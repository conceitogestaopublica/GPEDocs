<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

use App\Models\IntegracaoTributaria;

/** Adaptador do sistema tributário da UG (a configuração própria dela ou a geral do município). */
final class Fabrica
{
    public static function configuracao(?int $ugId = null): ?IntegracaoTributaria
    {
        $ugId ??= session('ug_id') ? (int) session('ug_id') : null;

        return IntegracaoTributaria::query()->where('ativo', true)
            ->where(fn ($q) => $q->where('ug_id', $ugId)->orWhereNull('ug_id'))
            ->orderByRaw('ug_id is null')->first();
    }

    public static function para(?int $ugId = null): SistemaTributario
    {
        return self::de(self::configuracao($ugId));
    }

    public static function de(?IntegracaoTributaria $cfg): SistemaTributario
    {
        return match ($cfg?->driver) {
            'gpe' => new RestGenerico(GpeTributario::configurar($cfg)),
            'rest' => new RestGenerico($cfg),
            default => new SemIntegracao(),
        };
    }
}