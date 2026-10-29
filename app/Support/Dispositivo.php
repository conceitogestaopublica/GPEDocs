<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Computador, celular ou tablet e os dados do aparelho, a partir do navegador (TR 4.11.14).
 * Leitura simples do user agent — suficiente para "Celular · Android 14 · Chrome 140".
 */
final class Dispositivo
{
    /** @return array{tipo: string, sistema: ?string, navegador: ?string} */
    public static function de(?string $ua): array
    {
        $ua = (string) $ua;
        $tipo = match (true) {
            $ua === ''                                                        => 'desconhecido',
            (bool) preg_match('/iPad|Tablet|SM-T\d/i', $ua)                   => 'tablet',
            (bool) preg_match('/Mobile|Android|iPhone|Windows Phone/i', $ua) => 'celular',
            default                                                           => 'computador',
        };
        $m = [];
        $sistema = match (true) {
            (bool) preg_match('/Android ([\d.]+)/', $ua, $m)           => "Android {$m[1]}",
            (bool) preg_match('/(?:iPhone|CPU) OS ([\d_]+)/', $ua, $m) => 'iOS ' . str_replace('_', '.', $m[1]),
            (bool) preg_match('/Windows NT 10/', $ua)                   => 'Windows 10/11',
            (bool) preg_match('/Mac OS X ([\d_]+)/', $ua, $m)           => 'macOS ' . str_replace('_', '.', $m[1]),
            (bool) preg_match('/Linux/', $ua)                           => 'Linux',
            default                                                     => null,
        };
        $navegador = match (true) {
            (bool) preg_match('/Edg\/(\d+)/', $ua, $m)             => "Edge {$m[1]}",
            (bool) preg_match('/OPR\/(\d+)/', $ua, $m)             => "Opera {$m[1]}",
            (bool) preg_match('/Firefox\/(\d+)/', $ua, $m)         => "Firefox {$m[1]}",
            (bool) preg_match('/Chrome\/(\d+)/', $ua, $m)          => "Chrome {$m[1]}",
            (bool) preg_match('/Version\/(\d+).*Safari/', $ua, $m) => "Safari {$m[1]}",
            default                                                => null,
        };

        return ['tipo' => $tipo, 'sistema' => $sistema, 'navegador' => $navegador];
    }

    public static function descrever(?string $ua): string
    {
        $d = self::de($ua);

        return implode(' · ', array_filter([ucfirst($d['tipo']), $d['sistema'], $d['navegador']]));
    }
}
