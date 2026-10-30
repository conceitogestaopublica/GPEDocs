<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Portal\CidadaoPortalService;

/**
 * Regras de validação de campos configurados pela Administração — metadados do tipo
 * documental e formulário de abertura do tipo de processo. Antes a obrigatoriedade era
 * só um asterisco na tela e o servidor aceitava qualquer valor.
 *
 * Esquema: [{campo, tipo, label, obrigatorio, opcoes: "a,b,c", formula, ajuda, ajuda_imagem}]
 * tipo: text | number | money | date | textarea | select | cpf_cnpj | cep | email | telefone
 *       | checkbox | calculo (TR 4.38.3–4.38.9)
 */
final class CamposDinamicos
{
    /** Partes do endereço do campo CEP (o mesmo formato do EnderecoForm). */
    private const ENDERECO = ['logradouro' => 200, 'numero' => 20, 'complemento' => 100, 'bairro' => 100, 'cidade' => 100, 'uf' => 2];

    /**
     * @param array<int, array<string, mixed>>|null $schema
     * @return array{0: array<string, array<int, mixed>>, 1: array<string, string>} regras e nomes amigáveis
     */
    public static function regras(?array $schema, string $prefixo): array
    {
        $regras = [];
        $nomes = [];

        foreach ($schema ?? [] as $campo) {
            $chave = trim((string) ($campo['campo'] ?? ''));
            $tipo = $campo['tipo'] ?? 'text';
            if ($chave === '' || $tipo === 'calculo') {
                continue;   // cálculo é feito no servidor (normalizar)
            }
            $nome = "{$prefixo}.{$chave}";
            $obrigatorio = ! empty($campo['obrigatorio']);
            $nomes[$nome] = (string) ($campo['label'] ?? $chave);

            if ($tipo === 'cep') {
                $regras[$nome] = [$obrigatorio ? 'required' : 'nullable', 'array'];
                $regras["{$nome}.cep"] = [$obrigatorio ? 'required' : 'nullable', 'string', 'regex:/^\d{5}-?\d{3}$/'];
                foreach (self::ENDERECO as $parte => $max) {
                    $regras["{$nome}.{$parte}"] = ['nullable', 'string', "max:{$max}"];
                }
                $nomes["{$nome}.cep"] = "CEP de {$nomes[$nome]}";
                continue;
            }

            $r = [$obrigatorio ? ($tipo === 'checkbox' ? 'accepted' : 'required') : 'nullable'];
            $r = [...$r, ...match ($tipo) {
                'number', 'money' => ['numeric'],
                'date'            => ['date'],
                'email'           => ['email', 'max:150'],
                'telefone'        => ['string', 'regex:/^\(?\d{2}\)?\s?\d{4,5}-?\d{4}$/'],
                'checkbox'        => ['boolean'],
                'cpf_cnpj'        => ['string', function ($atributo, $valor, $falha) use ($nomes, $nome) {
                    if ($valor !== null && $valor !== '' && ! CidadaoPortalService::documentoValido((string) $valor)) {
                        $falha("{$nomes[$nome]}: CPF ou CNPJ inválido.");
                    }
                }],
                default           => ['string'],
            }];
            if ($tipo === 'select' && ! empty($campo['opcoes'])) {
                $opcoes = array_filter(array_map('trim', explode(',', (string) $campo['opcoes'])), 'strlen');
                if ($opcoes) {
                    $r[] = 'in:' . implode(',', array_map(fn ($o) => str_replace(',', '', $o), $opcoes));
                }
            }

            $regras[$nome] = $r;
        }

        return [$regras, $nomes];
    }

    /**
     * Depois de validar: calcula os campos de cálculo, guarda CPF/CNPJ e CEP só com
     * dígitos e a caixa de marcar como verdadeiro/falso. O que não está no esquema sai.
     *
     * @param array<int, array<string, mixed>>|null $schema
     * @param array<string, mixed>|null $dados
     * @return array<string, mixed>|null
     */
    public static function normalizar(?array $schema, ?array $dados): ?array
    {
        if (! $schema) {
            return $dados;
        }
        $dados ??= [];
        $saida = [];
        foreach ($schema as $campo) {
            $chave = trim((string) ($campo['campo'] ?? ''));
            if ($chave === '' || ($campo['tipo'] ?? '') === 'calculo') {
                continue;
            }
            $v = $dados[$chave] ?? null;
            $saida[$chave] = match ($campo['tipo'] ?? 'text') {
                'cpf_cnpj' => $v ? DocumentoPessoa::soDigitos((string) $v) : null,
                'checkbox' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                'cep'      => is_array($v) && array_filter($v) ? ['cep' => DocumentoPessoa::soDigitos($v['cep'] ?? '')]
                    + array_map(fn ($p) => isset($v[$p]) ? trim((string) $v[$p]) : null, array_combine(array_keys(self::ENDERECO), array_keys(self::ENDERECO)))
                    : null,
                default    => $v,
            };
        }
        // Cálculos por último: usam os valores já normalizados
        foreach ($schema as $campo) {
            if (($campo['tipo'] ?? '') === 'calculo' && ($chave = trim((string) ($campo['campo'] ?? ''))) !== '') {
                $saida[$chave] = Formula::avaliar((string) ($campo['formula'] ?? ''), $saida);
            }
        }

        return $saida;
    }
}
