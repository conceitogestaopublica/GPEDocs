<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificado extends Model
{
    protected $table = 'ged_certificados';

    protected $fillable = [
        'user_id',
        'tipo',
        'subject_cn',
        'subject_cpf',
        'subject_dn',
        'issuer_cn',
        'issuer_dn',
        'serial_number',
        'thumbprint_sha1',
        'thumbprint_sha256',
        'valido_de',
        'valido_ate',
        'certificado_pem',
        'cadeia_pem',
        'politica_oid',
        'icp_brasil',
        'revogado',
        'verificado_em',
    ];

    protected function casts(): array
    {
        return [
            'valido_de'      => 'datetime',
            'valido_ate'     => 'datetime',
            'verificado_em'  => 'datetime',
            'cadeia_pem'     => 'array',
            'icp_brasil'     => 'boolean',
            'revogado'       => 'boolean',
        ];
    }

    protected $hidden = [
        'certificado_pem',
        'cadeia_pem',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class, 'certificado_id');
    }

    public function isValido(): bool
    {
        $now = now();
        return ! $this->revogado
            && $this->valido_de->lte($now)
            && $this->valido_ate->gte($now);
    }
}
