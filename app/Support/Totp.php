<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Código de uso único por tempo (TOTP, RFC 6238) — o dos aplicativos
 * autenticadores (Google Authenticator, Microsoft Authenticator, Authy…):
 * HMAC-SHA1, 6 dígitos, passo de 30 segundos.
 */
final class Totp
{
    public const PERIODO = 30;

    public const DIGITOS = 6;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Segredo novo (160 bits) em base32, como os aplicativos leem. */
    public static function gerarSegredo(): string
    {
        return self::base32(random_bytes(20));
    }

    /** Código do passo de tempo informado (padrão: agora). */
    public static function codigo(string $segredo, ?int $passo = null): string
    {
        $passo ??= self::passoAtual();
        $hash = hash_hmac('sha1', pack('J', $passo), self::deBase32($segredo), true);
        $offset = ord($hash[19]) & 0x0F;
        $numero = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($numero % 10 ** self::DIGITOS), self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * Confere o código aceitando um passo de folga para cada lado (relógio do
     * celular adiantado/atrasado). Devolve o passo que bateu, ou null.
     * Passos até $ultimoAceito são recusados: o mesmo código não entra duas vezes.
     */
    public static function verificar(string $segredo, string $codigo, ?int $ultimoAceito = null, int $folga = 1): ?int
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if (strlen($codigo) !== self::DIGITOS) {
            return null;
        }
        $agora = self::passoAtual();
        for ($p = $agora - $folga; $p <= $agora + $folga; $p++) {
            if ($ultimoAceito !== null && $p <= $ultimoAceito) {
                continue;
            }
            if (hash_equals(self::codigo($segredo, $p), $codigo)) {
                return $p;
            }
        }

        return null;
    }

    /** Endereço otpauth:// que o aplicativo lê pelo QR code. */
    public static function uri(string $segredo, string $conta, string $emissor): string
    {
        return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta)
            . '?secret=' . $segredo
            . '&issuer=' . rawurlencode($emissor)
            . '&algorithm=SHA1&digits=' . self::DIGITOS . '&period=' . self::PERIODO;
    }

    /** QR code do endereço em SVG (gerador 2D do TCPDF, já no projeto). */
    public static function qrSvg(string $uri): string
    {
        $qr = new \TCPDF2DBarcode($uri, 'QRCODE,M');

        return (string) $qr->getBarcodeSVGcode(5, 5, '#111827');
    }

    public static function passoAtual(): int
    {
        return intdiv(time(), self::PERIODO);
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $bloco) {
            $saida .= self::BASE32[bindec(str_pad($bloco, 5, '0'))];
        }

        return $saida;
    }

    private static function deBase32(string $texto): string
    {
        $texto = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto));
        $bits = '';
        foreach (str_split($texto) as $c) {
            $bits .= str_pad(decbin(strpos(self::BASE32, $c)), 5, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $saida .= chr(bindec($byte));
            }
        }

        return $saida;
    }
}
