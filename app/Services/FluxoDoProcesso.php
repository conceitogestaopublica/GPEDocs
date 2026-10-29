<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Processo\Processo;
use App\Models\Processo\TipoEtapa;
use App\Models\UgOrganograma;
use Illuminate\Support\Collection;

/**
 * Etapas do tipo de processo como fluxo de verdade (TR 4.38.2, 4.38.19–4.38.22):
 *
 *  - cada etapa tem setores (um, vários ou nenhum = livre) — na UG do processo;
 *  - o despacho da frente principal vai para a próxima etapa ou para uma adiante, desde
 *    que não pule uma obrigatória; ou fica na etapa atual (movimento interno);
 *  - etapa obrigatória com setores só aceita despacho para um deles;
 *  - decisão formal (deferir/indeferir/parcial) só depois das etapas obrigatórias.
 */
final class FluxoDoProcesso
{
    /** @return Collection<int, TipoEtapa> etapas vigentes do tipo, em ordem */
    public static function etapas(Processo $processo): Collection
    {
        return $processo->tipoProcesso?->etapas ?? collect();
    }

    /** Setores da etapa que existem na UG do processo. @return list<int> */
    public static function setoresDaEtapa(?TipoEtapa $etapa, ?int $ugId): array
    {
        $ids = array_map('intval', $etapa?->unidades ?? []);
        if (! $ids) {
            return [];
        }

        return UgOrganograma::whereIn('id', $ids)
            ->when($ugId, fn ($q) => $q->where('ug_id', $ugId))
            ->where('ativo', true)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Maior ordem de etapa já alcançada pela frente principal (0 = nenhuma). */
    public static function ordemAlcancada(Processo $processo): int
    {
        $ids = $processo->tramitacoes()
            ->where('modo', 'tramite')->where('frente', 1)
            ->whereNotNull('tipo_etapa_id')->pluck('tipo_etapa_id');

        return (int) TipoEtapa::whereIn('id', $ids)->max('ordem');
    }

    /** Etapas para onde o despacho da frente principal pode ir: a próxima e as seguintes até a 1ª obrigatória. */
    public static function proximasPermitidas(Processo $processo): Collection
    {
        $alcancada = self::ordemAlcancada($processo);
        $permitidas = collect();
        foreach (self::etapas($processo)->where('ordem', '>', $alcancada) as $etapa) {
            $permitidas->push($etapa);
            if ($etapa->obrigatorio) {
                break;
            }
        }

        return $permitidas->values();
    }

    /** Etapas obrigatórias que ainda não foram alcançadas. @return Collection<int, TipoEtapa> */
    public static function obrigatoriasPendentes(Processo $processo): Collection
    {
        $alcancada = self::ordemAlcancada($processo);

        return self::etapas($processo)->where('ordem', '>', $alcancada)->where('obrigatorio', true)->values();
    }

    /**
     * Confere o destino do despacho da frente principal. Devolve a etapa de destino
     * (null = fora do fluxo do tipo) ou a mensagem de erro.
     *
     * @return array{etapa: ?TipoEtapa, erro: ?string}
     */
    public static function conferirDespacho(Processo $processo, ?int $etapaAtualId, string|int|null $etapaDestino, int $setorDestino): array
    {
        $etapas = self::etapas($processo);
        if ($etapas->isEmpty()) {
            return ['etapa' => null, 'erro' => null];
        }

        // Movimento dentro da etapa atual: destino livre
        if ($etapaDestino === 'manter') {
            return ['etapa' => $etapas->firstWhere('id', $etapaAtualId), 'erro' => null];
        }

        $permitidas = self::proximasPermitidas($processo);
        if ($permitidas->isEmpty()) {
            // Todas as etapas já passaram: segue sem etapa
            return ['etapa' => null, 'erro' => null];
        }
        $etapa = $etapaDestino ? $permitidas->firstWhere('id', (int) $etapaDestino) : $permitidas->first();
        if (! $etapa) {
            return ['etapa' => null, 'erro' => 'Não é possível pular a etapa obrigatória "' . $permitidas->last()->nome . '".'];
        }

        $setores = self::setoresDaEtapa($etapa, $processo->ug_id);
        if ($etapa->obrigatorio && $setores && ! in_array($setorDestino, $setores, true)) {
            $nomes = UgOrganograma::whereIn('id', $setores)->orderBy('nome')->pluck('nome')->implode(', ');

            return ['etapa' => $etapa, 'erro' => "A etapa \"{$etapa->nome}\" é feita por: {$nomes}."];
        }

        return ['etapa' => $etapa, 'erro' => null];
    }

    /** Resumo do fluxo para a tela do processo. */
    public static function paraTela(Processo $processo, ?int $etapaAtualId): ?array
    {
        $etapas = self::etapas($processo);
        if ($etapas->isEmpty()) {
            return null;
        }
        $alcancada = self::ordemAlcancada($processo);
        $passou = $processo->tramitacoes()->where('modo', 'tramite')->where('frente', 1)
            ->whereNotNull('tipo_etapa_id')->pluck('tipo_etapa_id')->map(fn ($id) => (int) $id)->all();
        $todosSetores =UgOrganograma::whereIn('id', $etapas->pluck('unidades')->flatten()->filter()->unique())
            ->where('ug_id', $processo->ug_id)->pluck('nome', 'id');
        $mapa = fn (TipoEtapa $e) => [
            'id'          => $e->id,
            'nome'        => $e->nome,
            'ordem'       => $e->ordem,
            'obrigatorio' => (bool) $e->obrigatorio,
            'sla_horas'   => $e->sla_horas,
            'modelo'      => $e->template_texto,
            'setores'     => collect(self::setoresDaEtapa($e, $processo->ug_id))
                ->map(fn ($id) => ['id' => $id, 'nome' => $todosSetores[$id] ?? '-'])->values(),
            'situacao'    => match (true) {
                $e->id === $etapaAtualId        => 'atual',
                in_array($e->id, $passou, true) => 'feita',
                $e->ordem < $alcancada          => 'pulada',   // opcional que o fluxo saltou
                default                         => 'a_fazer',
            },
        ];

        return [
            'etapas'     => $etapas->map($mapa)->values(),
            'atual_id'   => $etapaAtualId,
            'permitidas' => self::proximasPermitidas($processo)->pluck('id')->values(),
            'pendentes'  => self::obrigatoriasPendentes($processo)->pluck('nome')->values(),
        ];
    }
}
