<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuscaSalva extends Model
{
    protected $table = 'ged_buscas_salvas';

    protected $fillable = [
        'usuario_id',
        'nome',
        'filtros',
    ];

    protected function casts(): array
    {
        return [
            'filtros' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
