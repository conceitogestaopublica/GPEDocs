<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade visual do município (TR 4.22.1). A cor predominante vira variáveis CSS que
 * substituem as paletas do tema (primary, cyan e blue — esta já mapeada para o ciano), com
 * os tons derivados por color-mix: as telas não mudam uma classe e passam a usar a cor.
 */
final class IdentidadeVisual
{
    public const PADRAO = '#0891b2';

    /** Mistura com branco (tons claros) ou preto (escuros) para cada tom da paleta. */
    private const TONS = [50 => ['white', 8], 100 => ['white', 16], 200 => ['white', 30], 300 => ['white', 50], 400 => ['white', 75],
        500 => [null, 100], 600 => ['black', 85], 700 => ['black', 70], 800 => ['black', 55], 900 => ['black', 42], 950 => ['black', 30]];

    /** @return array{cor: ?string, brasao_ug_id: ?int, fundo: bool, atualizado_em: ?string} */
    public static function atual(): array
    {
        $chave = 'identidade_visual:' . (app(\App\Tenant\TenantContext::class)->id() ?? 'x');

        return Cache::remember($chave, 600, function () {
            $r = Schema::hasTable('ged_identidade_visual') ? DB::table('ged_identidade_visual')->first() : null;

            return ['cor' => $r?->cor_primaria, 'brasao_ug_id' => $r?->brasao_ug_id ? (int) $r->brasao_ug_id : null,
                'fundo' => (bool) $r?->fundo_login_path, 'atualizado_em' => $r?->updated_at];
        });
    }

    public static function esquecer(): void
    {
        Cache::forget('identidade_visual:' . (app(\App\Tenant\TenantContext::class)->id() ?? 'x'));
    }

    /** <style> com a paleta; vazio quando o município usa a cor padrão. */
    public static function css(?string $cor = null): string
    {
        try {
            $cor ??= self::atual()['cor'];
        } catch (\Throwable) {
            return '';   // fora de um município (páginas do landlord, erro de banco): tema padrão
        }
        if (! $cor || ! preg_match('/^#[0-9a-f]{6}$/i', $cor) || strcasecmp($cor, self::PADRAO) === 0) {
            return '';
        }
        $vars = [];
        foreach (self::TONS as $tom => [$com, $pct]) {
            $valor = $com ? "color-mix(in oklab, {$cor} {$pct}%, {$com})" : $cor;
            foreach (['primary', 'cyan', 'blue'] as $paleta) {
                $vars[] = "--color-{$paleta}-{$tom}: {$valor};";
            }
        }

        return '<style id="identidade-visual">:root{' . implode('', $vars) . '}</style>';
    }

    /** Para as telas (inclusive a de acesso) e o portal: cor e endereços do brasão e do fundo. */
    public static function paraTela(): array
    {
        try {
            $a = self::atual();
            $temBrasao = \App\Models\Ug::withoutGlobalScopes()->whereNotNull('brasao_path')->when($a['brasao_ug_id'], fn ($q, $id) => $q->whereKey($id))->exists();
            $v = substr(md5((string) $a['atualizado_em']), 0, 8);

            return ['cor' => $a['cor'] ?? self::PADRAO, 'brasao_url' => $temBrasao ? route('identidade.brasao') . "?v={$v}" : null,
                'fundo_url' => $a['fundo'] ? route('identidade.fundo') . "?v={$v}" : null];
        } catch (\Throwable) {
            return ['cor' => self::PADRAO, 'brasao_url' => null, 'fundo_url' => null];
        }
    }

    /** Cor válida digitada no cadastro (#rrggbb). */
    public static function corValida(?string $cor): ?string
    {
        $cor = mb_strtolower(trim((string) $cor));

        return preg_match('/^#[0-9a-f]{6}$/', $cor) ? $cor : null;
    }
}
