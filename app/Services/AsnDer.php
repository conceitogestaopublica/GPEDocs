<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Helpers ASN.1 DER mínimos para construção do envelope PKCS#7 de assinaturas
 * externas (Web PKI A3). Suficientes para cobrir SignedData/SignerInfo conforme
 * RFC 5652 / 5035.
 */
final class AsnDer
{
    public static function der(int $tag, string $value): string
    {
        $len = strlen($value);
        if ($len <= 127) {
            return chr($tag) . chr($len) . $value;
        }
        $tmp = '';
        $n = $len;
        while ($n > 0) {
            $tmp = chr($n & 0xFF) . $tmp;
            $n >>= 8;
        }
        return chr($tag) . chr(0x80 | strlen($tmp)) . $tmp . $value;
    }

    public static function seq(string ...$items): string
    {
        return self::der(0x30, implode('', $items));
    }

    public static function set(string ...$items): string
    {
        return self::der(0x31, implode('', $items));
    }

    public static function null(): string
    {
        return "\x05\x00";
    }

    public static function octet(string $bytes): string
    {
        return self::der(0x04, $bytes);
    }

    public static function intFromBytes(string $bytes): string
    {
        // Bytes brutos do INTEGER (positivo). Se o bit alto estiver setado,
        // prepende 0x00 para nao virar negativo na leitura.
        if ($bytes === '') {
            return self::der(0x02, "\x00");
        }
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }
        // Remove zeros redundantes a esquerda (mantendo o ultimo)
        while (strlen($bytes) > 1 && $bytes[0] === "\x00" && (ord($bytes[1]) & 0x80) === 0) {
            $bytes = substr($bytes, 1);
        }
        return self::der(0x02, $bytes);
    }

    public static function intSmall(int $value): string
    {
        if ($value === 0) {
            return self::der(0x02, "\x00");
        }
        $bytes = '';
        $n = abs($value);
        while ($n > 0) {
            $bytes = chr($n & 0xFF) . $bytes;
            $n >>= 8;
        }
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }
        return self::der(0x02, $bytes);
    }

    /**
     * INTEGER a partir de uma string hexadecimal (eg. serial number).
     */
    public static function intFromHex(string $hex): string
    {
        $hex = ltrim((string) preg_replace('/[^0-9a-fA-F]/', '', $hex), '0');
        if ($hex === '') {
            return self::der(0x02, "\x00");
        }
        if (strlen($hex) % 2) {
            $hex = '0' . $hex;
        }
        return self::intFromBytes((string) hex2bin($hex));
    }

    public static function oid(string $dotted): string
    {
        $parts = array_values(array_filter(explode('.', $dotted), fn ($p) => $p !== ''));
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('OID invalido: ' . $dotted);
        }
        $bytes = chr(40 * (int) $parts[0] + (int) $parts[1]);
        for ($i = 2, $c = count($parts); $i < $c; $i++) {
            $n = (int) $parts[$i];
            $tmp = chr($n & 0x7F);
            $n >>= 7;
            while ($n > 0) {
                $tmp = chr(($n & 0x7F) | 0x80) . $tmp;
                $n >>= 7;
            }
            $bytes .= $tmp;
        }
        return self::der(0x06, $bytes);
    }

    public static function utcTime(int $timestamp): string
    {
        $s = gmdate('ymdHis\Z', $timestamp);
        return self::der(0x17, $s);
    }

    /**
     * Tag context-specific [n] EXPLICIT (constructed).
     */
    public static function ctxExplicit(int $n, string $value): string
    {
        return self::der(0xA0 | ($n & 0x1F), $value);
    }

    /**
     * Tag context-specific [n] IMPLICIT — substitui apenas a tag de um
     * valor ja codificado em DER (preserva tamanho e payload).
     */
    public static function reTag(string $derEncoded, int $newFirstByte): string
    {
        return chr($newFirstByte) . substr($derEncoded, 1);
    }
}
