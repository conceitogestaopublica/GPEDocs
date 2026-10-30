<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Parecer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Regras do parecer (TR 4.39.2–4.39.3): número automático por UG e ano e quem pode emitir —
 * permissão parecer.emitir e, se houver setores autorizados configurados, só servidores deles.
 */
final class Pareceres
{
    /** @return list<int> */
    public static function setoresAutorizados(): array
    {
        return DB::table('proc_parecer_setores')->pluck('unidade_id')->map(fn ($v) => (int) $v)->all();
    }

    public static function podeEmitir(?User $u): bool
    {
        if (! $u || ! ($u->super_admin || $u->can('parecer.emitir'))) {
            return false;
        }
        $autorizados = self::setoresAutorizados();

        return $u->super_admin || ! $autorizados || array_intersect($autorizados, Setores::doUsuario($u)) !== [];
    }

    /** PAR-2026/000001 — sequência por UG e ano; chamar dentro da transação que grava. */
    public static function proximoNumero(int $ugId): string
    {
        $ano = (int) date('Y');
        DB::select('select pg_advisory_xact_lock(?)', [crc32("parecer-{$ugId}-{$ano}")]);
        $ultimo = Parecer::withoutGlobalScopes()->withTrashed()->where('ug_id', $ugId)->where('numero', 'like', "PAR-{$ano}/%")
            ->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));

        return 'PAR-' . $ano . '/' . str_pad((string) (((int) $ultimo) + 1), 6, '0', STR_PAD_LEFT);
    }
}