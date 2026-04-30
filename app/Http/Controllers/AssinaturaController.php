<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Notificacao;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use App\Services\AssinaturaIcpService;
use App\Services\CertificadoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AssinaturaController extends Controller
{
    public function index(): Response
    {
        $pendentes = Assinatura::with(['documento', 'solicitacao.solicitante'])
            ->where('signatario_id', Auth::id())
            ->where('status', 'pendente')
            ->orderByDesc('created_at')
            ->get();

        $assinadas = Assinatura::with(['documento', 'solicitacao.solicitante'])
            ->where('signatario_id', Auth::id())
            ->where('status', 'assinado')
            ->orderByDesc('assinado_em')
            ->limit(20)
            ->get();

        return Inertia::render('GED/Assinaturas/Index', [
            'pendentes' => $pendentes,
            'assinadas' => $assinadas,
        ]);
    }

    public function solicitar(Request $request, $documentoId)
    {
        $request->validate([
            'signatarios'   => ['required', 'array', 'min:1'],
            'signatarios.*' => ['required', 'integer', 'exists:users,id'],
            'mensagem'      => ['nullable', 'string'],
            'prazo'         => ['nullable', 'date'],
        ]);

        $documento = Documento::with('versaoAtual')->findOrFail($documentoId);

        $solicitacao = SolicitacaoAssinatura::create([
            'documento_id'  => $documento->id,
            'solicitante_id'=> Auth::id(),
            'status'        => 'pendente',
            'mensagem'      => $request->input('mensagem'),
            'prazo'         => $request->input('prazo'),
        ]);

        foreach ($request->input('signatarios') as $idx => $userId) {
            $user = User::findOrFail($userId);

            Assinatura::create([
                'solicitacao_id'  => $solicitacao->id,
                'documento_id'    => $documento->id,
                'signatario_id'   => $userId,
                'ordem'           => $idx + 1,
                'status'          => 'pendente',
                'email_signatario'=> $user->email,
            ]);

            Notificacao::create([
                'usuario_id'      => $userId,
                'tipo'            => 'assinatura_pendente',
                'titulo'          => 'Assinatura solicitada',
                'mensagem'        => "Voce tem uma solicitacao de assinatura para o documento \"{$documento->nome}\".",
                'referencia_tipo' => 'documento',
                'referencia_id'   => $documento->id,
            ]);
        }

        AuditLog::create([
            'documento_id' => $documento->id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'solicitacao_assinatura',
            'detalhes'     => ['signatarios' => $request->input('signatarios')],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Solicitacao de assinatura enviada com sucesso.');
    }

    public function solicitarLote(Request $request)
    {
        $request->validate([
            'documento_ids'  => ['required', 'array', 'min:1'],
            'documento_ids.*'=> ['required', 'integer', 'exists:ged_documentos,id'],
            'signatarios'    => ['required', 'array', 'min:1'],
            'signatarios.*'  => ['required', 'integer', 'exists:users,id'],
            'mensagem'       => ['nullable', 'string'],
            'prazo'          => ['nullable', 'date'],
        ]);

        $count = 0;

        foreach ($request->input('documento_ids') as $documentoId) {
            $documento = Documento::with('versaoAtual')->findOrFail($documentoId);

            $solicitacao = SolicitacaoAssinatura::create([
                'documento_id'   => $documento->id,
                'solicitante_id' => Auth::id(),
                'status'         => 'pendente',
                'mensagem'       => $request->input('mensagem'),
                'prazo'          => $request->input('prazo'),
            ]);

            foreach ($request->input('signatarios') as $idx => $userId) {
                $user = User::findOrFail($userId);

                Assinatura::create([
                    'solicitacao_id'   => $solicitacao->id,
                    'documento_id'     => $documento->id,
                    'signatario_id'    => $userId,
                    'ordem'            => $idx + 1,
                    'status'           => 'pendente',
                    'email_signatario' => $user->email,
                ]);

                Notificacao::create([
                    'usuario_id'      => $userId,
                    'tipo'            => 'assinatura_pendente',
                    'titulo'          => 'Assinatura solicitada',
                    'mensagem'        => "Voce tem uma solicitacao de assinatura para o documento \"{$documento->nome}\".",
                    'referencia_tipo' => 'documento',
                    'referencia_id'   => $documento->id,
                ]);
            }

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'solicitacao_assinatura',
                'detalhes'     => ['signatarios' => $request->input('signatarios'), 'lote' => true],
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            $count++;
        }

        return redirect()->back()->with('success', "Assinatura solicitada para {$count} documento(s).");
    }

    public function assinar(Request $request, $id)
    {
        $request->validate([
            'cpf'           => ['required', 'string', 'min:11', 'max:14'],
            'geolocalizacao'=> ['nullable', 'string'],
        ]);

        $assinatura = Assinatura::with(['documento.versaoAtual', 'solicitacao'])->findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()) {
            return redirect()->back()->with('error', 'Voce nao tem permissao para assinar este documento.');
        }

        if ($assinatura->status !== 'pendente') {
            return redirect()->back()->with('error', 'Esta assinatura ja foi processada.');
        }

        $versaoAtual = $assinatura->documento->versaoAtual;

        $assinatura->update([
            'status'          => 'assinado',
            'cpf_signatario'  => $request->input('cpf'),
            'ip'              => $request->ip(),
            'geolocalizacao'  => $request->input('geolocalizacao'),
            'user_agent'      => $request->userAgent(),
            'hash_documento'  => $versaoAtual?->hash_sha256,
            'versao_id'       => $versaoAtual?->id,
            'assinado_em'     => now(),
        ]);

        // Verificar se todas as assinaturas da solicitacao foram concluidas
        $solicitacao = $assinatura->solicitacao;
        $todasAssinadas = $solicitacao->assinaturas()->where('status', 'pendente')->doesntExist();

        if ($todasAssinadas) {
            $solicitacao->update(['status' => 'concluida']);
        } else {
            $solicitacao->update(['status' => 'em_andamento']);
        }

        // Notificar solicitante
        Notificacao::create([
            'usuario_id'      => $solicitacao->solicitante_id,
            'tipo'            => 'assinatura_realizada',
            'titulo'          => 'Documento assinado',
            'mensagem'        => Auth::user()->name . " assinou o documento \"{$assinatura->documento->nome}\".",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        AuditLog::create([
            'documento_id' => $assinatura->documento_id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'assinatura',
            'detalhes'     => ['hash' => $versaoAtual?->hash_sha256, 'ip' => $request->ip()],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Documento assinado com sucesso.');
    }

    public function recusar(Request $request, $id)
    {
        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $assinatura = Assinatura::with(['documento', 'solicitacao'])->findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()) {
            return redirect()->back()->with('error', 'Voce nao tem permissao.');
        }

        $assinatura->update([
            'status'        => 'recusado',
            'motivo_recusa' => $request->input('motivo'),
            'ip'            => $request->ip(),
            'assinado_em'   => now(),
        ]);

        Notificacao::create([
            'usuario_id'      => $assinatura->solicitacao->solicitante_id,
            'tipo'            => 'assinatura_recusada',
            'titulo'          => 'Assinatura recusada',
            'mensagem'        => Auth::user()->name . " recusou assinar o documento \"{$assinatura->documento->nome}\".",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        return redirect()->back()->with('success', 'Assinatura recusada.');
    }

    /**
     * Assinatura Eletrônica Qualificada (Lei 14.063/2020 art. 4, III) — ICP-Brasil A1.
     *
     * Recebe upload de .pfx + senha, abre o material criptográfico em memória,
     * valida a cadeia ICP-Brasil, gera o PDF assinado em PAdES-BES e descarta
     * a chave privada. A senha NUNCA é persistida.
     */
    public function assinarIcp(
        Request $request,
        $id,
        CertificadoService $certificadoService,
        AssinaturaIcpService $assinaturaIcpService,
    ) {
        $request->validate([
            'pfx'           => ['required', 'file', 'max:5120', 'mimes:pfx,p12'],
            'senha'         => ['required', 'string'],
            'geolocalizacao'=> ['nullable', 'string'],
            'razao'         => ['nullable', 'string', 'max:200'],
            'local'         => ['nullable', 'string', 'max:200'],
        ]);

        $assinatura = Assinatura::with(['documento.versaoAtual', 'solicitacao'])->findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()) {
            return redirect()->back()->with('error', 'Voce nao tem permissao para assinar este documento.');
        }

        if ($assinatura->status !== 'pendente') {
            return redirect()->back()->with('error', 'Esta assinatura ja foi processada.');
        }

        $versao = $assinatura->documento->versaoAtual;
        if (! $versao) {
            return redirect()->back()->with('error', 'Documento sem versão disponível para assinar.');
        }

        $pdfRelativo = $versao->arquivo_path;
        $pdfAbsoluto = Storage::disk('local')->path($pdfRelativo);

        if (! is_file($pdfAbsoluto) || ! str_ends_with(strtolower($pdfRelativo), '.pdf')) {
            return redirect()->back()->with('error', 'Apenas arquivos PDF podem receber assinatura ICP-Brasil nesta versão.');
        }

        $pfxBinary = (string) file_get_contents($request->file('pfx')->getRealPath());
        $senha     = (string) $request->input('senha');

        try {
            // 1) Valida o certificado e cadeia
            $material = $certificadoService->abrirPfx($pfxBinary, $senha);
            $cadeiaIcp = $certificadoService->validarCadeiaIcpBrasil(
                $material['cert'],
                $material['extracerts']
            );
            if (! $cadeiaIcp) {
                return redirect()->back()->with('error',
                    'Cadeia ICP-Brasil não pôde ser validada. Verifique se a truststore do ITI está instalada em storage/app/icp-brasil/ e se o certificado é ICP-Brasil válido.'
                );
            }

            $meta = $certificadoService->lerMetadados($material['cert']);

            // 2) Verifica que o CPF do cert bate com o do usuário (se cadastrado)
            $cpfCert = preg_replace('/\D/', '', (string) $meta['subject_cpf']);
            $cpfUser = preg_replace('/\D/', '', (string) Auth::user()->cpf);

            if ($cpfUser && $cpfCert && $cpfUser !== $cpfCert) {
                return redirect()->back()->with('error',
                    "O CPF do certificado ({$cpfCert}) não confere com o CPF cadastrado para este usuário ({$cpfUser})."
                );
            }
            if (! $cpfCert) {
                return redirect()->back()->with('error',
                    'CPF não encontrado no certificado (OID 2.16.76.1.3.1). Cert pode não ser e-CPF ICP-Brasil.'
                );
            }

            // 3) Registra/atualiza certificado do usuário
            $certificado = $certificadoService->registrarParaUsuario(
                Auth::user(),
                $material['cert'],
                $material['extracerts']
            );

            // 4) Gera PDF assinado em PAdES-BES
            $resultado = $assinaturaIcpService->assinarPdf(
                $pdfAbsoluto,
                $pfxBinary,
                $senha,
                [
                    'razao'   => $request->input('razao', 'Assinatura Eletrônica Qualificada (Lei 14.063/2020)'),
                    'local'   => $request->input('local', 'Brasil'),
                    'contato' => $meta['subject_cn'],
                ]
            );

            // 5) Persiste no registro de assinatura
            $assinaturaIcpService->registrarAssinatura(
                $assinatura,
                $certificado,
                $resultado,
                $cpfCert,
                (string) $request->ip(),
                $request->input('geolocalizacao'),
                $request->userAgent(),
            );
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Falha na assinatura digital: ' . $e->getMessage());
        } finally {
            // Apaga material sensível da memória PHP (best-effort)
            $pfxBinary = str_repeat("\0", strlen($pfxBinary));
            $senha     = str_repeat("\0", strlen($senha));
            unset($pfxBinary, $senha, $material);
        }

        // Atualiza solicitação
        $solicitacao = $assinatura->solicitacao;
        $todasAssinadas = $solicitacao->assinaturas()->where('status', 'pendente')->doesntExist();
        $solicitacao->update(['status' => $todasAssinadas ? 'concluida' : 'em_andamento']);

        Notificacao::create([
            'usuario_id'      => $solicitacao->solicitante_id,
            'tipo'            => 'assinatura_realizada',
            'titulo'          => 'Documento assinado digitalmente (ICP-Brasil)',
            'mensagem'        => Auth::user()->name . " assinou \"{$assinatura->documento->nome}\" com Assinatura Qualificada ICP-Brasil.",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        AuditLog::create([
            'documento_id' => $assinatura->documento_id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'assinatura_qualificada_icp',
            'detalhes'     => [
                'tipo'            => 'qualificada',
                'cpf'             => $cpfCert,
                'issuer_cn'       => $meta['issuer_cn'] ?? null,
                'serial'          => $meta['serial_number'] ?? null,
                'thumbprint_sha256' => $meta['thumbprint_sha256'] ?? null,
                'politica_oid'    => '2.16.76.1.7.1.1.2.3',
            ],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Documento assinado digitalmente (ICP-Brasil) com sucesso.');
    }

    /**
     * Endpoint que devolve o PDF assinado para download.
     */
    public function downloadAssinado($id)
    {
        $assinatura = Assinatura::findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()
            && $assinatura->solicitacao->solicitante_id !== Auth::id()
            && $assinatura->documento->autor_id !== Auth::id()) {
            abort(403);
        }

        if (! $assinatura->arquivo_assinado_path) {
            abort(404, 'Esta assinatura não possui PDF qualificado anexado.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($assinatura->arquivo_assinado_path)) {
            abort(404);
        }

        $nome = "assinado-icp-{$assinatura->documento_id}-{$assinatura->id}.pdf";
        return response($disk->get($assinatura->arquivo_assinado_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nome}\"",
        ]);
    }

    public function manifesto($solicitacaoId)
    {
        $solicitacao = SolicitacaoAssinatura::with([
            'documento.tipoDocumental',
            'documento.autor',
            'solicitante',
            'assinaturas.signatario',
            'assinaturas.certificado',
        ])->findOrFail($solicitacaoId);

        $documento = $solicitacao->documento;

        $pdf = Pdf::loadView('assinaturas.manifesto', [
            'solicitacao' => $solicitacao,
            'documento'   => $documento,
        ]);

        $filename = "manifesto-assinatura-{$documento->nome}-{$solicitacao->id}.pdf";

        return $pdf->download($filename);
    }
}
