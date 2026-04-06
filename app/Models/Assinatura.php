<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assinatura extends Model
{
    protected $table = 'ged_assinaturas';

    protected $fillable = [
        'solicitacao_id',
        'documento_id',
        'signatario_id',
        'ordem',
        'status',
        'email_signatario',
        'cpf_signatario',
        'ip',
        'geolocalizacao',
        'user_agent',
        'hash_documento',
        'versao_id',
        'motivo_recusa',
        'assinado_em',
    ];

    protected function casts(): array
    {
        return ['assinado_em' => 'datetime'];
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoAssinatura::class, 'solicitacao_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function signatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatario_id');
    }

    public function versao(): BelongsTo
    {
        return $this->belongsTo(Versao::class, 'versao_id');
    }
}
