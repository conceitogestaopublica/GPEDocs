<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Classificacao;

/**
 * Item que entra no plano de classificação (TR 4.27.6): ao criar sem classificação,
 * recebe a do assunto ou, na falta, a do módulo. O model define a constante
 * MODULO_CLASSIFICACAO (chave de Classificacao::MODULOS).
 */
trait Classificavel
{
    public static function bootClassificavel(): void
    {
        static::creating(function ($item) {
            if ($item->classificacao_id) {
                return;
            }
            [, $colAssunto] = Classificacao::MODULOS[static::MODULO_CLASSIFICACAO];
            $item->classificacao_id = Classificacao::paraNovo(
                static::MODULO_CLASSIFICACAO,
                $colAssunto && $item->{$colAssunto} ? (int) $item->{$colAssunto} : null,
            );
        });
    }
}
