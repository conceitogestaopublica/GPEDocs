<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Mail\CodigoConfirmacaoEmail;
use App\Models\Portal\Cidadao;
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
            'nome'     => trim($dados['nome']),
            'email'    => $email,
            'cpf'      => $documento,
            'telefone' => $dados['telefone'] ?? null,
            'senha'    => Hash::make($dados['senha']),
            'ativo'    => true,
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

    /**
     * Documento + senha. Null para qualquer falha (documento inexistente,
     * senha errada, cadastro inativo) — quem chama não deve distinguir.
     */
    public function autenticar(string $documento, string $senha): ?Cidadao
    {
        $cidadao = $this->porDocumento($documento);
        if (! $cidadao || ! $cidadao->ativo || ! $cidadao->senha || ! Hash::check($senha, $cidadao->senha)) {
            return null;
        }

        return $cidadao;
    }

    /** Troca a senha com o código enviado ao e-mail cadastrado; confirma o e-mail de quebra. */
    public function redefinirSenha(Cidadao $cidadao, string $codigo, string $senha): bool
    {
        if (! $this->consumirCodigo($cidadao, $codigo)) {
            return false;
        }
        $cidadao->forceFill([
            'senha'               => Hash::make($senha),
            'email_verificado_em' => $cidadao->email_verificado_em ?? now(),
        ])->save();

        return true;
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
        ];
    }
}
