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
        'signature_position',
        'tipo_signatario',
        'nome_signatario',
        'telefone_signatario',
        'cidadao_id',
        'convite_token_hash',
        'convite_token',
        'convite_expira_em',
        'convite_enviado_em',
        'convite_acessado_em',
        'codigo_verificacao',
        'pacote_convite_id',
    ];

    protected function casts(): array
    {
        return [
            'assinado_em'          => 'datetime',
            'timestamp_assinatura' => 'datetime',
            'cadeia_certificados'  => 'array',
            'signature_position'   => 'array',
            'convite_token'        => 'encrypted',
            'convite_expira_em'    => 'datetime',
            'convite_enviado_em'   => 'datetime',
            'convite_acessado_em'  => 'datetime',
        ];
    }

    protected $hidden = [
        'assinatura_pkcs7',
        'convite_token',
        'convite_token_hash',
    ];

    /** Nome para telas, manifesto e verificação: o usuário interno ou o nome do convite. */
    protected $appends = ['nome_exibicao', 'externo'];

    public function getNomeExibicaoAttribute(): ?string
    {
        return $this->signatario?->name ?? $this->nome_signatario;
    }

    public function getExternoAttribute(): bool
    {
        return $this->tipo_signatario === 'externo';
    }

    /** Link público de assinatura do convite (só enquanto pendente). */
    public function linkConvite(): ?string
    {
        if (! $this->externo || $this->status !== 'pendente' || ! $this->convite_token) {
            return null;
        }

        return url('/assinar/' . $this->convite_token);
    }

    public function pacoteConvite(): BelongsTo
    {
        return $this->belongsTo(PacoteConvite::class, 'pacote_convite_id');
    }

    public function cidadao(): BelongsTo
    {
        return $this->belongsTo(Portal\Cidadao::class, 'cidadao_id');
    }

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
