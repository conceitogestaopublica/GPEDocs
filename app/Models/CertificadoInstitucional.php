<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Certificado da prefeitura (e-CNPJ A1) de uma UG — ver SeloInstitucionalService. */
class CertificadoInstitucional extends Model
{
    protected $table = 'ged_certificados_institucionais';

    protected $fillable = [
        'ug_id', 'pfx', 'senha', 'subject_cn', 'cnpj', 'issuer_cn', 'serial_number',
        'thumbprint_sha256', 'valido_de', 'valido_ate', 'icp_brasil', 'ativo', 'enviado_por',
    ];

    protected $hidden = ['pfx', 'senha'];

    protected function casts(): array
    {
        return [
            'pfx'        => 'encrypted',
            'senha'      => 'encrypted',
            'valido_de'  => 'datetime',
            'valido_ate' => 'datetime',
            'icp_brasil' => 'boolean',
            'ativo'      => 'boolean',
        ];
    }

    public function vigente(): bool
    {
        return $this->ativo && $this->valido_de->isPast() && $this->valido_ate->isFuture();
    }

    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class, 'ug_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
