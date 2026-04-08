<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircularDestinatario extends Model
{
    protected $table = 'proc_circular_destinatarios';

    protected $fillable = [
        'circular_id',
        'usuario_id',
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

    public function circular(): BelongsTo
    {
        return $this->belongsTo(Circular::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
