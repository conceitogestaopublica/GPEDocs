<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Autorização de um servidor para emitir documentos em nome de outro (TR 4.18). */
class Contrassenha extends Model
{
    protected $table = 'ged_contrassenhas';

    protected $fillable = ['autorizador_id', 'digitador_id', 'codigo_hash', 'final', 'usos_max', 'usos', 'valida_ate', 'cancelada_em', 'observacao'];

    protected $hidden = ['codigo_hash'];

    protected function casts(): array
    {
        return ['valida_ate' => 'datetime', 'cancelada_em' => 'datetime'];
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizador_id');
    }

    public function digitador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digitador_id');
    }

    /** Ainda pode ser usada: não cancelada, dentro da validade e com usos restantes. */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereNull('cancelada_em')->where('valida_ate', '>', now())->whereColumn('usos', '<', 'usos_max');
    }

    public function situacao(): string
    {
        return match (true) {
            $this->cancelada_em !== null      => 'cancelada',
            $this->usos >= $this->usos_max    => 'esgotada',
            $this->valida_ate->isPast()       => 'vencida',
            default                           => 'ativa',
        };
    }
}
