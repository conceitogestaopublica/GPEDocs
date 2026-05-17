<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMensagem extends Model
{
    protected $table = 'chat_mensagens';

    protected $fillable = [
        'ug_id', 'remetente_id', 'destinatario_id', 'conteudo', 'lida_em',
    ];

    protected function casts(): array
    {
        return ['lida_em' => 'datetime'];
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }
}
