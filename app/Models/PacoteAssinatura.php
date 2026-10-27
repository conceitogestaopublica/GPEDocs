<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Vários documentos para o mesmo signatário externo, num link só — ver PacoteAssinaturaService. */
class PacoteAssinatura extends Model
{
    protected $table = 'ged_pacotes_assinatura';

    protected $fillable = ['titulo', 'solicitante_id', 'sistema_origem', 'codigo_externo'];

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function convites(): HasMany
    {
        return $this->hasMany(PacoteConvite::class, 'pacote_id');
    }
}
