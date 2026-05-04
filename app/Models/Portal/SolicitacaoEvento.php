<?php

declare(strict_types=1);

namespace App\Models\Portal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitacaoEvento extends Model
{
    protected $table = 'portal_solicitacao_eventos';

    protected $fillable = [
        'solicitacao_id',
        'tipo',
        'autor_tipo',
        'autor_nome',
        'autor_user_id',
        'autor_cidadao_id',
        'status_anterior',
        'status_novo',
        'mensagem',
    ];

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    public function autorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }

    public function autorCidadao(): BelongsTo
    {
        return $this->belongsTo(Cidadao::class, 'autor_cidadao_id');
    }
}
