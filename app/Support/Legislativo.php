<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Protocolo legislativo (TR 4.37): requerimentos e indicações de vereadores com prazo de
 * resposta (dias do assunto, úteis ou corridos), consulta por tipo, vereador, situação do
 * prazo, período e recebidas/respondidas, e o relatório com os mesmos filtros.
 */
final class Legislativo
{
    public const PROPOSICOES = ['requerimento' => 'Requerimento', 'indicacao' => 'Indicação',
        'pedido_informacao' => 'Pedido de informação', 'mocao' => 'Moção'];

    private const ENCERRADOS = ['concluido', 'arquivado', 'cancelado'];

    /** Ao abrir: prazo de resposta do assunto (padrão 15 dias). */
    public static function aoAbrir(Processo $p): void
    {
        $tipo = $p->loadMissing('tipoProcesso')->tipoProcesso;
        if ($tipo?->modalidade !== 'legislativo') {
            return;
        }
        $dias = (int) ($tipo->prazo_resposta_dias ?: 15);
        $inicio = $p->created_at ?? now();
        $prazo = $tipo->prazo_dias_uteis ? Expediente::somarDiasUteis($inicio, $dias) : $inicio->copy()->addDays($dias)->endOfDay();
        Processo::withoutEvents(fn () => $p->forceFill(['prazo_legal_em' => $prazo])->save());
    }

    /** @param array<string, mixed> $f */
    public static function consulta(array $f = []): Builder
    {
        $agora = now();

        return Processo::query()->whereHas('tipoProcesso', fn ($t) => $t->where('modalidade', 'legislativo'))
            ->when($f['proposicao'] ?? null, fn ($w, $v) => $w->where('proposicao_tipo', $v))
            ->when($f['vereador'] ?? null, fn ($w, $v) => $w->where('vereador_id', (int) $v))
            ->when($f['de'] ?? null, fn ($w, $v) => $w->where('proc_processos.created_at', '>=', $v))
            ->when($f['ate'] ?? null, fn ($w, $v) => $w->where('proc_processos.created_at', '<=', $v . ' 23:59:59'))
            ->when($f['busca'] ?? null, fn ($w, $v) => $w->where(fn ($b) => $b->where('numero_protocolo', 'ilike', "%{$v}%")
                ->orWhere('assunto', 'ilike', "%{$v}%")->orWhere('proposicao_numero', 'ilike', "%{$v}%")))
            ->when(($f['situacao'] ?? '') === 'recebidas', fn ($w) => $w->whereNotIn('status', self::ENCERRADOS))
            ->when(($f['situacao'] ?? '') === 'respondidas', fn ($w) => $w->whereIn('status', self::ENCERRADOS))
            ->when(($f['prazo'] ?? '') === 'em_dia', fn ($w) => $w->whereNotIn('status', self::ENCERRADOS)->where('prazo_legal_em', '>', $agora->copy()->addDays(5)->endOfDay()))
            ->when(($f['prazo'] ?? '') === 'vencendo', fn ($w) => $w->whereNotIn('status', self::ENCERRADOS)
                ->whereBetween('prazo_legal_em', [$agora, $agora->copy()->addDays(5)->endOfDay()]))
            ->when(($f['prazo'] ?? '') === 'vencido', fn ($w) => $w->whereNotIn('status', self::ENCERRADOS)->where('prazo_legal_em', '<', $agora))
            ->when(($f['prazo'] ?? '') === 'no_prazo', fn ($w) => $w->whereIn('status', self::ENCERRADOS)->whereColumn('concluido_em', '<=', 'prazo_legal_em'))
            ->when(($f['prazo'] ?? '') === 'fora_do_prazo', fn ($w) => $w->whereIn('status', self::ENCERRADOS)->whereColumn('concluido_em', '>', 'prazo_legal_em'));
    }

    /** @return array<string, mixed> */
    public static function indicadores(Builder $base): array
    {
        $agora = now();
        $enc = "'" . implode("','", self::ENCERRADOS) . "'";
        $t = (clone $base)->toBase()->selectRaw("count(*) as total,
            count(*) filter (where status not in ({$enc})) as recebidas,
            count(*) filter (where status in ({$enc})) as respondidas,
            count(*) filter (where status not in ({$enc}) and prazo_legal_em < ?) as vencidas,
            count(*) filter (where status not in ({$enc}) and prazo_legal_em between ? and ?) as vencendo,
            count(*) filter (where status in ({$enc}) and concluido_em <= prazo_legal_em) as no_prazo",
            [$agora, $agora, $agora->copy()->addDays(5)->endOfDay()])->first();
        $porVereador = (clone $base)->toBase()->selectRaw('vereador_id, count(*) as qtd')->groupBy('vereador_id')->orderByDesc('qtd')->limit(10)->get();
        $nomes = DB::table('proc_vereadores')->whereIn('id', $porVereador->pluck('vereador_id')->filter())->pluck('nome', 'id');

        return [
            'total' => (int) $t->total, 'recebidas' => (int) $t->recebidas, 'respondidas' => (int) $t->respondidas,
            'vencidas' => (int) $t->vencidas, 'vencendo' => (int) $t->vencendo,
            'pct_no_prazo' => $t->respondidas > 0 ? round(100 * $t->no_prazo / $t->respondidas, 1) : null,
            'por_tipo' => (clone $base)->toBase()->selectRaw('proposicao_tipo, count(*) as qtd')->groupBy('proposicao_tipo')->orderByDesc('qtd')->get()
                ->map(fn ($r) => ['rotulo' => self::PROPOSICOES[$r->proposicao_tipo] ?? 'Não informado', 'qtd' => (int) $r->qtd])->all(),
            'por_vereador' => $porVereador->map(fn ($r) => ['rotulo' => $nomes[$r->vereador_id] ?? 'Sem vereador', 'qtd' => (int) $r->qtd])->all(),
        ];
    }

    /** Linha da lista e do relatório. */
    public static function linha(Processo $p): array
    {
        return [
            'id' => $p->id, 'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'status' => $p->status,
            'proposicao' => self::PROPOSICOES[$p->proposicao_tipo] ?? null, 'proposicao_numero' => $p->proposicao_numero,
            'vereador' => $p->vereador?->nome, 'partido' => $p->vereador?->partido,
            'recebido_em' => $p->created_at?->toIso8601String(), 'respondido_em' => $p->concluido_em?->toIso8601String(),
            'prazo' => Ouvidoria::situacaoPrazo($p),
        ];
    }
}
