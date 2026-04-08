<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OficioResposta extends Model
{
    protected $table = 'proc_oficio_respostas';

    protected $fillable = [
        'oficio_id',
        'respondente_nome',
        'respondente_email',
        'conteudo',
        'externo',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'externo' => 'boolean',
        ];
    }

    public function oficio(): BelongsTo
    {
        return $this->belongsTo(Oficio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
