<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircularAnexo extends Model
{
    protected $table = 'proc_circular_anexos';

    protected $fillable = [
        'circular_id',
        'nome',
        'arquivo_path',
        'tamanho',
        'mime_type',
        'enviado_por',
    ];

    public function circular(): BelongsTo
    {
        return $this->belongsTo(Circular::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
