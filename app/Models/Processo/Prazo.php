<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Prazo ou atividade lançada à mão no processo (TR 4.13, 4.32.16, 4.32.17, 4.38.16).
 * escopo: todos | setor (unidade_id) | pessoal (só quem criou).
 */
class Prazo extends Model
{
    use SoftDeletes;

    protected $table = 'proc_prazos';

    protected $fillable = [
        'processo_id', 'titulo', 'descricao', 'vence_em', 'escopo', 'unidade_id', 'criado_por',
        'concluido_em', 'concluido_por', 'alerta_dias', 'alerta_email', 'alertado_em',
    ];

    protected function casts(): array
    {
        return [
            'vence_em'     => 'datetime',
            'concluido_em' => 'datetime',
            'alertado_em'  => 'datetime',
            'alerta_email' => 'boolean',
            'alerta_dias'  => 'integer',
        ];
    }

    /** Horas antes do vencimento em que o prazo passa a "vencendo". */
    public const HORAS_VENCENDO = 48;

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(UgOrganograma::class, 'unidade_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function concluidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por');
    }

    /** Prazos que o usuário enxerga: os de todos, os dos setores dele e os pessoais dele. */
    public function scopeVisiveisPara(Builder $q, User $user): Builder
    {
        return $q->where(fn ($q) => $q->where('escopo', 'todos')
            ->orWhere(fn ($q) => $q->where('escopo', 'setor')->whereIn('unidade_id', \App\Support\Setores::doUsuario($user)))
            ->orWhere(fn ($q) => $q->where('escopo', 'pessoal')->where('criado_por', $user->id)));
    }

    /** com_folga | vencendo | vencido | realizado (TR 4.13.4) */
    public function situacao(): string
    {
        return match (true) {
            $this->concluido_em !== null                          => 'realizado',
            $this->vence_em->isPast()                             => 'vencido',
            $this->vence_em->lte(now()->addHours(self::HORAS_VENCENDO)) => 'vencendo',
            default                                               => 'com_folga',
        };
    }
}
