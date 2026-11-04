<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Support\DocumentoPessoa;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF ou CNPJ de uma conta: 11 ou 14 dígitos, com ou sem máscara, e que não esteja em outra conta.
 * Compara só os dígitos, porque os documentos antigos estão gravados dos dois jeitos.
 */
final class DocumentoDeUsuario implements ValidationRule
{
    /** @param int|null $contaId conta em edição: o próprio documento não conta como repetido */
    public function __construct(private readonly ?int $contaId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitos = DocumentoPessoa::soDigitos(is_string($value) ? $value : '');

        if (! in_array(strlen($digitos), [11, 14], true)) {
            $fail('Informe um CPF com 11 dígitos ou um CNPJ com 14 dígitos.');

            return;
        }

        if (User::documentoEmOutraConta($digitos, $this->contaId)) {
            $fail('Este CPF/CNPJ já está cadastrado em outra conta.');
        }
    }
}
