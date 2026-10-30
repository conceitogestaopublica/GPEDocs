<?php

declare(strict_types=1);

namespace App\Models\Processo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PssInscricao extends Model
{
    public const SITUACOES = ['recebida' => 'Recebida', 'deferida' => 'Deferida', 'indeferida' => 'Indeferida', 'classificada' => 'Classificada'];

    protected $table = 'proc_pss_inscricoes';

    protected $fillable = ['pss_id', 'numero', 'cargo', 'cidadao_id', 'nome', 'cpf', 'email', 'telefone', 'dados', 'situacao',
        'resposta', 'respondida_em', 'respondida_por'];

    protected function casts(): array
    {
        return ['dados' => 'array', 'respondida_em' => 'datetime'];
    }

    public function pss(): BelongsTo
    {
        return $this->belongsTo(Pss::class)->withoutGlobalScopes();
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(PssAnexo::class, 'inscricao_id');
    }
}