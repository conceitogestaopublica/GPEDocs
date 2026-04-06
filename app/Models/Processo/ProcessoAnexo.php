<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessoAnexo extends Model
{
    protected $table = 'proc_anexos';

    protected $fillable = [
        'processo_id',
        'tramitacao_id',
        'nome',
        'arquivo_path',
        'tamanho',
        'mime_type',
        'hash_sha256',
        'enviado_por',
    ];

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function tramitacao(): BelongsTo
    {
        return $this->belongsTo(Tramitacao::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
