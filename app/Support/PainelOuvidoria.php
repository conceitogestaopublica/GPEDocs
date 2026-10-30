<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Painel da ouvidoria e do e-SIC (TR 4.33.25–26, 4.34): as manifestações ou pedidos da
 * modalidade, com filtros e indicadores — totais, prazo legal (no prazo, vencendo, vencidos),
 * por tipo, canal, identificação, mês, assunto e setor, e a avaliação média.
 * As estatísticas públicas do e-SIC (LAI art. 30, III) usam o mesmo cálculo.
 */
final class PainelOuvidoria
{
    private const ENCERRADOS = ['concluido', 'arquivado', 'cancelado'];

    /** Setor em que o processo está agora: destino da última tramitação. */
    private const SETOR_ATUAL = '(select t.destino_unidade_id from proc_tramitacoes t where t.processo_id = proc_processos.id order by t.id desc limit 1)';

    /** @param array<string, mixed> $f */
    public static function consulta(string $modalidade, array $f = [], ?int $ugId = null): Builder
    {
        $q = Processo::query()->whereHas('tipoProcesso', fn ($t) => $t->where('modalidade', $modalidade));
        if ($ugId) {
            $q->withoutGlobalScope('ug')->where('proc_processos.ug_id', $ugId);
        }
        $agora = now();

        return $q
            ->when($f['tipo'] ?? null, fn ($w, $v) => $w->where('manifestacao_tipo', $v))
            ->when($f['identificacao'] ?? null, fn ($w, $v) => $w->where('identificacao', $v))
            ->when($f['canal'] ?? null, fn ($w, $v) => $w->where('canal', $v))
            ->when($f['de'] ?? null, fn ($w, $v) => $w->where('proc_processos.created_at', '>=', $v))
            ->when($f['ate'] ?? null, fn ($w, $v) => $w->where('proc_processos.created_at', '<=', $v . ' 23:59:59'))
            ->when($f['setor'] ?? null, fn ($w, $v) => $w->whereRaw(self::SETOR_ATUAL . ' = ?', [(int) $v]))
            ->when($f['busca'] ?? null, fn ($w, $v) => $w->where(fn ($b) => $b->where('numero_protocolo', 'ilike', "%{$v}%")
                ->orWhere('assunto', 'ilike', "%{$v}%")))
            ->when(($f['situacao'] ?? '') === 'abertos', fn ($w) => $w->whereNotIn('status', self::ENCERRADOS))
            ->when(($f['situacao'] ?? '') === 'vencendo', fn ($w) => $w->whereNotIn('status', self::ENCERRADOS)
                ->whereBetween('prazo_legal_em', [$agora, $agora->copy()->addDays(5)->endOfDay()]))
            ->when(($f['situacao'] ?? '') === 'vencidos', fn ($w) => $w->whereNotIn('status', self::ENCERRADOS)->where('prazo_legal_em', '<', $agora))
            ->when(($f['situacao'] ?? '') === 'respondidos', fn ($w) => $w->whereIn('status', self::ENCERRADOS));
    }

    /** @return array<string, mixed> */
    public static function indicadores(Builder $base): array
    {
        $agora = now();
        $enc = "'" . implode("','", self::ENCERRADOS) . "'";
        $t = (clone $base)->toBase()->selectRaw("count(*) as total,
            count(*) filter (where status not in ({$enc})) as abertos,
            count(*) filter (where status not in ({$enc}) and prazo_legal_em < ?) as vencidos,
            count(*) filter (where status not in ({$enc}) and prazo_legal_em between ? and ?) as vencendo,
            count(*) filter (where status in ({$enc})) as respondidos,
            count(*) filter (where status in ({$enc}) and concluido_em <= prazo_legal_em) as no_prazo,
            count(*) filter (where prorrogado_em is not null) as prorrogados,
            avg(extract(epoch from (concluido_em - proc_processos.created_at)) / 86400) filter (where status in ({$enc}) and concluido_em is not null) as dias_medio",
            [$agora, $agora, $agora->copy()->addDays(5)->endOfDay()])->first();

        $agrupar = fn (string $col) => (clone $base)->toBase()->selectRaw("{$col} as chave, count(*) as qtd")
            ->groupByRaw($col)->orderByDesc('qtd')->pluck('qtd', 'chave')->map(fn ($v) => (int) $v)->all();
        $porSetor = (clone $base)->toBase()->selectRaw(self::SETOR_ATUAL . ' as setor, count(*) as qtd')
            ->groupByRaw('1')->orderByDesc('qtd')->limit(10)->get();
        $nomes = DB::table('ug_organograma')->whereIn('id', $porSetor->pluck('setor')->filter())->pluck('nome', 'id');
        $ids = (clone $base)->toBase()->select('proc_processos.id');
        $avaliacao = DB::table('proc_avaliacoes')->whereIn('processo_id', $ids)->selectRaw('round(avg(nota)::numeric, 1) as media, count(*) as qtd')->first();

        return [
            'total'       => (int) $t->total,
            'abertos'     => (int) $t->abertos,
            'vencidos'    => (int) $t->vencidos,
            'vencendo'    => (int) $t->vencendo,
            'respondidos' => (int) $t->respondidos,
            'prorrogados' => (int) $t->prorrogados,
            'pct_no_prazo' => $t->respondidos > 0 ? round(100 * $t->no_prazo / $t->respondidos, 1) : null,
            'dias_medio'  => $t->dias_medio !== null ? round((float) $t->dias_medio, 1) : null,
            'por_tipo'    => self::rotular($agrupar('manifestacao_tipo'), Ouvidoria::MANIFESTACOES),
            'por_canal'   => self::rotular($agrupar('canal'), Ouvidoria::CANAIS),
            'por_identificacao' => self::rotular($agrupar('identificacao'), Ouvidoria::IDENTIFICACOES),
            'por_mes'     => (clone $base)->toBase()->selectRaw("to_char(proc_processos.created_at, 'YYYY-MM') as mes, count(*) as qtd")
                ->groupByRaw('1')->orderBy('mes')->pluck('qtd', 'mes')->map(fn ($v) => (int) $v)->all(),
            'por_assunto' => (clone $base)->toBase()->selectRaw('assunto, count(*) as qtd')->groupBy('assunto')
                ->orderByDesc('qtd')->limit(10)->get()->map(fn ($r) => ['rotulo' => $r->assunto, 'qtd' => (int) $r->qtd])->all(),
            'por_setor'   => $porSetor->map(fn ($r) => ['rotulo' => $nomes[$r->setor] ?? 'Sem setor', 'qtd' => (int) $r->qtd])->all(),
            'avaliacao'   => ['media' => $avaliacao?->media !== null ? (float) $avaliacao->media : null, 'qtd' => (int) ($avaliacao->qtd ?? 0)],
        ];
    }

    /** @return list<array{chave: string, rotulo: string, qtd: int}> */
    private static function rotular(array $contagem, array $rotulos): array
    {
        $saida = [];
        foreach ($contagem as $chave => $qtd) {
            $saida[] = ['chave' => (string) $chave, 'rotulo' => $rotulos[$chave] ?? ($chave === '' ? 'Não informado' : (string) $chave), 'qtd' => $qtd];
        }

        return $saida;
    }
}
