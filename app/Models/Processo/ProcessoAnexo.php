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
        'documento_id',
        'ordem',
        'avaliacao',
        'avaliacao_motivo',
        'avaliado_por',
        'avaliado_em',
    ];

    protected function casts(): array
    {
        return ['avaliado_em' => 'datetime', 'downloads' => 'integer', 'ordem' => 'integer'];
    }

    public function avaliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avaliado_por');
    }

    /** Documento do GED criado quando o anexo foi enviado para assinatura. */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Documento::class, 'documento_id');
    }

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
