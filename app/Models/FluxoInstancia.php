<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FluxoInstancia extends Model
{
    protected $table = 'ged_fluxo_instancias';

    protected $fillable = [
        'fluxo_id',
        'documento_id',
        'status',
        'etapa_atual',
        'iniciado_por',
    ];

    public function fluxo(): BelongsTo
    {
        return $this->belongsTo(Fluxo::class, 'fluxo_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(FluxoEtapa::class, 'instancia_id');
    }

    public function iniciador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por');
    }
}
