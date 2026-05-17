<?php

declare(strict_types=1);

namespace App\Support;

class TextoNormalizer
{
    /**
     * Sentence case PT-BR: apenas a primeira letra em maiuscula, o resto em minuscula.
     * Padrao para nomes de setores no organograma.
     */
    public static function sentenceCase(?string $s): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        if ($s === '') return $s;

        $s = mb_strtolower($s, 'UTF-8');

        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8')
             . mb_substr($s, 1, null, 'UTF-8');
    }
}
