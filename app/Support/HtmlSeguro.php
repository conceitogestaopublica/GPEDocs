<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpa o HTML do editor rico (RichEditor) antes de exibi-lo na tela ou no PDF.
 *
 * Fica só o que o editor produz: parágrafos, títulos, negrito/itálico/sublinhado, listas, alinhamento,
 * cor, tamanho, linha horizontal e links http/https/mailto. Script, iframe, eventos (onclick…),
 * javascript: e estilos fora da lista somem — o conteúdo é digitado por usuários e vai para o PDF oficial
 * e para a tela de outros servidores.
 *
 * Texto sem nenhuma tag (ofícios antigos, gravados antes do editor) é tratado como texto puro: escapado,
 * com as quebras de linha preservadas.
 */
final class HtmlSeguro
{
    private const TAGS = [
        'p', 'br', 'div', 'span', 'font', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'hr', 'a',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /** Elementos removidos junto com o conteúdo (o resto das tags proibidas some, mas o texto fica). */
    private const DESCARTAR = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'head', 'title', 'meta', 'link', 'form', 'input', 'button', 'textarea', 'select'];

    private const ESTILOS = ['text-align', 'color', 'background-color', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'margin-left', 'padding-left'];

    public static function limpar(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        // É HTML só se houver uma tag que o editor produz; "<x>" ou "a < b" num texto antigo continuam texto.
        if (! preg_match('#</?(' . implode('|', self::TAGS) . ')\b[^>]*>#i', $html)) {
            return nl2br(e($html), false);
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        // O prefixo de encoding faz o libxml ler UTF-8 (sem ele, acentos viram lixo).
        $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__raiz">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $doc->getElementById('__raiz');
        if (! $raiz) {
            return nl2br(e(strip_tags($html)), false);
        }

        self::limparFilhos($raiz);

        $saida = '';
        foreach (iterator_to_array($raiz->childNodes) as $filho) {
            $saida .= $doc->saveHTML($filho);
        }

        return $saida;
    }

    private static function limparFilhos(DOMNode $no): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho->nodeType === XML_COMMENT_NODE || $filho->nodeType === XML_PI_NODE) {
                $no->removeChild($filho);
                continue;
            }
            if (! $filho instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($filho->tagName);

            if (in_array($tag, self::DESCARTAR, true)) {
                $no->removeChild($filho);
                continue;
            }

            self::limparFilhos($filho);

            if (! in_array($tag, self::TAGS, true)) {
                // Tag desconhecida: some a tag, fica o conteúdo.
                while ($filho->firstChild) {
                    $no->insertBefore($filho->firstChild, $filho);
                }
                $no->removeChild($filho);
                continue;
            }

            self::limparAtributos($filho, $tag);
        }
    }

    private static function limparAtributos(DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $nome = strtolower($attr->nodeName);
            $valor = trim($attr->nodeValue ?? '');
            $manter = match (true) {
                $nome === 'style' => (bool) ($valor = self::estiloPermitido($valor)),
                $nome === 'href' && $tag === 'a' => (bool) preg_match('#^(https?://|mailto:)#i', $valor),
                $nome === 'color' && $tag === 'font' => (bool) preg_match('/^#?[0-9a-z]{1,20}$/i', $valor),
                $nome === 'size' && $tag === 'font' => (bool) preg_match('/^[1-7]$/', $valor),
                $nome === 'align' => in_array(strtolower($valor), ['left', 'right', 'center', 'justify'], true),
                in_array($nome, ['colspan', 'rowspan'], true) => ctype_digit($valor),
                default => false,
            };
            if ($manter) {
                $el->setAttribute($nome, $valor);
            } else {
                $el->removeAttribute($attr->nodeName);
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /** Mantém só propriedades da lista e valores sem url(), expression() ou javascript. */
    private static function estiloPermitido(string $estilo): string
    {
        $ok = [];
        foreach (explode(';', $estilo) as $decl) {
            [$prop, $val] = array_pad(explode(':', $decl, 2), 2, '');
            $prop = strtolower(trim($prop));
            $val = trim($val);
            if ($val === '' || ! in_array($prop, self::ESTILOS, true)) {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript|[<>\\\\]/i', $val)) {
                continue;
            }
            $ok[] = "{$prop}: {$val}";
        }

        return implode('; ', $ok);
    }
}
