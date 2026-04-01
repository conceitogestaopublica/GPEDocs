<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fluxo extends Model
{
    protected $table = 'ged_fluxos';

    protected $fillable = [
        'nome',
        'descricao',
        'definicao',
        'ativo',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'definicao' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function instancias(): HasMany
    {
        return $this->hasMany(FluxoInstancia::class, 'fluxo_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
