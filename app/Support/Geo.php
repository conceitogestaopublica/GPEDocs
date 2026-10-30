<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Geometria GeoJSON sem PostGIS (TR 4.50): extrai a geometria de Feature/FeatureCollection,
 * acha o ponto central de um polígono, testa ponto dentro de polígono (raio) e a área em m²
 * (fórmula de Shoelace sobre a projeção local). Coordenadas GeoJSON são [lng, lat].
 */
final class Geo
{
    /** @return array{type: string, coordinates: array} geometria */
    public static function geometria(array|string $geojson): array
    {
        $g = is_string($geojson) ? json_decode($geojson, true) : $geojson;
        if (! is_array($g)) {
            throw new InvalidArgumentException('GeoJSON inválido.');
        }
        if (($g['type'] ?? '') === 'FeatureCollection') {
            $polis = [];
            foreach ($g['features'] ?? [] as $f) {
                $geo = $f['geometry'] ?? null;
                if (($geo['type'] ?? '') === 'Polygon') {
                    $polis[] = $geo['coordinates'];
                } elseif (($geo['type'] ?? '') === 'MultiPolygon') {
                    array_push($polis, ...$geo['coordinates']);
                }
            }

            return ['type' => 'MultiPolygon', 'coordinates' => $polis];
        }
        if (($g['type'] ?? '') === 'Feature') {
            return $g['geometry'] ?? throw new InvalidArgumentException('Feature sem geometria.');
        }
        if (! in_array($g['type'] ?? '', ['Polygon', 'MultiPolygon', 'Point', 'LineString', 'MultiLineString', 'MultiPoint'], true)) {
            throw new InvalidArgumentException('Tipo de geometria não suportado.');
        }

        return $g;
    }

    /** @return list<list<list<float>>> polígonos (cada um: anéis) */
    private static function poligonos(array $geo): array
    {
        return match ($geo['type']) {
            'Polygon' => [$geo['coordinates']],
            'MultiPolygon' => $geo['coordinates'],
            default => [],
        };
    }

    /** @return array{0: float, 1: float} [lat, lng] */
    public static function centro(array $geo): array
    {
        if ($geo['type'] === 'Point') {
            return [(float) $geo['coordinates'][1], (float) $geo['coordinates'][0]];
        }
        $pts = [];
        foreach (self::poligonos($geo) as $poli) {
            foreach ($poli[0] ?? [] as $p) {
                $pts[] = $p;
            }
        }
        if (! $pts) {
            throw new InvalidArgumentException('Geometria sem pontos.');
        }

        return [array_sum(array_column($pts, 1)) / count($pts), array_sum(array_column($pts, 0)) / count($pts)];
    }

    public static function contem(array $geo, float $lat, float $lng): bool
    {
        foreach (self::poligonos($geo) as $poli) {
            if (self::noAnel($poli[0] ?? [], $lat, $lng)) {
                $buraco = false;
                foreach (array_slice($poli, 1) as $furo) {
                    $buraco = $buraco || self::noAnel($furo, $lat, $lng);
                }
                if (! $buraco) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Área aproximada em m² (projeção equiretangular local). */
    public static function area(array $geo): float
    {
        $total = 0.0;
        foreach (self::poligonos($geo) as $poli) {
            $anel = $poli[0] ?? [];
            if (count($anel) < 3) {
                continue;
            }
            $lat0 = deg2rad(array_sum(array_column($anel, 1)) / count($anel));
            $xy = array_map(fn ($p) => [deg2rad((float) $p[0]) * 6371008.8 * cos($lat0), deg2rad((float) $p[1]) * 6371008.8], $anel);
            $s = 0.0;
            for ($i = 0, $n = count($xy); $i < $n; $i++) {
                [$x1, $y1] = $xy[$i];
                [$x2, $y2] = $xy[($i + 1) % $n];
                $s += $x1 * $y2 - $x2 * $y1;
            }
            $total += abs($s) / 2;
        }

        return $total;
    }

    private static function noAnel(array $anel, float $lat, float $lng): bool
    {
        $dentro = false;
        for ($i = 0, $j = count($anel) - 1; $i < count($anel); $j = $i++) {
            [$xi, $yi] = [(float) $anel[$i][0], (float) $anel[$i][1]];
            [$xj, $yj] = [(float) $anel[$j][0], (float) $anel[$j][1]];
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }
}