<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Processos sigilosos por assunto (LGPD): requerimento do servidor (TR 4.36.1) e PAD (TR 4.40).
 * Só veem o processo quem tem a permissão da modalidade (rh.requerimentos / pad.gerir), quem
 * abriu, o servidor em nome de quem foi aberto, a comissão do PAD e os setores/pessoas por onde
 * ele tramitou. Fica fora da lista de processos, das buscas, da consulta de protocolo, do mapa e
 * das referências do editor.
 */
final class RequerimentoServidor
{
    /** modalidade => permissão que vê todos */
    private const RESTRITAS = ['servidor' => 'rh.requerimentos', 'pad' => 'pad.gerir'];

    /** Tira da consulta os processos restritos de que o usuário não participa. */
    public static function restringir(Builder $q, ?User $u): Builder
    {
        if ($u?->super_admin) {
            return $q;
        }
        $id = $u?->id ?? 0;
        $setores = $u ? (Setores::doUsuario($u) ?: [-1]) : [-1];
        $restritos = self::assuntosOcultos($u);
        if ($restritos === [-1]) {
            return $q;
        }

        return $q->where(fn ($w) => $w->whereNotIn('tipo_processo_id', $restritos)
            ->orWhereNull('tipo_processo_id')
            ->orWhere('aberto_por', $id)
            ->orWhere('solicitante_user_id', $id)
            ->orWhereExists(fn ($c) => $c->from('proc_pad_comissao')->whereColumn('proc_pad_comissao.processo_id', 'proc_processos.id')->where('proc_pad_comissao.user_id', $id))
            ->orWhereHas('tramitacoes', fn ($t) => $t->where(fn ($x) => $x->whereIn('destino_unidade_id', $setores)
                ->orWhere('destinatario_id', $id)->orWhere('remetente_id', $id))));
    }

    /** @return list<int> assuntos restritos cuja permissão o usuário não tem ([-1] = nenhum) */
    public static function assuntosOcultos(?User $u): array
    {
        if ($u?->super_admin) {
            return [-1];
        }
        $liberadas = $u ? array_keys(array_filter(self::RESTRITAS, fn ($perm) => $u->can($perm))) : [];

        return self::assuntos(array_values(array_diff(array_keys(self::RESTRITAS), $liberadas)));
    }

    public static function podeVer(?User $u, Processo $p): bool
    {
        return ! in_array($p->tipo_processo_id, self::assuntos(), true)
            || self::restringir(Processo::query()->whereKey($p->id), $u)->exists();
    }

    /**
     * Assuntos das modalidades restritas (todas, se não disser quais).
     * Sem cache estático: o worker da fila atende vários municípios no mesmo processo.
     *
     * @param list<string>|null $modalidades
     * @return list<int>
     */
    public static function assuntos(?array $modalidades = null): array
    {
        $modalidades ??= array_keys(self::RESTRITAS);

        return $modalidades ? (DB::table('proc_tipos_processo')->whereIn('modalidade', $modalidades)->pluck('id')->map(fn ($v) => (int) $v)->all() ?: [-1]) : [-1];
    }
}
