<?php

declare(strict_types=1);

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Arquivo enviado com a solicitação do portal (disco `documentos`). */
class SolicitacaoAnexo extends Model
{
    protected $table = 'portal_solicitacao_anexos';

    protected $fillable = [
        'solicitacao_id',
        'nome',
        'arquivo_path',
        'tamanho',
        'mime_type',
        'hash_sha256',
    ];

    protected $hidden = ['arquivo_path'];

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }
}
