<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FluxoEtapa extends Model
{
    protected $table = 'ged_fluxo_etapas';

    protected $fillable = [
        'instancia_id',
        'nome',
        'tipo',
        'ordem',
        'responsavel_id',
        'status',
        'prazo',
        'comentario',
        'concluido_em',
    ];

    protected function casts(): array
    {
        return [
            'prazo' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }

    public function instancia(): BelongsTo
    {
        return $this->belongsTo(FluxoInstancia::class, 'instancia_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }
}
