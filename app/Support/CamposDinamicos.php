<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Regras de validação de campos configurados pela Administração — metadados do tipo
 * documental e formulário de abertura do tipo de processo. Antes a obrigatoriedade era
 * só um asterisco na tela e o servidor aceitava qualquer valor.
 *
 * Esquema: [{campo, tipo: text|number|money|date|textarea|select, label, obrigatorio, opcoes: "a,b,c"}]
 */
final class CamposDinamicos
{
    /**
     * @param array<int, array<string, mixed>>|null $schema
     * @return array{0: array<string, array<int, string>>, 1: array<string, string>} regras e nomes amigáveis
     */
    public static function regras(?array $schema, string $prefixo): array
    {
        $regras = [];
        $nomes = [];

        foreach ($schema ?? [] as $campo) {
            $chave = trim((string) ($campo['campo'] ?? ''));
            if ($chave === '') {
                continue;
            }

            $r = [! empty($campo['obrigatorio']) ? 'required' : 'nullable'];
            $r[] = match ($campo['tipo'] ?? 'text') {
                'number', 'money' => 'numeric',
                'date'            => 'date',
                default           => 'string',
            };
            if (($campo['tipo'] ?? '') === 'select' && ! empty($campo['opcoes'])) {
                $opcoes = array_filter(array_map('trim', explode(',', (string) $campo['opcoes'])), 'strlen');
                if ($opcoes) {
                    $r[] = 'in:' . implode(',', array_map(fn ($o) => str_replace(',', '', $o), $opcoes));
                }
            }

            $regras["{$prefixo}.{$chave}"] = $r;
            $nomes["{$prefixo}.{$chave}"] = (string) ($campo['label'] ?? $chave);
        }

        return [$regras, $nomes];
    }
}
