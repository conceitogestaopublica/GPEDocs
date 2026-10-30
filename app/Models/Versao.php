<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Versao extends Model
{
    protected $table = 'ged_versoes';

    protected $fillable = [
        'documento_id',
        'versao',
        'arquivo_path',
        'tamanho',
        'hash_sha256',
        'autor_id',
        'comentario',
    ];

    protected static function booted(): void
    {
        // Versão nova sem texto (PDF escaneado, imagem): OCR na fila para a busca (TR 4.15.4)
        static::created(fn (Versao $v) => \App\Jobs\IndexarTextoArquivo::enfileirar('documento', (int) $v->documento_id));
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
