<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $table = 'ged_webhook_logs';

    protected $fillable = [
        'sistema_origem', 'documento_id', 'evento', 'callback_url',
        'payload', 'signature_header',
        'sucesso', 'http_status', 'response_body', 'erro',
        'tentativas', 'duracao_ms', 'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'sucesso'    => 'boolean',
            'enviado_em' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }
}
