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
        'tipo_assinatura',
        'certificado_id',
        'email_signatario',
        'cpf_signatario',
        'ip',
        'geolocalizacao',
        'user_agent',
        'hash_documento',
        'assinatura_pkcs7',
        'cadeia_certificados',
        'politica_assinatura',
        'algoritmo_hash',
        'arquivo_assinado_path',
        'hash_assinatura_sha256',
        'timestamp_assinatura',
        'versao_id',
        'motivo_recusa',
        'assinado_em',
    ];

    protected function casts(): array
    {
        return [
            'assinado_em'          => 'datetime',
            'timestamp_assinatura' => 'datetime',
            'cadeia_certificados'  => 'array',
        ];
    }

    protected $hidden = [
        'assinatura_pkcs7',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Certificado::class, 'certificado_id');
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
