<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Separa o user agent em sistema operacional, tipo de dispositivo e navegador (TR 4.25.14).
 * Heurística simples por expressão regular — suficiente para relatório, sem dependência externa.
 */
class AgenteUsuario
{
    /** @return array{0: ?string, 1: ?string, 2: ?string} [so, dispositivo, navegador] */
    public static function analisar(?string $ua): array
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return [null, null, null];
        }

        $so = match (true) {
            (bool) preg_match('/Windows NT 10/i', $ua) => 'Windows 10/11',
            (bool) preg_match('/Windows NT 6\.[23]/i', $ua) => 'Windows 8',
            (bool) preg_match('/Windows NT 6\.1/i', $ua) => 'Windows 7',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Android ([\d.]+)/i', $ua, $m) => 'Android ' . explode('.', $m[1])[0],
            (bool) preg_match('/(iPhone|iPad|iPod).*OS (\d+)/i', $ua, $m) => 'iOS ' . $m[2],
            (bool) preg_match('/Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Outro',
        };

        $dispositivo = match (true) {
            (bool) preg_match('/bot|crawl|spider|curl|wget|python|okhttp|Go-http/i', $ua) => 'Robô',
            (bool) preg_match('/iPad|Tablet|(Android(?!.*Mobile))/i', $ua) => 'Tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android/i', $ua) => 'Celular',
            default => 'Computador',
        };

        $navegador = match (true) {
            (bool) preg_match('/Edg(?:e|A|iOS)?\/(\d+)/', $ua, $m) => 'Edge ' . $m[1],
            (bool) preg_match('/(?:OPR|Opera)\/(\d+)/', $ua, $m) => 'Opera ' . $m[1],
            (bool) preg_match('/SamsungBrowser\/(\d+)/', $ua, $m) => 'Samsung Internet ' . $m[1],
            (bool) preg_match('/Firefox\/(\d+)/', $ua, $m) => 'Firefox ' . $m[1],
            (bool) preg_match('/HeadlessChrome\/(\d+)/', $ua, $m) => 'Chrome sem interface ' . $m[1],
            (bool) preg_match('/(?:Chrome|CriOS)\/(\d+)/', $ua, $m) => 'Chrome ' . $m[1],
            (bool) preg_match('/Version\/(\d+).*Safari/', $ua, $m) => 'Safari ' . $m[1],
            default => 'Outro',
        };

        return [$so, $dispositivo, $navegador];
    }
}
