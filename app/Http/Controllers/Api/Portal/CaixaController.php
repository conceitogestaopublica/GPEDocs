<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Assinatura;
use App\Models\Portal\Cidadao;
use App\Models\Processo\Oficio;
use App\Services\Portal\CidadaoPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Caixa do cidadão no Portal (TR 4.8.9): o que a Prefeitura mandou a ele —
 * ofícios eletrônicos para o e-mail confirmado do cadastro (na UG do portal) e
 * documentos à espera da assinatura dele por link (pelo CPF, no município).
 * Cada item leva o link público que já existe (rastreio do ofício, /assinar).
 */
class CaixaController extends PortalApiController
{
    private const LIMITE = 100;

    private const SITUACAO_ASSINATURA = [
        'pendente' => 'Aguardando a sua assinatura',
        'assinado' => 'Assinado',
        'recusado' => 'Recusado',
    ];

    public function index(Request $request, int $id): JsonResponse
    {
        $ug = $this->ug($request);
        $cidadao = Cidadao::where('id', $id)->where('ativo', true)->first();
        if (! $cidadao) {
            return response()->json(['erro' => 'Cidadão não encontrado.'], 404);
        }

        $itens = collect();

        // E-mail não confirmado não prova que o ofício é dele.
        if ($cidadao->email && $cidadao->email_verificado_em) {
            $oficios = Oficio::query()->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('ug_id', $ug->id)
                ->whereRaw('lower(destinatario_email) = ?', [mb_strtolower($cidadao->email)])
                ->whereNotNull('enviado_em')
                ->orderByDesc('enviado_em')
                ->limit(self::LIMITE)
                ->get(['id', 'numero', 'assunto', 'enviado_em', 'lido_em', 'status', 'rastreio_token']);

            foreach ($oficios as $o) {
                $itens->push([
                    'tipo'     => 'oficio',
                    'titulo'   => $o->assunto,
                    'numero'   => $o->numero,
                    'data'     => $o->enviado_em?->toIso8601String(),
                    'situacao' => $o->lido_em ? 'Lido' : 'Não lido',
                    'link'     => $o->rastreio_token ? url("/oficios/rastrear/{$o->rastreio_token}") : null,
                ]);
            }
        }

        $documento = CidadaoPortalService::digitos($cidadao->cpf);
        if (strlen($documento) === 11) {
            $assinaturas = Assinatura::query()
                ->with(['documento' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nome')])
                ->where('tipo_signatario', 'externo')
                ->where('cpf_signatario', $documento)
                ->whereNotNull('convite_token_hash')
                ->orderByDesc('created_at')
                ->limit(self::LIMITE)
                ->get();

            foreach ($assinaturas as $a) {
                $vencido = $a->status === 'pendente' && $a->convite_expira_em?->isPast();
                $itens->push([
                    'tipo'     => 'assinatura',
                    'titulo'   => $a->documento?->nome ?? 'Documento',
                    'numero'   => null,
                    'data'     => $a->created_at?->toIso8601String(),
                    'situacao' => $vencido ? 'Prazo encerrado' : (self::SITUACAO_ASSINATURA[$a->status] ?? $a->status),
                    'link'     => $a->convite_token ? url("/assinar/{$a->convite_token}") : null,
                ]);
            }
        }

        return response()->json([
            'itens' => $itens->sortByDesc('data')->values(),
        ]);
    }
}
