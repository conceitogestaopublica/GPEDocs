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

        $base = ['convite' => $this->dadosConvite($assinatura), 'token' => $token];
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

        $login = trim((string) $request->input('login'));
        $cpf = ConviteAssinaturaService::soDigitos($login);
        $cidadao = str_contains($login, '@')
            ? Cidadao::where('email', mb_strtolower($login))->first()
            : (strlen($cpf) === 11 ? $this->cidadaoPorCpf($cpf) : null);

        if (! $cidadao || ! $cidadao->ativo || ! Hash::check((string) $request->input('senha'), $cidadao->senha)) {
            return back()->withErrors(['login' => 'CPF ou senha incorretos.'])->onlyInput('login');
        }

        Auth::guard('cidadao')->login($cidadao);
        $request->session()->regenerate();

        return redirect("/assinar/{$token}");
    }

    /** Cadastro rápido — o CPF tem de ser o do convite; depois confirma o e-mail. */
    public function cadastrar(Request $request, string $token): RedirectResponse
    {
        $assinatura = $this->convites->porToken($token);
        abort_unless($assinatura, 404);

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

        $cpf = ConviteAssinaturaService::soDigitos($dados['cpf']);
        if (! ConviteAssinaturaService::cpfValido($cpf)) {
            return back()->withErrors(['cpf' => 'CPF inválido.'])->withInput($request->except('senha', 'senha_confirmation'));
        }
        if ($cpf !== ConviteAssinaturaService::soDigitos($assinatura->cpf_signatario)) {
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

        return redirect("/assinar/{$token}")->with('success', 'Cadastro criado. Enviamos um código para o seu e-mail.');
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

        return redirect("/assinar/{$token}")->with('success', 'E-mail confirmado.');
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

        return redirect("/assinar/{$token}");
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
        if ($assinatura->status !== 'pendente') {
            return back()->with('error', 'Esta assinatura já foi processada.');
        }
        if ($impedimento = $this->fluxo->impedimento($assinatura)) {
            return back()->with('error', $impedimento);
        }

        $versao = $assinatura->documento->versaoAtual;
        $paginas = $this->carimbo->tamanhoPaginas(Storage::disk('documentos')->path($versao->arquivo_path));
        $pagina = (int) $request->input('posicao.pagina');
        if ($pagina > count($paginas)) {
            return back()->with('error', 'Página inválida.');
        }
        $posicao = CarimboAssinaturaService::posicaoDaTela(
            $request->input('posicao'), $paginas[$pagina - 1]['w'], $paginas[$pagina - 1]['h']
        );

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
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Falha na assinatura externa', ['assinatura_id' => $assinatura->id, 'erro' => $e->getMessage(),
                'arquivo' => $e->getFile() . ':' . $e->getLine()]);

            return back()->with('error', 'Não foi possível assinar o documento agora. Tente de novo em instantes.');
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

        return redirect("/assinar/{$token}")->with('success', 'Documento assinado com sucesso.');
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

        return redirect("/assinar/{$token}");
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

    // ── apoio ───────────────────────────────────────────────────────────────

    private function tela(string $estado, array $props = []): Response
    {
        return Inertia::render('AssinaturaExterna/Assinar', ['estado' => $estado] + $props);
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
