<?php

declare(strict_types=1);

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Cidadao extends Authenticatable
{
    use Notifiable;

    protected $table = 'portal_cidadaos';

    protected $fillable = [
        'nome',
        'email',
        'cpf',
        'telefone',
        'senha',
        'email_verificado_em',
        'token_verificacao',
        'ativo',
    ];

    protected $hidden = [
        'senha',
        'remember_token',
        'token_verificacao',
    ];

    protected function casts(): array
    {
        return [
            'email_verificado_em' => 'datetime',
            'ativo'               => 'boolean',
        ];
    }

    /**
     * Laravel exige getAuthPassword(); apontamos para a coluna `senha`.
     */
    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class, 'cidadao_id');
    }
}
