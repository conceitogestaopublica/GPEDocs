<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitacaoAssinatura extends Model
{
    protected $table = 'ged_solicitacoes_assinatura';

    protected $fillable = [
        'documento_id',
        'solicitante_id',
        'status',
        'mensagem',
        'prazo',
    ];

    protected function casts(): array
    {
        return ['prazo' => 'datetime'];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class, 'solicitacao_id');
    }

    /**
     * Numero de protocolo legivel — formato SOL-AAAA-NNNNNN.
     * Util para localizar uma solicitacao especifica em listagens grandes.
     */
    public function getProtocoloAttribute(): string
    {
        $ano = $this->created_at?->format('Y') ?? date('Y');
        return sprintf('SOL-%s-%06d', $ano, $this->id);
    }
}
