<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorandoAnexo extends Model
{
    protected $table = 'proc_memorando_anexos';

    protected $fillable = [
        'memorando_id',
        'nome',
        'arquivo_path',
        'tamanho',
        'mime_type',
        'enviado_por',
    ];

    public function memorando(): BelongsTo
    {
        return $this->belongsTo(Memorando::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
