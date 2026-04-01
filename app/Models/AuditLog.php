<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ged_audit_logs';

    protected $fillable = [
        'documento_id',
        'usuario_id',
        'acao',
        'detalhes',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'detalhes' => 'array',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
