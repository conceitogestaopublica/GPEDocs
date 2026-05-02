<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\SistemaIntegrado;
use App\Models\SolicitacaoAssinatura;
use App\Models\TipoDocumental;
use App\Models\User;
use App\Models\Versao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Endpoint REST para sistemas externos (GPE, RH, patrimonio, etc) enviarem
 * documentos para assinatura digital + arquivamento no GPE Docs.
 *
 * POST /api/integracoes/documentos
 *   Headers: Authorization: Bearer {token}
 *   Body (JSON):
 *     {
 *       "tipo": "empenho",                       // codigo do tipo documental no GPE Docs
 *       "ug_codigo": "UG-001",                   // codigo da UG (multi-tenant)
 *       "numero": "2026/000123",                 // numero externo do sistema
 *       "nome": "Empenho 2026/000123",           // nome de exibicao
 *       "descricao": "...",
 *       "metadados": { ... }                     // chave/valor JSON livre
 *       "pdf_base64": "JVBERi0...",              // PDF em base64
 *       "signatarios": [
 *         { "cpf": "12345678900", "ordem": 1 },
 *         { "cpf": "98765432100", "ordem": 2 }
 *       ],
 *       "callback_url": "https://gpe.exemplo.com/webhooks/assinatura",
 *       "pasta_codigo": "EMPENHOS"               // opcional — pasta de arquivamento
 *     }
 *
 * Retorna:
 *   201 { "id": 42, "numero_externo": "2026/000123", "url_visualizacao": "...",
 *         "signatarios": [...] }
 */
class IntegracaoDocumentoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var SistemaIntegrado $sistema */
        $sistema = $request->attributes->get('sistema_integrado');

        $validated = $request->validate([
            'tipo'           => ['required', 'string', 'max:100'],
            'ug_codigo'      => ['required', 'string', 'max:20'],
            'numero'         => ['required', 'string', 'max:100'],
            'nome'           => ['required', 'string', 'max:255'],
            'descricao'      => ['nullable', 'string'],
            'metadados'      => ['nullable', 'array'],
            'pdf_base64'     => ['required', 'string'],
            'signatarios'    => ['required', 'array', 'min:1'],
            'signatarios.*.cpf'   => ['required', 'string', 'min:11', 'max:14'],
            'signatarios.*.ordem' => ['nullable', 'integer', 'min:1'],
            'signatarios.*.email' => ['nullable', 'email', 'max:200'],
            'callback_url'   => ['nullable', 'url', 'max:500'],
            'pasta_codigo'   => ['nullable', 'string', 'max:100'],
        ]);

        try {
            DB::beginTransaction();

            // 1. Resolve UG por codigo
            $ug = \App\Models\Ug::where('codigo', $validated['ug_codigo'])->first();
            if (! $ug) {
                return response()->json(['erro' => "UG nao encontrada: {$validated['ug_codigo']}"], 422);
            }

            // 2. Resolve tipo documental — busca por nome (case-insensitive) ou por sistema_origem
            $tipoDoc = TipoDocumental::where('ativo', true)
                ->where(function ($q) use ($validated, $sistema) {
                    $q->whereRaw('LOWER(nome) = ?', [strtolower($validated['tipo'])])
                      ->orWhere(function ($q2) use ($validated, $sistema) {
                          $q2->where('sistema_origem', $sistema->codigo)
                             ->whereRaw('LOWER(nome) = ?', [strtolower($validated['tipo'])]);
                      });
                })
                ->first();
            if (! $tipoDoc) {
                return response()->json(['erro' => "Tipo documental nao cadastrado: {$validated['tipo']}"], 422);
            }

            // 3. Resolve pasta (opcional)
            $pastaId = null;
            if (! empty($validated['pasta_codigo'])) {
                $pasta = DB::table('ged_pastas')
                    ->where('ug_id', $ug->id)
                    ->whereRaw('LOWER(nome) = ?', [strtolower($validated['pasta_codigo'])])
                    ->first();
                $pastaId = $pasta?->id;
            }

            // 4. Resolve signatarios (cria User externo se CPF nao existe)
            $assinaturasParaCriar = [];
            foreach ($validated['signatarios'] as $idx => $sig) {
                $cpf = preg_replace('/\D/', '', $sig['cpf']);
                $user = User::where('cpf', $cpf)->first();

                if (! $user) {
                    // Cria user externo (sem senha — precisa setar depois pra logar)
                    $user = User::create([
                        'name'     => 'Signatario ' . substr($cpf, 0, 3) . '...' . substr($cpf, -2),
                        'email'    => $sig['email'] ?? "ext-{$cpf}@externo.local",
                        'cpf'      => $cpf,
                        'password' => bcrypt(Str::random(32)),
                        'tipo'     => 'externo',
                        'ug_id'    => $ug->id,
                    ]);
                }

                $assinaturasParaCriar[] = [
                    'user'  => $user,
                    'ordem' => $sig['ordem'] ?? ($idx + 1),
                ];
            }

            // 5. Decode + persiste PDF
            $pdfBytes = base64_decode($validated['pdf_base64'], true);
            if ($pdfBytes === false || strlen($pdfBytes) < 100) {
                return response()->json(['erro' => 'pdf_base64 invalido ou vazio.'], 422);
            }

            $filename = 'integracao-' . $sistema->codigo . '-' . str_replace(['/','\\'], '-', $validated['numero']) . '.pdf';
            $path = 'documentos/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
            Storage::disk('documentos')->put($path, $pdfBytes);

            // 6. Cria Documento
            $documento = Documento::create([
                'ug_id'              => $ug->id,
                'nome'               => $validated['nome'],
                'descricao'          => $validated['descricao'] ?? null,
                'tipo_documental_id' => $tipoDoc->id,
                'pasta_id'           => $pastaId,
                'versao_atual'       => 1,
                'tamanho'            => strlen($pdfBytes),
                'mime_type'          => 'application/pdf',
                'autor_id'           => $assinaturasParaCriar[0]['user']->id,
                'status'             => 'rascunho',
                'sistema_origem'     => $sistema->codigo,
                'numero_externo'     => $validated['numero'],
                'metadados_externos' => $validated['metadados'] ?? null,
                'callback_url'       => $validated['callback_url'] ?? null,
            ]);

            Versao::create([
                'documento_id' => $documento->id,
                'versao'       => 1,
                'arquivo_path' => $path,
                'tamanho'      => strlen($pdfBytes),
                'hash_sha256'  => hash('sha256', $pdfBytes),
                'autor_id'     => $assinaturasParaCriar[0]['user']->id,
                'comentario'   => "Recebido via API de {$sistema->nome}",
            ]);

            // 7. Cria SolicitacaoAssinatura + Assinaturas (uma por signatario, com ordem)
            $solicitante = $assinaturasParaCriar[0]['user'];
            $solicitacao = SolicitacaoAssinatura::create([
                'documento_id'   => $documento->id,
                'solicitante_id' => $solicitante->id,
                'status'         => 'pendente',
                'mensagem'       => "Documento {$validated['numero']} enviado por {$sistema->nome} para assinatura.",
            ]);

            $assinaturasResp = [];
            foreach ($assinaturasParaCriar as $a) {
                $ass = Assinatura::create([
                    'solicitacao_id'   => $solicitacao->id,
                    'documento_id'     => $documento->id,
                    'signatario_id'    => $a['user']->id,
                    'ordem'            => $a['ordem'],
                    'status'           => 'pendente',
                    'email_signatario' => $a['user']->email,
                ]);
                $assinaturasResp[] = [
                    'id'    => $ass->id,
                    'cpf'   => $a['user']->cpf,
                    'ordem' => $a['ordem'],
                ];
            }

            DB::commit();

            return response()->json([
                'id'              => $documento->id,
                'numero_externo'  => $documento->numero_externo,
                'sistema_origem'  => $documento->sistema_origem,
                'tipo'            => $tipoDoc->nome,
                'ug'              => $ug->codigo,
                'pasta'           => $pasta->nome ?? null,
                'url_visualizacao'=> url("/documentos/{$documento->id}"),
                'solicitacao_id'  => $solicitacao->id,
                'signatarios'     => $assinaturasResp,
                'criado_em'       => $documento->created_at->toIso8601String(),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'erro'    => 'Falha ao processar documento.',
                'detalhe' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Status de um documento ja enviado — util pra GPE poliar antes do webhook.
     * GET /api/integracoes/documentos/{numero_externo}
     */
    public function show(Request $request, string $numeroExterno): JsonResponse
    {
        $sistema = $request->attributes->get('sistema_integrado');

        $documento = Documento::with(['solicitacoesAssinatura.assinaturas'])
            ->where('sistema_origem', $sistema->codigo)
            ->where('numero_externo', $numeroExterno)
            ->first();

        if (! $documento) {
            return response()->json(['erro' => 'Documento nao encontrado para este sistema.'], 404);
        }

        $solicitacao = $documento->solicitacoesAssinatura->first();
        $assinaturas = ($solicitacao?->assinaturas ?? collect())->map(fn ($a) => [
            'cpf'         => $a->signatario?->cpf,
            'ordem'       => $a->ordem,
            'status'      => $a->status,
            'assinado_em' => $a->assinado_em?->toIso8601String(),
        ]);

        return response()->json([
            'id'              => $documento->id,
            'numero_externo'  => $documento->numero_externo,
            'status'          => $documento->status,
            'solicitacao'     => $solicitacao?->status,
            'assinaturas'     => $assinaturas,
            'todas_assinadas' => $assinaturas->where('status', 'pendente')->isEmpty(),
            'callback_executado' => (bool) $documento->callback_executado,
            'pdf_assinado_url'   => url("/documentos/{$documento->id}/download"),
            'visualizacao_url'   => url("/documentos/{$documento->id}"),
        ]);
    }
}
