<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacao extends Model
{
    protected $table = 'ged_notificacoes';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'titulo',
        'mensagem',
        'referencia_tipo',
        'referencia_id',
        'lida',
    ];

    protected function casts(): array
    {
        return [
            'lida' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
