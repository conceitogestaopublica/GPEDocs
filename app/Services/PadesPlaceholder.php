<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Resumo dos bytes cobertos pela assinatura e gravação do CMS no /Contents
 * reservado (por PadesIncremental), sem mexer em nenhum outro byte.
 */
final class PadesPlaceholder
{
    /** @return array{0: int, 1: int, 2: int, 3: int} */
    public static function byteRange(string $pdf): array
    {
        // O último /ByteRange é o da assinatura que está sendo feita
        if (! preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $m, PREG_SET_ORDER)) {
            throw new RuntimeException('PDF sem /ByteRange.');
        }
        $u = end($m);
        return [(int) $u[1], (int) $u[2], (int) $u[3], (int) $u[4]];
    }

    /** SHA-256 binário dos bytes cobertos pela assinatura. */
    public static function digest(string $pdf, array $byteRange): string
    {
        return hash('sha256', substr($pdf, $byteRange[0], $byteRange[1]) . substr($pdf, $byteRange[2], $byteRange[3]), true);
    }

    /** Grava o CMS no /Contents reservado (mesmo tamanho, completa com zeros). */
    public static function embutir(string $pdf, array $byteRange, string $cms): string
    {
        $inicio = $byteRange[1];
        $fim    = $byteRange[2];
        $regiao = substr($pdf, $inicio, $fim - $inicio);

        if (! preg_match('/^<([0-9a-fA-F]+)>$/', $regiao, $m)) {
            throw new RuntimeException('Espaço da assinatura (/Contents) não encontrado no intervalo do /ByteRange.');
        }
        $hex = bin2hex($cms);
        if (strlen($hex) > strlen($m[1])) {
            throw new RuntimeException(sprintf(
                'Assinatura (%d bytes) maior que o espaço reservado no PDF (%d bytes).',
                strlen($cms),
                intdiv(strlen($m[1]), 2),
            ));
        }

        return substr($pdf, 0, $inicio) . '<' . str_pad($hex, strlen($m[1]), '0') . '>' . substr($pdf, $fim);
    }
}
