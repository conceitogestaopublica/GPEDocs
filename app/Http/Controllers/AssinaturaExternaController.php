<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\CodigoConfirmacaoEmail;
use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Notificacao;
use App\Models\Portal\Cidadao;
use App\Models\Ug;
use App\Services\CarimboAssinaturaService;
use App\Services\ConviteAssinaturaService;
use App\Services\FluxoAssinaturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Assinatura por link para signatário EXTERNO (modelo "Conecta Ouro Branco"):
 *
 *   1. recebe o link /assinar/{token} (e-mail ou WhatsApp);
 *   2. entra com CPF e senha, ou se cadastra (portal_cidadaos) — o CPF tem de ser o do convite;
 *   3. confirma o e-mail do cadastro com um código (uma vez);
 *   4. vê o PDF, posiciona/redimensiona/gira o carimbo e confirma com a senha;
 *   5. o PDF carimbado fica no GED como versão oficial e pode ser baixado.
 *
 * Público: a autorização é o convite + o cadastro com o mesmo CPF (guard `cidadao`).
 */
class AssinaturaExternaController extends Controller
{
    /** Validade do código de confirmação do e-mail. */
    private const MINUTOS_CODIGO = 15;

    public function __construct(
        private readonly ConviteAssinaturaService $convites,
        private readonly FluxoAssinaturaService $fluxo,
        private readonly CarimboAssinaturaService $carimbo,
        private readonly \App\Services\PacoteAssinaturaService $pacotes,
    ) {
    }

    public function show(string $token): Response
    {
        $assinatura = $this->convites->porToken($token);
        if (! $assinatura) {
            return $this->tela('invalido');
        }

        if (! $assinatura->convite_acessado_em) {
            $assinatura->forceFill(['convite_acessado_em' => now()])->save();
        }

        $base = ['convite' => $this->dadosConvite($assinatura), 'token' => $token,
            'pacote_link' => $assinatura->pacoteConvite?->link()];
        $cidadao = $this->cidadao();

        if ($assinatura->status === 'assinado') {
            return $this->tela('assinado', $base + ['assinado_em' => CarimboAssinaturaService::horaBrasilia($assinatura->assinado_em),
                'codigo' => $assinatura->codigo_verificacao, 'pode_baixar' => $this->ehDoConvite($cidadao, $assinatura)]);
        }
        if ($assinatura->status === 'recusado') {
            return $this->tela('recusado', $base);
        }
        if (in_array($assinatura->solicitacao?->status, ['cancelada', 'recusada'], true)
            || $assinatura->documento?->status === 'cancelado') {
            return $this->tela('encerrado', $base);
        }
        if ($this->convites->expirado($assinatura)) {
            return $this->tela('expirado', $base);
        }

        if (! $cidadao) {
            return $this->tela('acesso', $base);
        }
        if (! $this->ehDoConvite($cidadao, $assinatura)) {
            return $this->tela('outra_conta', $base + ['logado_como' => $cidadao->nome]);
        }
        if (! $cidadao->email_verificado_em) {
            return $this->tela('confirmar_email', $base + ['email' => $this->mascararEmail($cidadao->email)]);
        }
        if (! $this->fluxo->ehAVez($assinatura)) {
            return $this->tela('aguardando_vez', $base);
        }

        try {
            $paginas = $this->carimbo->tamanhoPaginas(
                Storage::disk('documentos')->path($assinatura->documento->versaoAtual->arquivo_path)
            );
        } catch (Throwable $e) {
            Log::warning('Assinatura externa: PDF não pôde ser lido', ['assinatura_id' => $assinatura->id, 'erro' => $e->getMessage()]);

            return $this->tela('erro_pdf', $base);
        }

        return $this->tela('assinar', $base + [
            'paginas'  => $paginas,
            'carimbo'  => [
                'nome'   => $assinatura->nome_signatario,
                'cpf'    => ConviteAssinaturaService::mascararCpf($assinatura->cpf_signatario),
                'w'      => CarimboAssinaturaService::LARGURA_PADRAO,
                'h'      => CarimboAssinaturaService::ALTURA_PADRAO,
            ],
            'posicao_sugerida' => $assinatura->signature_position,
        ]);
    }

    /** Entrar com CPF (ou e-mail) e senha. */
    public function entrar(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'senha' => ['required', 'string'],
        ]);
        if (\App\Support\Captcha::exigidoNaEntrada($request, 'assinatura-entrar')) {
            \App\Support\Captcha::validar($request, 'assinatura-entrar');
        }

        $login = trim((string) $request->input('login'));
        $cpf = ConviteAssinaturaService::soDigitos($login);
        $cidadao = str_contains($login, '@')
            ? Cidadao::where('email', mb_strtolower($login))->first()
            : (strlen($cpf) === 11 ? $this->cidadaoPorCpf($cpf) : null);

        if (! $cidadao || ! $cidadao->ativo || ! Hash::check((string) $request->input('senha'), $cidadao->senha)) {
            \App\Support\Captcha::registrarFalha($request, 'assinatura-entrar');

            return back()->withErrors(['login' => 'CPF ou senha incorretos.'])->onlyInput('login');
        }

        \App\Support\Captcha::limparFalhas($request, 'assinatura-entrar');
        Auth::guard('cidadao')->login($cidadao);
        $request->session()->regenerate();

        return redirect($this->base($request, $token));
    }

    /** Cadastro rápido — o CPF tem de ser o do convite; depois confirma o e-mail. */
    public function cadastrar(Request $request, string $token): RedirectResponse
    {
        $cpfConvite = $this->cpfDoConvite($request, $token);
        abort_unless($cpfConvite, 404);

        $dados = $request->validate([
            'cpf'      => ['required', 'string', 'max:14'],
            'nome'     => ['required', 'string', 'min:5', 'max:200'],
            'email'    => ['required', 'email', 'max:150', 'confirmed'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'senha'    => ['required', 'string', 'min:8', 'confirmed'],
            'aceite'   => ['accepted'],
        ], [
            'email.confirmed' => 'Os e-mails não conferem.',
            'senha.confirmed' => 'As senhas não conferem.',
            'senha.min'       => 'A senha precisa ter pelo menos 8 caracteres.',
            'aceite.accepted' => 'É preciso aceitar os termos de uso.',
        ]);
        \App\Support\Captcha::validar($request, 'assinatura-cadastro');

        $cpf = ConviteAssinaturaService::soDigitos($dados['cpf']);
        if (! ConviteAssinaturaService::cpfValido($cpf)) {
            return back()->withErrors(['cpf' => 'CPF inválido.'])->withInput($request->except('senha', 'senha_confirmation'));
        }
        if ($cpf !== $cpfConvite) {
            return back()->withErrors(['cpf' => 'Este documento foi enviado para outro CPF. Confira o CPF digitado.'])
                ->withInput($request->except('senha', 'senha_confirmation'));
        }
        if ($this->cidadaoPorCpf($cpf)) {
            return back()->withErrors(['cpf' => 'Já existe cadastro com este CPF. Use "Entrar" com a sua senha.'])
                ->withInput($request->except('senha', 'senha_confirmation'));
        }
        $email = mb_strtolower(trim($dados['email']));
        if (Cidadao::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'Este e-mail já está em outro cadastro.'])
                ->withInput($request->except('senha', 'senha_confirmation'));
        }

        $cidadao = Cidadao::create([
            'nome'     => trim($dados['nome']),
            'email'    => $email,
            'cpf'      => $cpf,
            'telefone' => $dados['telefone'] ?? null,
            'senha'    => Hash::make($dados['senha']),
            'ativo'    => true,
        ]);

        Auth::guard('cidadao')->login($cidadao);
        $request->session()->regenerate();
        $this->enviarCodigo($cidadao);

        return redirect($this->base($request, $token))->with('success', 'Cadastro criado. Enviamos um código para o seu e-mail.');
    }

    public function confirmarEmail(Request $request, string $token): RedirectResponse
    {
        $request->validate(['codigo' => ['required', 'string', 'size:6']]);
        $cidadao = $this->cidadao();
        abort_unless($cidadao, 403);

        $valido = $cidadao->token_verificacao
            && $cidadao->token_verificacao_expira_em?->isFuture()
            && hash_equals($cidadao->token_verificacao, hash('sha256', (string) $request->input('codigo')));
        if (! $valido) {
            return back()->withErrors(['codigo' => 'Código incorreto ou vencido. Peça um novo código.']);
        }

        $cidadao->forceFill([
            'email_verificado_em'         => now(),
            'token_verificacao'           => null,
            'token_verificacao_expira_em' => null,
        ])->save();

        return redirect($this->base($request, $token))->with('success', 'E-mail confirmado.');
    }

    public function reenviarCodigo(string $token): RedirectResponse
    {
        $cidadao = $this->cidadao();
        abort_unless($cidadao, 403);
        $this->enviarCodigo($cidadao);

        return back()->with('success', 'Enviamos um novo código para o seu e-mail.');
    }

    public function sair(Request $request, string $token): RedirectResponse
    {
        Auth::guard('cidadao')->logout();
        $request->session()->regenerateToken();

        return redirect($this->base($request, $token));
    }

    /** PDF para a tela de assinatura: a versão oficial atual, já com os carimbos de quem assinou antes. */
    public function pdf(string $token)
    {
        $assinatura = $this->autorizada($token);
        $caminho = $this->arquivoOficial($assinatura);

        return response(Storage::disk('documentos')->get($caminho), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento.pdf"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function assinar(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'senha'            => ['required', 'string'],
            'posicao'          => ['required', 'array'],
            'posicao.pagina'   => ['required', 'integer', 'min:1'],
            'posicao.x'        => ['required', 'numeric', 'between:0,1'],
            'posicao.y'        => ['required', 'numeric', 'between:0,1'],
            'posicao.w'        => ['required', 'numeric', 'between:0.02,1'],
            'posicao.h'        => ['required', 'numeric', 'between:0.01,1'],
            'posicao.rotacao'  => ['nullable', 'integer', 'in:0,90,180,270'],
            'geolocalizacao'   => ['nullable', 'string', 'max:255'],
        ]);

        $assinatura = $this->autorizada($token);
        $cidadao = $this->cidadao();

        if (! Hash::check((string) $request->input('senha'), $cidadao->senha)) {
            return back()->withErrors(['senha' => 'Senha incorreta.']);
        }
        $paginas = $this->carimbo->tamanhoPaginas(Storage::disk('documentos')->path($assinatura->documento->versaoAtual->arquivo_path));
        $pagina = (int) $request->input('posicao.pagina');
        if ($pagina > count($paginas)) {
            return back()->with('error', 'Página inválida.');
        }
        $posicao = CarimboAssinaturaService::posicaoDaTela(
            $request->input('posicao'), $paginas[$pagina - 1]['w'], $paginas[$pagina - 1]['h']
        );

        if ($erro = $this->executar($assinatura, $cidadao, $posicao, $request)) {
            return back()->with('error', $erro);
        }

        return redirect($request->boolean('voltar_pacote') && $assinatura->pacoteConvite
            ? '/assinar/pacote/' . $assinatura->pacoteConvite->token
            : "/assinar/{$token}")->with('success', 'Documento assinado com sucesso.');
    }

    /**
     * Núcleo da assinatura por link — usado pela tela de um documento e pelo "Assinar todos"
     * do pacote. $posicao já em mm ({page,x,y,w,h,rotacao}). Devolve a mensagem de erro, ou
     * null se assinou.
     */
    public function executar(Assinatura $assinatura, Cidadao $cidadao, array $posicao, Request $request): ?string
    {
        if ($assinatura->status !== 'pendente') {
            return 'Esta assinatura já foi processada.';
        }
        if ($impedimento = $this->fluxo->impedimento($assinatura)) {
            return $impedimento;
        }

        $versao = $assinatura->documento->versaoAtual;

        try {
            DB::transaction(function () use ($assinatura, $cidadao, $versao, $posicao, $request) {
                // Trava a linha: dois cliques (ou duas abas) não assinam duas vezes.
                $travada = Assinatura::whereKey($assinatura->id)->lockForUpdate()->first();
                if ($travada->status !== 'pendente') {
                    throw new \DomainException('Esta assinatura já foi processada.');
                }

                $assinatura->update([
                    'status'             => 'assinado',
                    'tipo_assinatura'    => 'simples',
                    'cidadao_id'         => $cidadao->id,
                    'email_signatario'   => $assinatura->email_signatario ?: $cidadao->email,
                    'ip'                 => $request->ip(),
                    'geolocalizacao'     => $request->input('geolocalizacao'),
                    'user_agent'         => $request->userAgent(),
                    'hash_documento'     => $versao->hash_sha256,
                    'versao_id'          => $versao->id,
                    'signature_position' => $posicao,
                    'codigo_verificacao' => $this->novoCodigo(),
                    'assinado_em'        => now(),
                ]);

                $gerado = $this->carimbo->gerar($assinatura);
                $selo = $gerado['selo'];
                $assinatura->update([
                    'arquivo_assinado_path'  => $gerado['caminho'],
                    'hash_assinatura_sha256' => hash('sha256', Storage::disk('documentos')->get($gerado['caminho'])),
                    'timestamp_assinatura'   => now(),
                    'politica_assinatura'    => $selo
                        ? 'Eletrônica por link, selada pela prefeitura (' . ($selo['icp_brasil'] ? 'ICP-Brasil' : 'certificado de teste') . ')'
                        : 'Eletrônica por link (sem selo da prefeitura)',
                ]);
            });
        } catch (\DomainException $e) {
            return $e->getMessage();
        } catch (Throwable $e) {
            Log::error('Falha na assinatura externa', ['assinatura_id' => $assinatura->id, 'erro' => $e->getMessage(),
                'arquivo' => $e->getFile() . ':' . $e->getLine()]);

            return 'Não foi possível assinar o documento agora. Tente de novo em instantes.';
        }

        $assinatura->solicitacao->setRelation('documento', $assinatura->documento);
        $this->fluxo->aposAssinar($assinatura, 'simples', null);

        Notificacao::create([
            'usuario_id'      => $assinatura->solicitacao->solicitante_id,
            'tipo'            => 'assinatura_realizada',
            'titulo'          => 'Documento assinado',
            'mensagem'        => "{$assinatura->nome_signatario} (externo) assinou o documento \"{$assinatura->documento->nome}\".",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        AuditLog::create([
            'documento_id' => $assinatura->documento_id,
            'usuario_id'   => null,
            'acao'         => 'assinatura_externa',
            'detalhes'     => [
                'signatario' => $assinatura->nome_signatario,
                'cpf'        => ConviteAssinaturaService::mascararCpf($assinatura->cpf_signatario),
                'cidadao_id' => $cidadao->id,
                'codigo'     => $assinatura->codigo_verificacao,
                'hash'       => $versao->hash_sha256,
                'pagina'     => $posicao['page'],
                'forma'      => $assinatura->politica_assinatura,
            ],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return null;
    }

    public function recusar(Request $request, string $token): RedirectResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']]);
        $assinatura = $this->autorizada($token);

        if ($assinatura->status !== 'pendente') {
            return back()->with('error', 'Esta assinatura já foi processada.');
        }

        $assinatura->update([
            'status'        => 'recusado',
            'motivo_recusa' => $request->input('motivo'),
            'cidadao_id'    => $this->cidadao()->id,
            'ip'            => $request->ip(),
            'assinado_em'   => now(),
        ]);
        $assinatura->solicitacao->update(['status' => 'recusada']);
        $assinatura->solicitacao->setRelation('documento', $assinatura->documento);

        AuditLog::create([
            'documento_id' => $assinatura->documento_id,
            'usuario_id'   => null,
            'acao'         => 'recusa_assinatura',
            'detalhes'     => ['signatario' => $assinatura->nome_signatario, 'externo' => true,
                'motivo' => $request->input('motivo'), 'solicitacao_id' => $assinatura->solicitacao_id],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        Notificacao::create([
            'usuario_id'      => $assinatura->solicitacao->solicitante_id,
            'tipo'            => 'assinatura_recusada',
            'titulo'          => 'Assinatura recusada',
            'mensagem'        => "{$assinatura->nome_signatario} (externo) recusou assinar o documento \"{$assinatura->documento->nome}\".",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        $this->fluxo->dispararWebhook($assinatura->solicitacao, 'assinatura.recusada', [
            'signatario_nome' => $assinatura->nome_signatario,
            'externo'         => true,
            'cpf'             => $assinatura->cpf_signatario,
            'motivo'          => $request->input('motivo'),
            'recusado_em'     => now()->toIso8601String(),
        ]);

        return redirect($this->base($request, $token));
    }

    /** Baixa o PDF assinado (a versão oficial mais recente, com todos os carimbos). */
    public function baixar(string $token)
    {
        $assinatura = $this->convites->porToken($token);
        abort_unless($assinatura && $assinatura->status === 'assinado'
            && $this->ehDoConvite($this->cidadao(), $assinatura), 403);

        $caminho = $this->arquivoOficial($assinatura);
        $nome = Str::slug((string) $assinatura->documento->nome) ?: 'documento';

        return response(Storage::disk('documentos')->get($caminho), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nome}-assinado.pdf\"",
        ]);
    }

    // ── pacote: vários documentos, um link ──────────────────────────────────

    public function showPacote(string $token): Response
    {
        $convite = $this->pacotes->porToken($token);
        if (! $convite) {
            return $this->tela('invalido', ['pacote' => true]);
        }
        if (! $convite->acessado_em) {
            $convite->forceFill(['acessado_em' => now()])->save();
        }

        $ugId = Assinatura::where('pacote_convite_id', $convite->id)
            ->join('ged_documentos as d', 'd.id', '=', 'ged_assinaturas.documento_id')->value('d.ug_id');
        $base = ['token' => $token, 'pacote' => true, 'convite' => [
            'nome'        => $convite->nome,
            'cpf'         => ConviteAssinaturaService::mascararCpf($convite->cpf),
            'documento'   => $convite->pacote->titulo,
            'orgao'       => Ug::withoutGlobalScopes()->find($ugId)?->nome,
            'solicitante' => $convite->pacote->solicitante?->name,
            'mensagem'    => null,
            'expira_em'   => $convite->expira_em?->format('d/m/Y'),
        ]];

        $cidadao = $this->cidadao();
        if (! $cidadao) {
            return $this->tela('acesso', $base);
        }
        if (ConviteAssinaturaService::soDigitos($cidadao->cpf) !== $convite->cpf) {
            return $this->tela('outra_conta', $base + ['logado_como' => $cidadao->nome]);
        }
        if (! $cidadao->email_verificado_em) {
            return $this->tela('confirmar_email', $base + ['email' => $this->mascararEmail($cidadao->email)]);
        }

        $itens = $this->assinaturasDoPacote($convite)->map(function (Assinatura $a) {
            $situacao = match (true) {
                $a->status === 'assinado'                                                     => 'assinado',
                $a->status === 'recusado'                                                     => 'recusado',
                in_array($a->solicitacao?->status, ['cancelada', 'recusada'], true)
                    || $a->documento?->status === 'cancelado'                                 => 'encerrado',
                $this->convites->expirado($a)                                                 => 'expirado',
                ! $this->fluxo->ehAVez($a)                                                    => 'aguardando_vez',
                default                                                                       => 'pendente',
            };

            return [
                'id'           => $a->id,
                'documento'    => $a->documento?->nome,
                'situacao'     => $situacao,
                'link'         => $situacao === 'pendente' ? $a->linkConvite() . '?pacote=1' : null,
                'codigo'       => $a->codigo_verificacao,
                'assinado_em'  => CarimboAssinaturaService::horaBrasilia($a->assinado_em) ?: null,
                'posicao_sistema' => ! empty($a->signature_position),
            ];
        })->values();

        return $this->tela('pacote', $base + ['itens' => $itens]);
    }

    /**
     * "Assinar todos": um carimbo por documento no mesmo lugar (canto da última página,
     * ou o quadro definido pelo sistema de origem), confirmado com UMA senha depois de a
     * pessoa declarar que conferiu os documentos. Cada um usa o mesmo núcleo (executar).
     */
    public function assinarTodos(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'senha'            => ['required', 'string'],
            'conferido'        => ['accepted'],
            'canto'            => ['required', 'in:direita,esquerda,centro'],
            'assinatura_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'assinatura_ids.*' => ['integer'],
            'geolocalizacao'   => ['nullable', 'string', 'max:255'],
        ], ['conferido.accepted' => 'Confirme que conferiu os documentos.']);

        $convite = $this->pacotes->porToken($token);
        abort_unless($convite, 404);
        $cidadao = $this->cidadao();
        abort_unless($cidadao && $cidadao->email_verificado_em
            && ConviteAssinaturaService::soDigitos($cidadao->cpf) === $convite->cpf, 403);

        if (! Hash::check((string) $request->input('senha'), $cidadao->senha)) {
            return back()->withErrors(['senha' => 'Senha incorreta.']);
        }

        @set_time_limit(600);
        $assinadas = 0;
        $falhas = [];
        foreach ($this->assinaturasDoPacote($convite)->whereIn('id', (array) $request->input('assinatura_ids')) as $a) {
            if ($a->status !== 'pendente') {
                continue;
            }
            try {
                $posicao = $this->posicaoAutomatica($a, (string) $request->input('canto'));
                $erro = $this->executar($a, $cidadao, $posicao, $request);
            } catch (Throwable $e) {
                Log::warning('Assinar todos: documento não assinado', ['assinatura_id' => $a->id, 'erro' => $e->getMessage()]);
                $erro = 'não foi possível ler o PDF';
            }
            $erro ? $falhas[] = "{$a->documento?->nome}: {$erro}" : $assinadas++;
        }

        $resposta = redirect($this->base($request, $token));
        if ($assinadas) {
            $resposta = $resposta->with('success', "{$assinadas} documento(s) assinado(s).");
        }
        if ($falhas) {
            $resposta = $resposta->with('error', count($falhas) . ' não assinado(s): ' . implode(' | ', array_slice($falhas, 0, 5)));
        }

        return $resposta;
    }

    /** @return \Illuminate\Support\Collection<int, Assinatura> */
    private function assinaturasDoPacote(\App\Models\PacoteConvite $convite)
    {
        // Página pública: os escopos de UG/sigilo do usuário interno logado não se aplicam.
        return Assinatura::with([
            'documento' => fn ($q) => $q->withoutGlobalScopes(['ug', 'sigilo']),
            'documento.versaoAtual',
            'solicitacao',
            'pacoteConvite',
        ])
            ->where('pacote_convite_id', $convite->id)
            ->orderBy('id')
            ->get()
            ->each(fn (Assinatura $a) => $a->solicitacao?->setRelation('documento', $a->documento));
    }

    /**
     * Posição do carimbo no "Assinar todos": o quadro que o sistema de origem definiu (ex.:
     * campo do contratado no empenho) ou o canto escolhido na última página — subindo
     * enquanto houver outro carimbo no lugar.
     */
    private function posicaoAutomatica(Assinatura $a, string $canto): array
    {
        $paginas = $this->carimbo->tamanhoPaginas(Storage::disk('documentos')->path($a->documento->versaoAtual->arquivo_path));
        $total = count($paginas);

        if (isset($a->signature_position['x'], $a->signature_position['y'])) {
            $p = $a->signature_position;
            $pagina = (int) ($p['page'] ?? -1);

            return [
                'page'    => $pagina < 1 || $pagina > $total ? $total : $pagina,
                'x'       => (float) $p['x'],
                'y'       => (float) $p['y'],
                'w'       => (float) ($p['w'] ?? CarimboAssinaturaService::LARGURA_PADRAO),
                'h'       => (float) ($p['h'] ?? CarimboAssinaturaService::ALTURA_PADRAO),
                'rotacao' => 0,
            ];
        }

        ['w' => $larguraPagina, 'h' => $alturaPagina] = $paginas[$total - 1];
        $w = CarimboAssinaturaService::LARGURA_PADRAO;
        $h = CarimboAssinaturaService::ALTURA_PADRAO;
        $x = match ($canto) {
            'esquerda' => 15.0,
            'centro'   => ($larguraPagina - $w) / 2,
            default    => $larguraPagina - $w - 15,
        };
        $y = $alturaPagina - $h - 18;

        // Carimbos já na última página — sobe até achar espaço livre.
        $ocupados = Assinatura::where('documento_id', $a->documento_id)->where('status', 'assinado')
            ->whereNotNull('signature_position')->get()
            ->map(fn ($o) => $o->signature_position)
            ->filter(fn ($p) => isset($p['x'], $p['y']) && (((int) ($p['page'] ?? -1)) === $total || (int) ($p['page'] ?? -1) === -1));
        $colide = fn (float $yy) => $ocupados->contains(fn ($p) => $x < $p['x'] + ($p['w'] ?? $w) && $x + $w > $p['x']
            && $yy < $p['y'] + ($p['h'] ?? $h) && $yy + $h > $p['y']);
        while ($colide($y) && $y > 20) {
            $y -= $h + 3;
        }

        return ['page' => $total, 'x' => round($x, 2), 'y' => round(max(10, $y), 2), 'w' => $w, 'h' => $h, 'rotacao' => 0];
    }

    // ── apoio ───────────────────────────────────────────────────────────────

    /** Página de volta: a do pacote (rotas assinar/pacote/*) ou a do documento. */
    private function base(Request $request, string $token): string
    {
        return $request->route('pacote') ? "/assinar/pacote/{$token}" : "/assinar/{$token}";
    }

    /** CPF (só dígitos) do convite do link — do documento ou do pacote. */
    private function cpfDoConvite(Request $request, string $token): ?string
    {
        $cpf = $request->route('pacote')
            ? $this->pacotes->porToken($token)?->cpf
            : $this->convites->porToken($token)?->cpf_signatario;

        return $cpf ? ConviteAssinaturaService::soDigitos($cpf) : null;
    }

    private function tela(string $estado, array $props = []): Response
    {
        return Inertia::render('AssinaturaExterna/Assinar', [
            'estado'        => $estado,
            'exige_captcha' => \App\Support\Captcha::exigidoNaEntrada(request(), 'assinatura-entrar'),
        ] + $props);
    }

    private function cidadao(): ?Cidadao
    {
        $c = Auth::guard('cidadao')->user();

        return $c instanceof Cidadao && $c->ativo ? $c : null;
    }

    private function cidadaoPorCpf(string $cpf): ?Cidadao
    {
        // cpf pode estar gravado com máscara em cadastros antigos do portal.
        return Cidadao::whereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?", [$cpf])->first();
    }

    private function ehDoConvite(?Cidadao $cidadao, Assinatura $assinatura): bool
    {
        return $cidadao !== null
            && ConviteAssinaturaService::soDigitos($cidadao->cpf) === ConviteAssinaturaService::soDigitos($assinatura->cpf_signatario);
    }

    /** Convite válido, pendente e do cidadão logado (com e-mail confirmado) — senão 403/404. */
    private function autorizada(string $token): Assinatura
    {
        $assinatura = $this->convites->porToken($token);
        abort_unless($assinatura, 404);
        $cidadao = $this->cidadao();
        abort_unless($this->ehDoConvite($cidadao, $assinatura) && $cidadao->email_verificado_em, 403);
        abort_if($this->convites->expirado($assinatura) && $assinatura->status === 'pendente', 403, 'Convite vencido.');

        return $assinatura;
    }

    /** Arquivo assinado mais recente do documento; sem nenhum, a versão atual. */
    private function arquivoOficial(Assinatura $assinatura): string
    {
        $disk = Storage::disk('documentos');
        $assinada = Assinatura::where('documento_id', $assinatura->documento_id)
            ->where('status', 'assinado')
            ->whereNotNull('arquivo_assinado_path')
            ->orderByDesc('assinado_em')
            ->first();

        if ($assinada && $disk->exists($assinada->arquivo_assinado_path)) {
            return $assinada->arquivo_assinado_path;
        }

        $caminho = $assinatura->documento?->versaoAtual?->arquivo_path;
        abort_unless($caminho && $disk->exists($caminho), 404);

        return $caminho;
    }

    private function dadosConvite(Assinatura $a): array
    {
        $documento = $a->documento;

        return [
            'nome'        => $a->nome_signatario,
            'cpf'         => ConviteAssinaturaService::mascararCpf($a->cpf_signatario),
            'documento'   => $documento?->nome,
            'orgao'       => Ug::withoutGlobalScopes()->find($documento?->ug_id)?->nome,
            'solicitante' => $a->solicitacao?->solicitante?->name,
            'mensagem'    => $a->solicitacao?->mensagem,
            'expira_em'   => $a->convite_expira_em?->format('d/m/Y'),
        ];
    }

    private function enviarCodigo(Cidadao $cidadao): void
    {
        $codigo = (string) random_int(100000, 999999);
        $cidadao->forceFill([
            'token_verificacao'           => hash('sha256', $codigo),
            'token_verificacao_expira_em' => now()->addMinutes(self::MINUTOS_CODIGO),
        ])->save();

        try {
            Mail::to($cidadao->email)->send(new CodigoConfirmacaoEmail($cidadao->nome, $codigo, self::MINUTOS_CODIGO));
        } catch (Throwable $e) {
            Log::warning('Código de confirmação não enviado', ['cidadao_id' => $cidadao->id, 'erro' => $e->getMessage()]);
        }
    }

    /** Código do carimbo: 12 caracteres sem ambíguos (0/O, 1/I), em 3 grupos. */
    private function novoCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $c = '';
            for ($i = 0; $i < 12; $i++) {
                $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $codigo = implode('-', str_split($c, 4));
        } while (Assinatura::where('codigo_verificacao', $codigo)->exists());

        return $codigo;
    }

    private function mascararEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return (string) $email;
        }
        [$u, $d] = explode('@', $email, 2);

        return mb_substr($u, 0, 2) . str_repeat('*', max(1, mb_strlen($u) - 2)) . '@' . $d;
    }
}
