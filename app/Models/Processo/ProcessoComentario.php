<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessoComentario extends Model
{
    protected $table = 'proc_comentarios';

    protected $fillable = [
        'processo_id',
        'tramitacao_id',
        'usuario_id',
        'texto',
        'interno',
    ];

    protected function casts(): array
    {
        return [
            'interno' => 'boolean',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function tramitacao(): BelongsTo
    {
        return $this->belongsTo(Tramitacao::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
