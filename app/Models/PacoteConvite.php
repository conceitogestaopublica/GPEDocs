<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Convite de um signatário externo para um pacote: um link /assinar/pacote/{token}. */
class PacoteConvite extends Model
{
    protected $table = 'ged_pacote_convites';

    protected $fillable = ['pacote_id', 'cpf', 'nome', 'email', 'telefone', 'token_hash', 'token',
        'expira_em', 'enviado_em', 'acessado_em'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token'       => 'encrypted',
            'expira_em'   => 'datetime',
            'enviado_em'  => 'datetime',
            'acessado_em' => 'datetime',
        ];
    }

    public function link(): string
    {
        return url('/assinar/pacote/' . $this->token);
    }

    public function pacote(): BelongsTo
    {
        return $this->belongsTo(PacoteAssinatura::class, 'pacote_id');
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class, 'pacote_convite_id');
    }
}
