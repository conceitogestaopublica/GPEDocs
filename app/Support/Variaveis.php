<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Variáveis de conteúdo do editor (TR 4.16.10): {{numero}}, {{data}}… trocadas pelos dados do
 * documento na hora de exibir ou gerar o PDF — o texto acompanha uma correção posterior.
 * Variável desconhecida fica como está, à vista de quem revisa.
 */
final class Variaveis
{
    /** @param array<string, string> $valores chave sem acento e minúscula => valor */
    public static function aplicar(?string $html, array $valores): string
    {
        // O marcador do editor (span com fundo de variável) sai: no documento fica só o valor
        $html = preg_replace('#<span\b[^>]*data-type="variavel"[^>]*>\s*(\{\{[^<]{1,44}\}\})\s*</span>#u', '$1', (string) $html);
        $conteudo = preg_replace_callback('/\{\{\s*([^{}]{1,40}?)\s*\}\}/u', function (array $m) use ($valores) {
            $chave = Str::ascii(mb_strtolower($m[1]));

            return array_key_exists($chave, $valores) ? e($valores[$chave]) : $m[0];
        }, (string) $html);

        return HtmlSeguro::limpar($conteudo);
    }

    /** Lista para o editor: [{id, label}] a partir de [chave => rótulo]. */
    public static function paraEditor(array $rotulos): array
    {
        return collect($rotulos)->map(fn ($r, $k) => ['id' => $k, 'label' => $r])->values()->all();
    }

    /** Data por extenso no fuso da prefeitura ("29 de outubro de 2026"). */
    public static function dataPorExtenso(?\DateTimeInterface $quando): string
    {
        return \Illuminate\Support\Carbon::instance($quando ?? now())->setTimezone('America/Sao_Paulo')
            ->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
    }

    /** Texto puro do HTML, para a busca e o texto pesquisável do documento arquivado. */
    public static function textoPuro(?string $html): string
    {
        $texto = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i', "\n", (string) $html);

        return trim(html_entity_decode(strip_tags((string) $texto), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
