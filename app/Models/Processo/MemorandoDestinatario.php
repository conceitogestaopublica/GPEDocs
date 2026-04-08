<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorandoDestinatario extends Model
{
    protected $table = 'proc_memorando_destinatarios';

    protected $fillable = [
        'memorando_id',
        'usuario_id',
        'setor_destino',
        'lido',
        'lido_em',
    ];

    protected function casts(): array
    {
        return [
            'lido'    => 'boolean',
            'lido_em' => 'datetime',
        ];
    }

    public function memorando(): BelongsTo
    {
        return $this->belongsTo(Memorando::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
