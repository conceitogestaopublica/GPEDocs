<?php

declare(strict_types=1);

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

/** Pessoa envolvida que acompanha e interage pela Central (TR 4.23.9, 4.32.22). */
class SolicitacaoEnvolvido extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'portal_solicitacao_envolvidos';

    protected $fillable = ['solicitacao_id', 'nome', 'cpf', 'email', 'papel'];
}