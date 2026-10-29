<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Mail\CodigoConfirmacaoEmail;
use App\Models\Portal\Cidadao;
use App\Models\User;
use App\Services\ConviteAssinaturaService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Cidadão do Portal (`portal_cidadaos`) como cadastro ÚNICO da pessoa: o
 * Portal do Cidadão (Next.js) cadastra, autentica e redefine a senha por aqui,
 * e o mesmo cadastro serve à assinatura por link. Uma pessoa, uma senha.
 *
 * O documento (CPF ou CNPJ) é a chave: gravado só com dígitos, conferido com
 * dígito verificador e procurado ignorando máscara (cadastros antigos têm).
 *
 * Quem é usuário do GPE Docs (servidor, funcionário) entra no portal com a senha de
 * usuário: no primeiro acesso nasce o cidadão ligado a ele (user_id), e daí em diante a
 * senha conferida é sempre a do usuário.
 */
class CidadaoPortalService
{
    /** Validade do código enviado por e-mail (mesma da assinatura por link). */
    public const MINUTOS_CODIGO = 15;

    public static function digitos(?string $valor): string
    {
        return ConviteAssinaturaService::soDigitos($valor);
    }

    /** CPF ou CNPJ com dígitos verificadores válidos. */
    public static function documentoValido(string $documento): bool
    {
        $d = self::digitos($documento);

        return strlen($d) === 11
            ? ConviteAssinaturaService::cpfValido($d)
            : self::cnpjValido($d);
    }

    public static function cnpjValido(string $cnpj): bool
    {
        $d = self::digitos($cnpj);
        if (strlen($d) !== 14 || preg_match('/^(\d)\1{13}$/', $d)) {
            return false;
        }
        foreach ([12, 13] as $t) {
            $pesos = $t === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $d[$i] * $pesos[$i];
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $d[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public function porDocumento(string $documento): ?Cidadao
    {
        return Cidadao::whereRaw(
            "regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?",
            [self::digitos($documento)],
        )->first();
    }

    /**
     * Cria o cadastro (ainda sem e-mail confirmado) e envia o código. Um
     * cadastro do mesmo documento que nunca confirmou o e-mail é retomado —
     * quem desistiu no meio não fica trancado para sempre.
     *
     * @param  array{documento:string,nome:string,email:string,telefone?:?string,senha:string}  $dados
     * @return array{cidadao: Cidadao, codigo: string}
     */
    public function cadastrar(array $dados): array
    {
        $documento = self::digitos($dados['documento']);
        if (! self::documentoValido($documento)) {
            throw new CidadaoPortalException('CPF ou CNPJ inválido.');
        }
        $email = mb_strtolower(trim($dados['email']));

        $existente = $this->porDocumento($documento);
        if ($existente && $existente->email_verificado_em) {
            throw new CidadaoPortalException('Já existe cadastro com este CPF/CNPJ. Entre com a sua senha.', 409);
        }

        $emailEmUso = Cidadao::where('email', $email)
            ->when($existente, fn ($q) => $q->where('id', '<>', $existente->id))
            ->exists();
        if ($emailEmUso) {
            throw new CidadaoPortalException('Este e-mail já está em outro cadastro.');
        }

        $atributos = [
            'nome'             => trim($dados['nome']),
            'email'            => $email,
            'cpf'              => $documento,
            'telefone'         => $dados['telefone'] ?? null,
            'senha'            => Hash::make($dados['senha']),
            'ativo'            => true,
            'nomes_anteriores' => self::limparNomes($dados['nomes_anteriores'] ?? []) ?: null,
            'endereco'         => self::limparEndereco($dados['endereco'] ?? null),
        ];
        $cidadao = $existente
            ? tap($existente)->update($atributos)
            : Cidadao::create($atributos);

        return ['cidadao' => $cidadao, 'codigo' => $this->enviarCodigo($cidadao)];
    }

    /** Gera, guarda (hash) e envia por e-mail um código de 6 dígitos; devolve o código. */
    public function enviarCodigo(Cidadao $cidadao): string
    {
        $codigo = (string) random_int(100000, 999999);
        $cidadao->forceFill([
            'token_verificacao'           => hash('sha256', $codigo),
            'token_verificacao_expira_em' => now()->addMinutes(self::MINUTOS_CODIGO),
        ])->save();

        try {
            Mail::to($cidadao->email)->send(new CodigoConfirmacaoEmail($cidadao->nome, $codigo, self::MINUTOS_CODIGO));
        } catch (Throwable $e) {
            Log::warning('Código do portal não enviado', ['cidadao_id' => $cidadao->id, 'erro' => $e->getMessage()]);
        }

        return $codigo;
    }

    /** Confere o código (uma vez só) — consome o código se estiver certo. */
    public function consumirCodigo(Cidadao $cidadao, string $codigo): bool
    {
        $valido = $cidadao->token_verificacao
            && $cidadao->token_verificacao_expira_em?->isFuture()
            && hash_equals($cidadao->token_verificacao, hash('sha256', trim($codigo)));
        if (! $valido) {
            return false;
        }
        $cidadao->forceFill([
            'token_verificacao'           => null,
            'token_verificacao_expira_em' => null,
        ])->save();

        return true;
    }

    public function confirmarEmail(Cidadao $cidadao, string $codigo): bool
    {
        if (! $this->consumirCodigo($cidadao, $codigo)) {
            return false;
        }
        $cidadao->forceFill(['email_verificado_em' => now()])->save();

        return true;
    }

    /** Cidadão pelo CPF/CNPJ ou pelo e-mail (único no cadastro) — o que tiver "@" é e-mail. */
    public function porLogin(string $login): ?Cidadao
    {
        return str_contains($login, '@')
            ? Cidadao::whereRaw('lower(email) = ?', [mb_strtolower(trim($login))])->first()
            : $this->porDocumento($login);
    }

    /** Usuário do GPE Docs pelo CPF ou e-mail — só com CPF (é a chave do cidadão). */
    public function usuarioPorLogin(string $login): ?User
    {
        return User::query()
            ->whereNotNull('cpf')
            ->when(str_contains($login, '@'),
                fn ($q) => $q->whereRaw('lower(email) = ?', [mb_strtolower(trim($login))]),
                fn ($q) => $q->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [self::digitos($login)]))
            ->orderByRaw('legado_funcionario_id is null')   // CPF repetido: prefere o do servidor
            ->orderBy('id')
            ->first();
    }

    /** Tem acesso ao portal? Cidadão ativo, ou usuário do GPE Docs (entra com a senha de lá). */
    public function temAcesso(string $documento): bool
    {
        $cidadao = $this->porDocumento($documento);

        return $cidadao ? (bool) $cidadao->ativo : $this->usuarioPorLogin($documento) !== null;
    }

    /**
     * CPF/CNPJ ou e-mail + senha. Null para qualquer falha (inexistente, senha errada,
     * cadastro inativo) — quem chama não deve distinguir.
     */
    public function autenticar(string $login, string $senha): ?Cidadao
    {
        $cidadao = $this->porLogin($login);
        if ($cidadao) {
            $hash = $cidadao->user_id ? $cidadao->usuario?->password : $cidadao->senha;

            return $cidadao->ativo && $hash && Hash::check($senha, (string) $hash) ? $cidadao : null;
        }

        // Sem cadastro de cidadão: usuário do GPE Docs entra com a senha de usuário.
        $usuario = $this->usuarioPorLogin($login);
        if (! $usuario || ! Hash::check($senha, (string) $usuario->password)) {
            return null;
        }

        return $this->cidadaoDoUsuario($usuario);
    }

    /**
     * Quem já provou a identidade por fora (certificado ICP, gov.br, Google): acha o
     * cidadão pelo CPF/CNPJ ou e-mail, sem senha. Usuário do GPE Docs sem cadastro de
     * cidadão ganha o cadastro ligado a ele, como na senha. Pelo e-mail, só se confirmado.
     */
    public function porIdentidadeComprovada(string $login): ?Cidadao
    {
        $cidadao = $this->porLogin($login);
        if ($cidadao) {
            $porEmail = str_contains($login, '@');

            return $cidadao->ativo && (! $porEmail || $cidadao->email_verificado_em) ? $cidadao : null;
        }
        $usuario = $this->usuarioPorLogin($login);

        return $usuario ? $this->cidadaoDoUsuario($usuario) : null;
    }

    /**
     * Cidadão ligado ao usuário. O e-mail vem do usuário (cadastrado pela Prefeitura, por
     * isso já confirmado); se já for de outro cidadão, fica um provisório.
     */
    private function cidadaoDoUsuario(User $usuario): Cidadao
    {
        $email = mb_strtolower((string) $usuario->email);
        if (Cidadao::whereRaw('lower(email) = ?', [$email])->exists()) {
            $email = 'usr-' . $usuario->id . '@usuario.local';
        }

        return Cidadao::create([
            'user_id'             => $usuario->id,
            'nome'                => $usuario->name,
            'email'               => $email,
            'cpf'                 => self::digitos($usuario->cpf),
            'telefone'            => $usuario->telefone,
            'senha'               => (string) $usuario->password,   // não é conferida: vale a do usuário
            'email_verificado_em' => now(),
            'ativo'               => true,
        ]);
    }

    /** Troca a senha com o código enviado ao e-mail cadastrado; confirma o e-mail de quebra. */
    public function redefinirSenha(Cidadao $cidadao, string $codigo, string $senha): bool
    {
        if (! $this->consumirCodigo($cidadao, $codigo)) {
            return false;
        }
        $hash = Hash::make($senha);
        $cidadao->forceFill([
            'senha'               => $hash,
            'email_verificado_em' => $cidadao->email_verificado_em ?? now(),
        ])->save();
        // Ligado a usuário do GPE Docs: a senha é uma só.
        $cidadao->usuario?->forceFill(['password' => $hash])->save();

        return true;
    }

    /**
     * Meus dados (TR 4.8.6). O CPF/CNPJ não muda. Trocar o nome guarda o anterior
     * (nome civil alterado — Lei 14.382/2022). O e-mail novo só vale depois do código
     * mandado para ele; até lá o cadastro segue com o antigo.
     *
     * @param  array{nome?:string,telefone?:?string,nomes_anteriores?:array,endereco?:?array,email?:string}  $dados
     * @return array{cidadao: Cidadao, codigo: ?string}
     */
    public function atualizar(Cidadao $cidadao, array $dados): array
    {
        $atributos = [];

        if (array_key_exists('nomes_anteriores', $dados)) {
            $atributos['nomes_anteriores'] = self::limparNomes($dados['nomes_anteriores'] ?? []);
        }
        $nome = isset($dados['nome']) ? trim((string) $dados['nome']) : null;
        if ($nome && $nome !== $cidadao->nome) {
            $anteriores = $atributos['nomes_anteriores'] ?? ($cidadao->nomes_anteriores ?? []);
            $atributos['nomes_anteriores'] = self::limparNomes([...$anteriores, $cidadao->nome]);
            $atributos['nome'] = $nome;
        }
        if (isset($atributos['nomes_anteriores'])) {
            $atributos['nomes_anteriores'] = $atributos['nomes_anteriores'] ?: null;
        }
        if (array_key_exists('telefone', $dados)) {
            $atributos['telefone'] = $dados['telefone'] ?: null;
        }
        if (array_key_exists('endereco', $dados)) {
            $atributos['endereco'] = self::limparEndereco($dados['endereco']);
        }

        $codigo = null;
        $email = isset($dados['email']) ? mb_strtolower(trim((string) $dados['email'])) : null;
        if ($email && $email !== mb_strtolower((string) $cidadao->email)) {
            $emUso = Cidadao::whereRaw('lower(email) = ?', [$email])->where('id', '<>', $cidadao->id)->exists();
            if ($emUso) {
                throw new CidadaoPortalException('Este e-mail já está em outro cadastro.');
            }
            $codigo = (string) random_int(100000, 999999);
            $cidadao->forceFill([
                'email_novo'           => $email,
                'email_novo_token'     => hash('sha256', $codigo),
                'email_novo_expira_em' => now()->addMinutes(self::MINUTOS_CODIGO),
            ]);
        }

        $cidadao->fill($atributos)->save();

        if ($codigo !== null) {
            try {
                Mail::to($email)->send(new CodigoConfirmacaoEmail($cidadao->nome, $codigo, self::MINUTOS_CODIGO));
            } catch (Throwable $e) {
                Log::warning('Código do e-mail novo não enviado', ['cidadao_id' => $cidadao->id, 'erro' => $e->getMessage()]);
            }
        }

        return ['cidadao' => $cidadao, 'codigo' => $codigo];
    }

    /** Confirma o e-mail novo com o código mandado a ele; só então troca. */
    public function confirmarEmailNovo(Cidadao $cidadao, string $codigo): bool
    {
        $valido = $cidadao->email_novo && $cidadao->email_novo_token
            && $cidadao->email_novo_expira_em?->isFuture()
            && hash_equals($cidadao->email_novo_token, hash('sha256', trim($codigo)));
        if (! $valido) {
            return false;
        }
        // Alguém pode ter cadastrado o mesmo e-mail enquanto o código estava no ar.
        if (Cidadao::whereRaw('lower(email) = ?', [$cidadao->email_novo])->where('id', '<>', $cidadao->id)->exists()) {
            return false;
        }
        $cidadao->forceFill([
            'email'                => $cidadao->email_novo,
            'email_verificado_em'  => now(),
            'email_novo'           => null,
            'email_novo_token'     => null,
            'email_novo_expira_em' => null,
        ])->save();

        return true;
    }

    /** @return list<string> sem vazios nem repetidos, na ordem informada */
    private static function limparNomes(array $nomes): array
    {
        $limpos = [];
        foreach ($nomes as $n) {
            $n = trim((string) $n);
            if ($n !== '' && ! in_array(mb_strtolower($n), array_map('mb_strtolower', $limpos), true)) {
                $limpos[] = mb_substr($n, 0, 200);
            }
        }

        return $limpos;
    }

    private static function limparEndereco(?array $endereco): ?array
    {
        if (! $endereco) {
            return null;
        }
        $campos = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf'];
        $limpo = [];
        foreach ($campos as $c) {
            $v = trim((string) ($endereco[$c] ?? ''));
            $limpo[$c] = $v === '' ? null : $v;
        }
        if ($limpo['cep']) {
            $limpo['cep'] = self::digitos($limpo['cep']);
        }
        if ($limpo['uf']) {
            $limpo['uf'] = mb_strtoupper($limpo['uf']);
        }

        return array_filter($limpo) ? $limpo : null;
    }

    /** E-mail para mostrar ao cidadão sem expô-lo: j***@dominio.com. */
    public static function mascararEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$usuario, $dominio] = explode('@', $email, 2);

        return mb_substr($usuario, 0, 1).'***@'.$dominio;
    }

    /** Representação para o Portal — nunca senha nem token. */
    public static function paraApi(Cidadao $c): array
    {
        return [
            'id'              => $c->id,
            'nome'            => $c->nome,
            'documento'       => self::digitos($c->cpf),
            'email'           => $c->email,
            'telefone'        => $c->telefone,
            'email_confirmado' => $c->email_verificado_em !== null,
            // Servidor municipal provado: entrou pelo usuário do GPE Docs que veio do RH
            // (o cadastro aberto do portal não prova CPF — não basta o CPF ser de servidor).
            'servidor'        => $c->user_id !== null && $c->usuario?->legado_funcionario_id !== null,
            'nomes_anteriores' => $c->nomes_anteriores ?? [],
            'endereco'        => $c->endereco,
        ];
    }
}
