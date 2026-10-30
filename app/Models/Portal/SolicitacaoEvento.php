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
        'setor_nome',
        'setor_sigla',
    ];

    /** Quem é do GED interagiu: guarda o setor e a sigla dele para o cidadão ver (TR 4.23.3). */
    protected static function booted(): void
    {
        static::creating(function (SolicitacaoEvento $e) {
            if ($e->autor_user_id && ! $e->setor_sigla && ! $e->setor_nome) {
                $setor = \App\Models\UgOrganograma::find(\App\Models\User::whereKey($e->autor_user_id)->value('unidade_id'), ['nome', 'sigla']);
                $e->setor_nome = $setor?->nome;
                $e->setor_sigla = $setor?->sigla;
            }
        });
    }

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
