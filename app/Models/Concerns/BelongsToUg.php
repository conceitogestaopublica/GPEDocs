<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Ug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenancy por Unidade Gestora.
 *
 * - Adiciona uma global scope que filtra automaticamente as queries
 *   pelo `session('ug_id')` ativo (a UG escolhida no login).
 * - Super-admins ignoram o filtro (veem dados de todas UGs).
 * - Quando nao ha sessao web (CLI, jobs, testes), nao aplica filtro.
 * - Forca `ug_id = session('ug_id')` ao criar registro novo se nao for
 *   informado explicitamente.
 *
 * Para liberar uma query do filtro: Model::withoutGlobalScope('ug').
 */
trait BelongsToUg
{
    public static function bootBelongsToUg(): void
    {
        static::addGlobalScope('ug', function (Builder $query) {
            // Sem usuario logado e sem sessao com ug_id, nao aplica filtro
            // (jobs, console, public endpoints).
            $user = Auth::user();

            // Super_admin ve dados de todas UGs
            if ($user && $user->super_admin) {
                return;
            }

            $ugId = session('ug_id');
            if (! $ugId) {
                return;
            }

            $query->where($query->getModel()->qualifyColumn('ug_id'), $ugId);
        });

        static::creating(function ($model) {
            if (empty($model->ug_id)) {
                $ugId = session('ug_id');
                if ($ugId) {
                    $model->ug_id = $ugId;
                }
            }
        });
    }

    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class, 'ug_id');
    }
}
