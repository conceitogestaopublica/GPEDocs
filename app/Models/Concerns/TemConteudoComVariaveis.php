<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Variaveis;

/**
 * Documento redigido no editor (memorando, circular, ofício): o conteúdo é exibido e impresso
 * com as variáveis trocadas e o HTML limpo. O modelo diz o valor de cada variável em
 * `variaveis()` e os rótulos para o editor em `ROTULOS_VARIAVEIS`.
 */
trait TemConteudoComVariaveis
{
    /** @return array<string, string> */
    abstract public function variaveis(): array;

    public function conteudoFinal(): string
    {
        return Variaveis::aplicar($this->conteudo, $this->variaveis());
    }

    /** [{id, label}] para o editor da tela de criação. */
    public static function variaveisParaEditor(): array
    {
        return Variaveis::paraEditor(static::ROTULOS_VARIAVEIS);
    }
}
