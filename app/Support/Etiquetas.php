<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Etiquetas do setor (TR 4.19): o servidor vê e usa as etiquetas dos setores que atende
 * (App\Support\Setores). Etiqueta-mãe abrange as filhas na hora de filtrar.
 */
final class Etiquetas
{
    public const TIPOS = ['processo', 'memorando', 'oficio'];

    /** Etiquetas que o usuário pode ver e usar. */
    public static function doUsuario(User $user): Collection
    {
        $setores = Setores::doUsuario($user);
        if (! $setores) {
            return collect();
        }

        return DB::table('proc_etiquetas as e')->join('ug_organograma as o', 'o.id', '=', 'e.unidade_id')
            ->whereIn('e.unidade_id', $setores)
            ->orderBy('o.nome')->orderBy('e.nome')
            ->get(['e.id', 'e.unidade_id', 'e.parent_id', 'e.nome', 'e.cor_texto', 'e.cor_fundo', 'o.nome as setor']);
    }

    /** A etiqueta e todas abaixo dela (gaveta com subgavetas). @return list<int> */
    public static function comFilhas(int $id): array
    {
        $todas = [$id];
        $fila = [$id];
        while ($fila) {
            $fila = DB::table('proc_etiquetas')->whereIn('parent_id', $fila)->pluck('id')->map(fn ($i) => (int) $i)
                ->diff($todas)->values()->all();
            $todas = [...$todas, ...$fila];
        }

        return $todas;
    }

    /**
     * Etiquetas (visíveis ao usuário) de cada item. @param list<array{0:string,1:int}> $itens tipo e id
     * @return array<string, list<object>> "tipo-id" => etiquetas
     */
    public static function dosItens(User $user, array $itens): array
    {
        $visiveis = self::doUsuario($user)->keyBy('id');
        if ($visiveis->isEmpty() || ! $itens) {
            return [];
        }
        $q = DB::table('proc_etiquetados')->whereIn('etiqueta_id', $visiveis->keys())
            ->where(function ($q) use ($itens) {
                foreach (collect($itens)->groupBy(0) as $tipo => $grupo) {
                    $q->orWhere(fn ($q) => $q->where('tipo', $tipo)->whereIn('item_id', $grupo->pluck(1)));
                }
            });
        $saida = [];
        foreach ($q->get(['etiqueta_id', 'tipo', 'item_id']) as $r) {
            $saida["{$r->tipo}-{$r->item_id}"][] = $visiveis[$r->etiqueta_id];
        }

        return $saida;
    }
}
