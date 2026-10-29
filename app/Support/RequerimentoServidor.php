<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Requerimento do servidor (TR 4.36.1, LGPD): o assunto de modalidade "servidor" é pessoal —
 * só veem o processo quem tem rh.requerimentos (Gestão de Pessoas), quem abriu, o servidor em
 * nome de quem foi aberto e os setores/pessoas por onde ele tramitou. Fica fora da lista de
 * processos, das buscas, da consulta de protocolo, do mapa e das referências do editor.
 */
final class RequerimentoServidor
{
    public static function veTodos(?User $u): bool
    {
        return $u !== null && ($u->super_admin || $u->can('rh.requerimentos'));
    }

    /** Tira da consulta os requerimentos de servidor de que o usuário não participa. */
    public static function restringir(Builder $q, ?User $u): Builder
    {
        if (self::veTodos($u)) {
            return $q;
        }
        $id = $u?->id ?? 0;
        $setores = $u ? (Setores::doUsuario($u) ?: [-1]) : [-1];

        return $q->where(fn ($w) => $w->whereNotIn('tipo_processo_id', self::assuntos())
            ->orWhereNull('tipo_processo_id')
            ->orWhere('aberto_por', $id)
            ->orWhere('solicitante_user_id', $id)
            ->orWhereHas('tramitacoes', fn ($t) => $t->where(fn ($x) => $x->whereIn('destino_unidade_id', $setores)
                ->orWhere('destinatario_id', $id)->orWhere('remetente_id', $id))));
    }

    public static function podeVer(?User $u, Processo $p): bool
    {
        return self::veTodos($u) || ! in_array($p->tipo_processo_id, self::assuntos(), true)
            || self::restringir(Processo::query()->whereKey($p->id), $u)->exists();
    }

    /** @return list<int> assuntos de modalidade "servidor" */
    public static function assuntos(): array
    {
        // Sem cache estático: o worker da fila atende vários municípios no mesmo processo
        return \Illuminate\Support\Facades\DB::table('proc_tipos_processo')->where('modalidade', 'servidor')->pluck('id')->map(fn ($v) => (int) $v)->all() ?: [-1];
    }
}
