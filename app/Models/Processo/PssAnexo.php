<?php

declare(strict_types=1);

namespace App\Models\Processo;

use Illuminate\Database\Eloquent\Model;

class PssAnexo extends Model
{
    protected $table = 'proc_pss_anexos';

    protected $fillable = ['inscricao_id', 'nome', 'arquivo_path', 'tamanho', 'mime_type', 'hash_sha256'];

    public function inscricao(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PssInscricao::class, 'inscricao_id');
    }
}