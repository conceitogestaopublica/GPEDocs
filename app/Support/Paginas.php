<?php

declare(strict_types=1);

namespace App\Support;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Número de páginas de um arquivo anexado (TR 4.29.23, 4.29.26): PDF pelo FPDI (com a contagem de
 * /Type /Page como reserva para PDF que o FPDI não abre), imagem = 1, outros formatos = não conta.
 */
class Paginas
{
    public static function contar(string $caminho, ?string $mime): ?int
    {
        $mime = (string) $mime;
        if (str_starts_with($mime, 'image/')) {
            return 1;
        }
        if ($mime !== 'application/pdf' && ! str_ends_with(strtolower($caminho), '.pdf')) {
            return null;
        }
        try {
            return (new Fpdi())->setSourceFile($caminho);
        } catch (\Throwable) {
            $conteudo = @file_get_contents($caminho);
            $n = $conteudo ? preg_match_all('#/Type\s*/Page(?![a-z])#i', $conteudo) : 0;

            return $n > 0 ? $n : null;
        }
    }
}
