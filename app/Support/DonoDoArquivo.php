<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Arquivo gravado por processo que roda como root (comando de console, worker da fila)
 * fica ilegível para o PHP da web. Devolve o arquivo e as pastas acima dele, até a raiz
 * do storage, ao dono do storage. Fora do root não faz nada.
 */
final class DonoDoArquivo
{
    public static function ajustar(string $arquivo): void
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0 || ! file_exists($arquivo)) {
            return;
        }
        $raiz = realpath(storage_path('app')) ?: storage_path('app');
        $dono = fileowner($raiz);
        $grupo = filegroup($raiz);

        $atual = $arquivo;
        while ($atual && str_starts_with($atual, $raiz) && $atual !== $raiz) {
            @chown($atual, $dono);
            @chgrp($atual, $grupo);
            $atual = dirname($atual);
        }
    }
}
