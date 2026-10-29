<?php

declare(strict_types=1);

namespace App\Models\Portal;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Cidadao extends Authenticatable
{
    use Notifiable;

    protected $table = 'portal_cidadaos';

    protected $fillable = [
        'user_id',
        'nome',
        'email',
        'cpf',
        'telefone',
        'senha',
        'email_verificado_em',
        'token_verificacao',
        'token_verificacao_expira_em',
        'ativo',
        'nomes_anteriores',
        'endereco',
    ];

    protected $hidden = [
        'senha',
        'remember_token',
        'token_verificacao',
    ];

    protected function casts(): array
    {
        return [
            'email_verificado_em'         => 'datetime',
            'token_verificacao_expira_em' => 'datetime',
            'ativo'                       => 'boolean',
            'nomes_anteriores'            => 'array',
            'endereco'                    => 'array',
            'email_novo_expira_em'        => 'datetime',
        ];
    }

    /**
     * Laravel exige getAuthPassword(); apontamos para a coluna `senha`.
     */
    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    /** Usuário do GPE Docs que é esta pessoa: a senha conferida é a dele. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class, 'cidadao_id');
    }
}
