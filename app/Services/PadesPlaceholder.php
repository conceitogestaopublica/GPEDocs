<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Espaço reservado para a assinatura no PDF e gravação do CMS nele.
 *
 * O TCPDF monta o dicionário /Sig, o /ByteRange e o /Contents assinando com
 * um par chave/certificado descartável; depois o /Contents é sobrescrito com o
 * CMS real (CmsAdRb), sem mexer em nenhum outro byte.
 */
final class PadesPlaceholder
{
    /**
     * Par chave/cert descartável só para o TCPDF montar a estrutura.
     *
     * @return array{0: string, 1: string} [certPem, keyPem]
     */
    public static function materialDescartavel(): array
    {
        $dir = storage_path('app/private/icp-brasil-internal');
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        // Pasta criada por outro usuário (artisan como root no container) não é
        // legível pelo PHP da web: usa uma pasta própria do processo no /tmp
        if (! is_writable($dir) && ! is_readable($dir . DIRECTORY_SEPARATOR . 'placeholder.key')) {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ged-pades-' . (function_exists('posix_geteuid') ? posix_geteuid() : 'w');
            if (! is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
        }
        $certFile = $dir . DIRECTORY_SEPARATOR . 'placeholder.crt';
        $keyFile  = $dir . DIRECTORY_SEPARATOR . 'placeholder.key';

        if (! is_file($certFile) || ! is_file($keyFile)) {
            // openssl CLI: openssl_pkey_new depende do openssl.cnf no path do PHP
            shell_exec(sprintf(
                'openssl req -x509 -newkey rsa:2048 -nodes -days 36500 -subj %s -keyout %s -out %s 2>&1',
                escapeshellarg('/CN=GED-PAdES-Placeholder/O=GED Internal'),
                escapeshellarg($keyFile),
                escapeshellarg($certFile),
            ));
            if (! is_file($certFile) || ! is_file($keyFile)) {
                throw new RuntimeException('Falha ao gerar par descartável via openssl CLI.');
            }
        }

        return [(string) file_get_contents($certFile), (string) file_get_contents($keyFile)];
    }

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
