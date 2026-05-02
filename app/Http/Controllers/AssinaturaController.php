<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Notificacao;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use App\Services\AssinaturaIcpA3Service;
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
    public function index(\Illuminate\Http\Request $request): Response
    {
        // Filtros (apenas para a aba "assinadas")
        $busca = trim((string) $request->input('busca', ''));
        $tipoFiltro = $request->input('tipo_filtro');     // null | simples | qualificada
        $dataDe   = $request->input('data_de');
        $dataAte  = $request->input('data_ate');

        // Pendentes — sem filtro, sao poucas
        $pendentes = Assinatura::with(['documento', 'solicitacao.solicitante'])
            ->where('signatario_id', Auth::id())
            ->where('status', 'pendente')
            ->orderByDesc('created_at')
            ->get();

        // Assinadas — com busca + filtros + paginacao
        $assinadas = Assinatura::with(['documento', 'solicitacao.solicitante', 'certificado'])
            ->where('signatario_id', Auth::id())
            ->where('status', 'assinado')
            ->when($busca !== '', function ($q) use ($busca) {
                $termo = "%{$busca}%";
                $cpfDigits = preg_replace('/\D/', '', $busca);
                $q->where(function ($q) use ($termo, $cpfDigits) {
                    $q->whereHas('documento', fn ($q2) => $q2->where('nome', 'like', $termo))
                      ->orWhereHas('solicitacao', fn ($q2) => $q2->where('mensagem', 'like', $termo))
                      ->orWhere('email_signatario', 'like', $termo);
                    if ($cpfDigits !== '') {
                        $q->orWhere('cpf_signatario', 'like', "%{$cpfDigits}%");
                    }
                });
            })
            ->when(in_array($tipoFiltro, ['simples', 'qualificada'], true),
                fn ($q) => $q->where('tipo_assinatura', $tipoFiltro))
            ->when($dataDe, fn ($q) => $q->whereDate('assinado_em', '>=', $dataDe))
            ->when($dataAte, fn ($q) => $q->whereDate('assinado_em', '<=', $dataAte))
            ->orderByDesc('assinado_em')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('GED/Assinaturas/Index', [
            'pendentes' => $pendentes,
            'assinadas' => $assinadas,
            'filtros'   => [
                'busca'       => $busca,
                'tipo_filtro' => $tipoFiltro,
                'data_de'     => $dataDe,
                'data_ate'    => $dataAte,
            ],
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
            $this->finalizarProcessoSeVinculado($solicitacao);
            $this->dispararCallbackSistemaExterno($solicitacao);
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

    /**
     * Se a solicitacao esta vinculada a um processo (decisao administrativa),
     * marca o processo como concluido apos a assinatura ICP-Brasil.
     */
    private function finalizarProcessoSeVinculado(SolicitacaoAssinatura $solicitacao): void
    {
        $processo = \App\Models\Processo\Processo::where('solicitacao_assinatura_id', $solicitacao->id)
            ->where('status', 'aguardando_assinatura')
            ->first();

        if (! $processo) {
            return;
        }

        $processo->update([
            'status'       => 'concluido',
            'concluido_em' => now(),
        ]);

        \App\Models\Processo\ProcessoHistorico::create([
            'processo_id' => $processo->id,
            'usuario_id'  => Auth::id(),
            'acao'        => 'assinatura_decisao',
            'detalhes'    => [
                'decisao'        => $processo->decisao,
                'solicitacao_id' => $solicitacao->id,
            ],
        ]);
    }

    /**
     * Quando todas as assinaturas concluem em um documento que veio de
     * sistema externo (sistema_origem != null e callback_url presente),
     * dispara webhook POST para o sistema atualizar la.
     */
    private function dispararCallbackSistemaExterno(SolicitacaoAssinatura $solicitacao): void
    {
        $documento = $solicitacao->documento;
        if (! $documento || ! $documento->sistema_origem || ! $documento->callback_url
            || $documento->callback_executado) {
            return;
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(10)
                ->retry(2, 500)
                ->post($documento->callback_url, [
                    'sistema_origem'   => $documento->sistema_origem,
                    'numero_externo'   => $documento->numero_externo,
                    'documento_id'     => $documento->id,
                    'status'           => 'assinado',
                    'todas_assinadas'  => true,
                    'concluido_em'     => now()->toIso8601String(),
                    'pdf_assinado_url' => url("/documentos/{$documento->id}/download"),
                    'visualizacao_url' => url("/documentos/{$documento->id}"),
                ]);

            $documento->update([
                'callback_executado'    => true,
                'callback_executado_em' => now(),
            ]);

            \Log::info('Webhook de callback enviado', [
                'sistema'  => $documento->sistema_origem,
                'numero'   => $documento->numero_externo,
                'status'   => $resp->status(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Falha ao executar callback de sistema externo', [
                'sistema'  => $documento->sistema_origem,
                'numero'   => $documento->numero_externo,
                'callback' => $documento->callback_url,
                'erro'     => $e->getMessage(),
            ]);
        }
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
            'pfx'           => ['required', 'file', 'max:5120', 'extensions:pfx,p12'],
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
        $pdfAbsoluto = Storage::disk('documentos')->path($pdfRelativo);

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
            \Illuminate\Support\Facades\Log::error('Falha na assinatura ICP-Brasil A1', [
                'assinatura_id' => $assinatura->id,
                'user_id'       => Auth::id(),
                'erro'          => $e->getMessage(),
                'arquivo'       => $e->getFile() . ':' . $e->getLine(),
                'trace'         => $e->getTraceAsString(),
            ]);
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
        if ($todasAssinadas) {
            $this->finalizarProcessoSeVinculado($solicitacao);
            $this->dispararCallbackSistemaExterno($solicitacao);
        }

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
     * Etapa 1 do fluxo A3: cliente envia o cert publico (lido do token via
     * Web PKI da Lacuna). Servidor monta PDF com placeholder e devolve o
     * hash a ser assinado pelo token.
     *
     * Resposta JSON: { sessao_id, hash_a_assinar (b64), algoritmo_digest }
     */
    public function prepararIcpA3(Request $request, $id, AssinaturaIcpA3Service $svc, CertificadoService $certificadoService)
    {
        $request->validate([
            'cert_pem' => ['required', 'string', 'min:100'],
            'razao'    => ['nullable', 'string', 'max:200'],
            'local'    => ['nullable', 'string', 'max:200'],
        ]);

        $assinatura = Assinatura::with('documento.versaoAtual')->findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()) {
            return response()->json(['erro' => 'Sem permissão.'], 403);
        }
        if ($assinatura->status !== 'pendente') {
            return response()->json(['erro' => 'Esta assinatura já foi processada.'], 409);
        }

        $versao = $assinatura->documento->versaoAtual;
        if (! $versao) {
            return response()->json(['erro' => 'Documento sem versão disponível.'], 422);
        }

        $pdfRelativo = $versao->arquivo_path;
        $pdfAbsoluto = Storage::disk('documentos')->path($pdfRelativo);

        if (! is_file($pdfAbsoluto) || ! str_ends_with(strtolower((string) $pdfRelativo), '.pdf')) {
            return response()->json(['erro' => 'Apenas arquivos PDF podem receber assinatura ICP-Brasil.'], 422);
        }

        $certPem = (string) $request->input('cert_pem');

        // Validacoes do cert antes de prosseguir
        if (! $certificadoService->ehIcpBrasil($certPem)) {
            return response()->json(['erro' => 'Certificado fora da cadeia ICP-Brasil.'], 422);
        }
        if (! $certificadoService->validarCadeiaIcpBrasil($certPem, [])) {
            return response()->json(['erro' => 'Cadeia ICP-Brasil não validada — verifique a truststore (storage/app/private/icp-brasil).'], 422);
        }

        $meta = $certificadoService->lerMetadados($certPem);
        $cpfCert = preg_replace('/\D/', '', (string) $meta['subject_cpf']);
        $cpfUser = preg_replace('/\D/', '', (string) Auth::user()->cpf);
        if ($cpfUser && $cpfCert && $cpfUser !== $cpfCert) {
            return response()->json([
                'erro' => "CPF do certificado ({$cpfCert}) não confere com o CPF do usuário ({$cpfUser}).",
            ], 422);
        }
        if (! $cpfCert) {
            return response()->json([
                'erro' => 'CPF não encontrado no certificado (OID 2.16.76.1.3.1).',
            ], 422);
        }

        try {
            $resultado = $svc->preparar(
                $pdfAbsoluto,
                $certPem,
                [
                    'razao'   => $request->input('razao', 'Assinatura Eletrônica Qualificada (Lei 14.063/2020)'),
                    'local'   => $request->input('local', 'Brasil'),
                    'contato' => $meta['subject_cn'],
                ]
            );
        } catch (Throwable $e) {
            return response()->json(['erro' => 'Falha ao preparar assinatura: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'sessao_id'        => $resultado['sessao_id'],
            'hash_a_assinar'   => $resultado['hash_a_assinar'],
            'algoritmo_digest' => $resultado['algoritmo_digest'],
            'cpf'              => $cpfCert,
            'cn'               => $meta['subject_cn'],
        ]);
    }

    /**
     * Etapa 2 do fluxo A3: cliente envia os bytes RSA assinados pelo token
     * (a chave privada nunca sai do hardware). Servidor monta PKCS#7,
     * embute no PDF e persiste o registro de assinatura.
     */
    public function finalizarIcpA3(
        Request $request,
        $id,
        AssinaturaIcpA3Service $svc,
        CertificadoService $certificadoService,
    ) {
        $request->validate([
            'sessao_id'      => ['required', 'string', 'size:36'],
            'assinatura_b64' => ['required', 'string', 'min:50'],
            'cadeia_pem'     => ['nullable', 'array'],
            'cadeia_pem.*'   => ['string'],
            'geolocalizacao' => ['nullable', 'string'],
        ]);

        $assinatura = Assinatura::with(['documento', 'solicitacao'])->findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()) {
            return response()->json(['erro' => 'Sem permissão.'], 403);
        }
        if ($assinatura->status !== 'pendente') {
            return response()->json(['erro' => 'Esta assinatura já foi processada.'], 409);
        }

        try {
            $resultado = $svc->finalizar(
                (string) $request->input('sessao_id'),
                (string) $request->input('assinatura_b64'),
                (array)  $request->input('cadeia_pem', []),
            );
        } catch (Throwable $e) {
            return response()->json(['erro' => 'Falha ao finalizar assinatura: ' . $e->getMessage()], 500);
        }

        $meta = $resultado['meta'];
        $certPem = $meta['subject_dn'] ? $this->buscarCertNoResultado($resultado) : null;
        // Nota: registramos o cert via CertificadoService a partir do meta retornado.
        // Como nao temos o PEM diretamente aqui, lemos o thumbprint para vincular.
        $thumbprint = $meta['thumbprint_sha256'] ?? '';

        $certificado = \App\Models\Certificado::where('user_id', Auth::id())
            ->where('thumbprint_sha256', $thumbprint)
            ->first();

        // Se ainda nao registrado, persiste a partir do PEM da cadeia (devolvido pelo servico)
        if (! $certificado) {
            // Recupera o PEM via cache da sessao OU via fingerprint (recarregar do estado pre-finalizar nao da, ja foi limpado).
            // Como protecao: apenas registra dados minimos.
            $certificado = \App\Models\Certificado::create([
                'user_id'           => Auth::id(),
                'tipo'              => 'A3',
                'subject_cn'        => $meta['subject_cn'] ?? '',
                'subject_cpf'       => $meta['subject_cpf'] ?? null,
                'subject_dn'        => $meta['subject_dn'] ?? '',
                'issuer_cn'         => $meta['issuer_cn'] ?? '',
                'issuer_dn'         => $meta['issuer_dn'] ?? '',
                'serial_number'     => $meta['serial_number'] ?? '',
                'thumbprint_sha1'   => $meta['thumbprint_sha1'] ?? '',
                'thumbprint_sha256' => $thumbprint,
                'valido_de'         => $meta['valido_de'] ?? now(),
                'valido_ate'        => $meta['valido_ate'] ?? now(),
                'certificado_pem'   => '',
                'icp_brasil'        => true,
                'verificado_em'     => now(),
            ]);
        }

        $assinatura->update([
            'status'                  => 'assinado',
            'tipo_assinatura'         => 'qualificada',
            'certificado_id'          => $certificado->id,
            'cpf_signatario'          => $meta['subject_cpf'] ?? null,
            'ip'                      => $request->ip(),
            'geolocalizacao'          => $request->input('geolocalizacao'),
            'user_agent'              => $request->userAgent(),
            'hash_documento'          => $resultado['pdf_sha256'],
            // assinatura_pkcs7 nao e mais persistida (vive no PDF embutido
            // em arquivo_assinado_path); evita erro UTF-8 do PostgreSQL com
            // bytes binarios em coluna text.
            'cadeia_certificados'     => array_map(
                fn ($pem) => ['cn' => '', 'thumbprint' => strtolower((string) openssl_x509_fingerprint($pem, 'sha256'))],
                (array) $request->input('cadeia_pem', []),
            ),
            'politica_assinatura'     => ($meta['politica_nome'] ?? 'AD-RB v2') . ' (OID ' . ($meta['politica_oid'] ?? '2.16.76.1.7.1.1.2.3') . ')',
            'algoritmo_hash'          => 'SHA-256',
            'arquivo_assinado_path'   => $resultado['caminho'],
            'hash_assinatura_sha256'  => hash('sha256', $resultado['pkcs7']),
            'timestamp_assinatura'    => now(),
            'assinado_em'             => now(),
        ]);

        // Atualiza solicitacao
        $solicitacao = $assinatura->solicitacao;
        $todasAssinadas = $solicitacao->assinaturas()->where('status', 'pendente')->doesntExist();
        $solicitacao->update(['status' => $todasAssinadas ? 'concluida' : 'em_andamento']);
        if ($todasAssinadas) {
            $this->finalizarProcessoSeVinculado($solicitacao);
            $this->dispararCallbackSistemaExterno($solicitacao);
        }

        Notificacao::create([
            'usuario_id'      => $solicitacao->solicitante_id,
            'tipo'            => 'assinatura_realizada',
            'titulo'          => 'Documento assinado digitalmente (ICP-Brasil A3)',
            'mensagem'        => Auth::user()->name . " assinou \"{$assinatura->documento->nome}\" com Assinatura Qualificada ICP-Brasil (token A3).",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        AuditLog::create([
            'documento_id' => $assinatura->documento_id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'assinatura_qualificada_icp_a3',
            'detalhes'     => [
                'tipo'              => 'qualificada_a3',
                'thumbprint_sha256' => $thumbprint,
                'sessao_id'         => $request->input('sessao_id'),
                'caminho'           => $resultado['caminho'],
            ],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return response()->json([
            'ok'      => true,
            'caminho' => $resultado['caminho'],
            'mensagem'=> 'Documento assinado digitalmente com ICP-Brasil A3.',
        ]);
    }

    private function buscarCertNoResultado(array $resultado): ?string
    {
        return null; // helper placeholder; cert vive no PKCS7 binario
    }

    /**
     * Endpoint que devolve o PDF assinado para download.
     */
    public function downloadAssinado(Request $request, $id)
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

        $disk = Storage::disk('documentos');
        if (! $disk->exists($assinatura->arquivo_assinado_path)) {
            abort(404);
        }

        $nome = "assinado-icp-{$assinatura->documento_id}-{$assinatura->id}.pdf";

        // ?inline=1 abre direto no navegador (visualizacao); sem isso, baixa
        $disposition = $request->boolean('inline')
            ? "inline; filename=\"{$nome}\""
            : "attachment; filename=\"{$nome}\"";

        return response($disk->get($assinatura->arquivo_assinado_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition,
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
