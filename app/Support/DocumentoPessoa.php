<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CPF e CNPJ de pessoas para exibição (LGPD).
 *
 * Fora do cadastro da própria pessoa, o documento aparece só em parte: CPF 123.***.***-09 e
 * CNPJ 12.***.***\/****-90. A máscara é aplicada no servidor, antes de o dado ir para a tela —
 * mascarar só no front deixaria o número completo no código da página.
 */
final class DocumentoPessoa
{
    public static function soDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    public static function mascararCpf(?string $cpf): ?string
    {
        $d = self::soDigitos($cpf);
        if ($d === '') {
            return $cpf === null ? null : '';
        }

        return strlen($d) === 11 ? substr($d, 0, 3) . '.***.***-' . substr($d, 9, 2) : '***';
    }

    public static function mascararCnpj(?string $cnpj): ?string
    {
        $d = self::soDigitos($cnpj);
        if ($d === '') {
            return $cnpj === null ? null : '';
        }

        return strlen($d) === 14 ? substr($d, 0, 2) . '.***.***/****-' . substr($d, 12, 2) : '***';
    }

    /** CPF ou CNPJ pelo tamanho; qualquer outra coisa vira "***" (nunca devolve o valor cru). */
    public static function mascarar(?string $documento): ?string
    {
        $d = self::soDigitos($documento);

        return strlen($d) === 14 ? self::mascararCnpj($d) : self::mascararCpf($documento);
    }
}
