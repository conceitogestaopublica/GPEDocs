<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Painel de indicadores (TR 4.25.1, 4.25.3, 4.25.7, 4.25.17–20, 4.29). Tudo calculado direto dos dados
 * que as fases anteriores gravam (tramitação com leitura e resolução, avaliações, acessos, anexos com
 * páginas, assinaturas). O resultado fica 15 min em cache por combinação de filtros: o mesmo relatório
 * pedido de novo é reaproveitado (4.25.19) e o painel diz quando foi calculado.
 *
 * Datas gravadas em UTC sem fuso: agrupamentos convertem para o horário de Brasília.
 */
class Indicadores
{
    public const FUSO = 'America/Sao_Paulo';

    public const MODULOS = ['protocolo' => 'Protocolo geral', 'ouvidoria' => 'Ouvidoria', 'esic' => 'e-SIC', 'chamado' => 'Chamado técnico',
        'servidor' => 'Requerimento do servidor', 'legislativo' => 'Legislativo', 'pad' => 'PAD', 'fiscalizacao' => 'Fiscalização'];

    /** Assiduidade (4.25.1): faixas pelo tempo desde o último acesso. */
    public const FAIXAS_ACESSO = ['ate2' => 'Até 2 dias', 'ate3' => '3 dias', 'ate7' => 'Até 1 semana', 'ate14' => 'Até 2 semanas',
        'ate45' => 'Até 45 dias', 'mais45' => 'Mais de 45 dias', 'nunca' => 'Nunca acessou'];

    private array $f;

    private int $ug;

    /** @var list<int>|null setores do filtro (com subordinados) */
    private ?array $setores;

    public function __construct(array $entrada, int $ugId)
    {
        $this->ug = $ugId;
        $this->f = self::filtros($entrada);
        $this->setores = ! empty($this->f['unidade_id']) ? Organograma::comDescendentes((int) $this->f['unidade_id']) : null;
    }

    public static function filtros(array $in): array
    {
        $ate = ! empty($in['ate']) ? Carbon::parse($in['ate'], self::FUSO) : now(self::FUSO);
        $de = ! empty($in['de']) ? Carbon::parse($in['de'], self::FUSO) : $ate->copy()->subMonthsNoOverflow(6)->addDay();
        $dias = $de->diffInDays($ate);
        $grao = in_array($in['grao'] ?? '', ['dia', 'semana', 'mes', 'ano'], true) ? $in['grao'] : ($dias <= 45 ? 'dia' : ($dias <= 200 ? 'semana' : 'mes'));

        return array_filter(['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'grao' => $grao,
            'unidade_id' => ! empty($in['unidade_id']) ? (int) $in['unidade_id'] : null,
            'modulo' => isset(self::MODULOS[$in['modulo'] ?? '']) ? $in['modulo'] : null,
            'tipo' => ! empty($in['tipo']) ? (int) $in['tipo'] : null], fn ($v) => $v !== null);
    }

    public function filtrosAplicados(): array
    {
        return $this->f;
    }

    /** Todos os blocos, do cache quando a mesma combinação já foi calculada. */
    public function calcular(bool $recalcular = false): array
    {
        $chave = 'indicadores:' . app(\App\Tenant\TenantContext::class)->get()?->id . ":{$this->ug}:" . md5(json_encode($this->f));
        if ($recalcular) {
            Cache::forget($chave);
        }
        $reaproveitado = Cache::has($chave);
        $dados = Cache::remember($chave, now()->addMinutes(15), fn () => [
            'uso' => $this->uso(), 'setores' => $this->setores(), 'processos' => $this->processos(), 'assinaturas' => $this->assinaturas(),
            'calculado_em' => now()->toIso8601String(),
        ]);

        return $dados + ['reaproveitado' => $reaproveitado];
    }

    // ---------------------------------------------------------------- apoio

    private function periodo(): array
    {
        return [Carbon::parse($this->f['de'], self::FUSO)->startOfDay()->utc(), Carbon::parse($this->f['ate'], self::FUSO)->endOfDay()->utc()];
    }

    private static function local(string $col): string
    {
        return "({$col} AT TIME ZONE 'UTC' AT TIME ZONE '" . self::FUSO . "')";
    }

    /** [balde => valor] da agregação, agrupada pelo grão atual. */
    private function porBalde($q, string $col, string $agg): array
    {
        $b = $this->balde($col);

        return $q->selectRaw("{$b} as k, {$agg} as v")->groupByRaw($b)->pluck('v', 'k')->all();
    }

    private function balde(string $col): string
    {
        $g = ['dia' => 'day', 'semana' => 'week', 'mes' => 'month', 'ano' => 'year'][$this->f['grao']];

        return "date_trunc('{$g}', " . self::local($col) . ')::date';
    }

    /** Rótulos de todos os baldes do período (série contínua, sem buracos). */
    private function eixo(): array
    {
        $c = Carbon::parse($this->f['de'], self::FUSO);
        $fim = Carbon::parse($this->f['ate'], self::FUSO);
        $c = match ($this->f['grao']) { 'semana' => $c->startOfWeek(Carbon::MONDAY), 'mes' => $c->startOfMonth(), 'ano' => $c->startOfYear(), default => $c };
        $out = [];
        while ($c->lte($fim) && count($out) < 400) {
            $out[] = $c->toDateString();
            $c = match ($this->f['grao']) { 'semana' => $c->addWeek(), 'mes' => $c->addMonthNoOverflow(), 'ano' => $c->addYear(), default => $c->addDay() };
        }

        return $out;
    }

    private function rotuloBalde(string $d): string
    {
        $c = Carbon::parse($d);

        return match ($this->f['grao']) { 'mes' => $c->locale('pt_BR')->translatedFormat('M/y'), 'ano' => $c->format('Y'), 'semana' => 'sem. ' . $c->format('d/m'), default => $c->format('d/m') };
    }

    /** Monta a série: cada chave de $series é [rótulo => mapa balde→valor]. */
    private function serie(array $series): array
    {
        return array_map(function ($b) use ($series) {
            $linha = ['periodo' => $this->rotuloBalde($b)];
            foreach ($series as $nome => $mapa) {
                $linha[$nome] = (float) ($mapa[$b] ?? 0);
            }

            return $linha;
        }, $this->eixo());
    }

    /** Processos no escopo dos filtros (UG, módulo, assunto, setor por onde passou ou que abriu). */
    private function processos_q(string $alias = 'p')
    {
        $q = DB::table("proc_processos as {$alias}")->leftJoin("proc_tipos_processo as {$alias}t", "{$alias}t.id", '=', "{$alias}.tipo_processo_id")
            ->whereNull("{$alias}.deleted_at")->where("{$alias}.ug_id", $this->ug);
        if (! empty($this->f['modulo'])) {
            $this->f['modulo'] === 'protocolo' ? $q->whereNull("{$alias}t.modalidade") : $q->where("{$alias}t.modalidade", $this->f['modulo']);
        }
        if (! empty($this->f['tipo'])) {
            $q->where("{$alias}.tipo_processo_id", $this->f['tipo']);
        }
        if ($this->setores) {
            $s = $this->setores;
            $q->where(fn ($w) => $w->whereExists(fn ($e) => $e->from('proc_tramitacoes as ft')->whereColumn('ft.processo_id', "{$alias}.id")->whereIn('ft.destino_unidade_id', $s))
                ->orWhereIn("{$alias}.aberto_por", DB::table('users')->whereIn('unidade_id', $s)->select('id')));
        }

        return $q;
    }

    /** Tramitações recebidas pelos setores, no escopo dos filtros de processo. */
    private function tramitacoes_q()
    {
        $q = DB::table('proc_tramitacoes as t')->joinSub($this->processos_q()->select('p.id', 'p.tipo_processo_id', 'pt.modalidade', 'pt.nome as assunto'), 'p', 'p.id', '=', 't.processo_id');
        if ($this->setores) {
            $q->whereIn('t.destino_unidade_id', $this->setores);
        }

        return $q;
    }

    private function setoresDaUg()
    {
        $q = DB::table('ug_organograma')->where('ug_id', $this->ug)->where('ativo', true);

        return $this->setores ? $q->whereIn('id', $this->setores) : $q;
    }

    private function usuarios_q()
    {
        $q = DB::table('users as u')->where('u.tipo', '<>', 'externo')->whereNull('u.suspenso_em');
        $setores = $this->setores ?? DB::table('ug_organograma')->where('ug_id', $this->ug)->pluck('id')->all();

        return $q->where(fn ($w) => $w->whereIn('u.unidade_id', $setores ?: [-1])->when(! $this->setores, fn ($x) => $x->orWhere('u.ug_id', $this->ug)));
    }

    private static function faixaAcesso(?string $ultimo): string
    {
        if (! $ultimo) {
            return 'nunca';
        }
        $d = Carbon::parse($ultimo, 'UTC')->diffInDays(now(), true);

        return match (true) { $d <= 2 => 'ate2', $d <= 3 => 'ate3', $d <= 7 => 'ate7', $d <= 14 => 'ate14', $d <= 45 => 'ate45', default => 'mais45' };
    }

    // ---------------------------------------------------------------- uso (4.25.3, 4.25.7, 4.25.8, 4.29.8–11)

    private function uso(): array
    {
        [$de, $ate] = $this->periodo();
        $usuarios = $this->usuarios_q();
        $proc = $this->processos_q();
        $anexos = DB::table('proc_anexos as a')->joinSub($this->processos_q()->select('p.id'), 'p', 'p.id', '=', 'a.processo_id');
        $natos = [
            'Memorandos' => DB::table('proc_memorandos')->whereBetween('created_at', [$de, $ate])->count(),
            'Circulares' => DB::table('proc_circulares')->whereBetween('created_at', [$de, $ate])->count(),
            'Ofícios' => DB::table('proc_oficios')->whereBetween('created_at', [$de, $ate])->count(),
            'Pareceres' => DB::table('proc_pareceres')->whereBetween('created_at', [$de, $ate])->count(),
            'Atos oficiais' => DB::table('proc_atos')->whereBetween('created_at', [$de, $ate])->count(),
            'Despachos' => (clone $this->tramitacoes_q())->whereNotNull('t.despacho')->where('t.despacho', '<>', '')->whereBetween('t.created_at', [$de, $ate])->count(),
        ];
        $inicio = collect([DB::table('proc_processos')->where('ug_id', $this->ug)->min('created_at'), DB::table('users')->min('created_at')])->filter()->min();
        $niveis = DB::table('ged_user_roles as ur')->join('ged_roles as r', 'r.id', '=', 'ur.role_id')
            ->whereIn('ur.user_id', (clone $usuarios)->select('u.id'))->groupBy('r.nome')->orderByDesc(DB::raw('count(*)'))
            ->get(['r.nome', DB::raw('count(distinct ur.user_id) as n')])->map(fn ($r) => ['nivel' => $r->nome, 'usuarios' => (int) $r->n])->all();

        // Documentos por setor (setor de quem abriu): emitidos no período, em trâmite e concluídos
        $porSetor = (clone $proc)->join('users as ua', 'ua.id', '=', 'p.aberto_por')->leftJoin('ug_organograma as s', 's.id', '=', 'ua.unidade_id')
            ->whereBetween('p.created_at', [$de, $ate])->groupBy('s.id', 's.nome', 's.sigla')
            ->get(['s.nome', 's.sigla', DB::raw('count(*) as emitidos'), DB::raw("count(*) filter (where p.status not in ('concluido','arquivado','cancelado')) as em_tramite"),
                DB::raw("count(*) filter (where p.status in ('concluido','arquivado')) as concluidos")])
            ->map(fn ($r) => ['setor' => $r->sigla ?: ($r->nome ?: 'Sem setor'), 'emitidos' => (int) $r->emitidos, 'em_tramite' => (int) $r->em_tramite, 'concluidos' => (int) $r->concluidos])
            ->sortByDesc('emitidos')->values()->all();
        $porTipo = (clone $proc)->whereBetween('p.created_at', [$de, $ate])->groupBy('pt.nome')->orderByDesc(DB::raw('count(*)'))->limit(15)
            ->get([DB::raw("coalesce(pt.nome, 'Sem assunto') as assunto"), DB::raw('count(*) as n')])->map(fn ($r) => ['assunto' => $r->assunto, 'documentos' => (int) $r->n])->all();
        // Setores sem nenhuma movimentação (abriu ou recebeu) no período
        $ativos = DB::table('proc_tramitacoes')->whereBetween('created_at', [$de, $ate])->whereNotNull('destino_unidade_id')->distinct()->pluck('destino_unidade_id')
            ->merge(DB::table('proc_processos as p')->join('users as u', 'u.id', '=', 'p.aberto_por')->whereBetween('p.created_at', [$de, $ate])->distinct()->pluck('u.unidade_id'))
            ->filter()->unique()->all();
        $inativos = (clone $this->setoresDaUg())->whereNotIn('id', $ativos ?: [-1])->whereIn('id', DB::table('users')->whereNotNull('unidade_id')->select('unidade_id'))
            ->orderBy('nome')->limit(200)->get(['nome', 'sigla'])->map(fn ($s) => $s->sigla ? "{$s->sigla} — {$s->nome}" : $s->nome)->all();

        // Consumo mensal (4.25.8)
        $grao = $this->f['grao'];
        $this->f['grao'] = 'mes';
        $consumo = $this->serie([
            'servidores_ativos' => DB::table('ged_eventos')->where('codigo', 'ACE.ENTRADA')->whereBetween('created_at', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'created_at', 'count(distinct usuario_id)')),
            'externos_novos' => DB::table('portal_cidadaos')->whereBetween('created_at', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'created_at', 'count(*)')),
            'processos' => (clone $proc)->whereBetween('p.created_at', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'p.created_at', 'count(*)')),
            'anexos' => (clone $anexos)->whereBetween('a.created_at', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'a.created_at', 'count(*)')),
            'assinaturas' => DB::table('ged_assinaturas')->where('status', 'assinado')->whereBetween('assinado_em', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'assinado_em', 'count(*)')),
        ]);
        $this->f['grao'] = $grao;

        return [
            'cartoes' => [
                'servidores' => (clone $usuarios)->count(),
                'externos' => DB::table('portal_cidadaos')->where('ativo', true)->count() + DB::table('users')->where('tipo', 'externo')->whereNull('suspenso_em')->count(),
                'setores' => (clone $this->setoresDaUg())->count(),
                'documentos_ged' => DB::table('ged_documentos')->whereNull('deleted_at')->count(),
                'processos' => (clone $proc)->count(),
                'anexos' => (clone $anexos)->count(),
                'anexos_bytes' => (int) (clone $anexos)->sum('a.tamanho'),
                'paginas' => (int) (clone $anexos)->sum('a.paginas'),
                'nato_digitais' => array_sum($natos),
                'acessos' => DB::table('ged_eventos')->where('codigo', 'ACE.ENTRADA')->whereBetween('created_at', [$de, $ate])
                    ->when($this->setores, fn ($q) => $q->whereIn('unidade_id', $this->setores))->count(),
                'operacao_desde' => $inicio ? Carbon::parse($inicio, 'UTC')->toIso8601String() : null,
                'operacao_dias' => $inicio ? (int) Carbon::parse($inicio, 'UTC')->diffInDays(now(), true) : 0,
            ],
            'niveis' => $niveis,
            'nato_digitais' => collect($natos)->map(fn ($n, $k) => ['tipo' => $k, 'documentos' => $n])->values()->all(),
            'por_setor' => array_slice($porSetor, 0, 25),
            'por_tipo' => $porTipo,
            'setores_inativos' => $inativos,
            'consumo' => $consumo,
        ];
    }

    // ---------------------------------------------------------------- setores (4.25.1, 4.29.12–18)

    private function setores(): array
    {
        [$de, $ate] = $this->periodo();
        $tram = (clone $this->tramitacoes_q())->whereBetween('t.created_at', [$de, $ate])->whereNotNull('t.destino_unidade_id');
        $porSetor = (clone $tram)->groupBy('t.destino_unidade_id')->get(['t.destino_unidade_id as id', DB::raw('count(*) as recebidos'),
            DB::raw("count(*) filter (where t.status <> 'pendente' or t.resolvido_em is not null) as resolvidos"),
            DB::raw('count(*) filter (where t.lida_em is not null or t.recebido_em is not null) as lidos')])->keyBy('id');
        $notas = DB::table('proc_avaliacoes as a')->join('proc_tramitacoes as t', 't.processo_id', '=', 'a.processo_id')
            ->whereBetween('a.created_at', [$de, $ate])->groupBy('t.destino_unidade_id')->selectRaw('t.destino_unidade_id as k, round(avg(a.nota)::numeric, 2) as v')->pluck('v', 'k');

        $usuarios = (clone $this->usuarios_q())->get(['u.id', 'u.name', 'u.email', 'u.unidade_id', 'u.ultimo_acesso_em']);
        $nomes = DB::table('ug_organograma')->whereIn('id', $usuarios->pluck('unidade_id')->merge($porSetor->keys())->filter()->unique())->get(['id', 'nome', 'sigla'])->keyBy('id');
        $rotulo = fn ($id) => isset($nomes[$id]) ? ($nomes[$id]->sigla ?: $nomes[$id]->nome) : 'Sem setor';

        $linhas = [];
        foreach ($usuarios->groupBy(fn ($u) => (int) $u->unidade_id)->keys()->merge($porSetor->keys())->unique() as $id) {
            $doSetor = $usuarios->where('unidade_id', $id);
            $faixas = array_fill_keys(array_keys(self::FAIXAS_ACESSO), 0);
            foreach ($doSetor as $u) {
                $faixas[self::faixaAcesso($u->ultimo_acesso_em)]++;
            }
            $p = $porSetor[$id] ?? null;
            $dias = $doSetor->map(fn ($u) => $u->ultimo_acesso_em ? Carbon::parse($u->ultimo_acesso_em, 'UTC')->diffInDays(now(), true) : null)->filter(fn ($d) => $d !== null);
            $linhas[] = [
                'setor' => $rotulo($id), 'setor_id' => $id ?: null, 'servidores' => $doSetor->count(),
                'frequentes' => $faixas['ate2'] + $faixas['ate3'] + $faixas['ate7'],
                'recebidos' => (int) ($p->recebidos ?? 0), 'resolvidos' => (int) ($p->resolvidos ?? 0),
                'eficiencia' => $p && $p->recebidos ? round(100 * $p->resolvidos / $p->recebidos, 1) : null,
                'engajamento' => $p && $p->recebidos ? round(100 * $p->lidos / $p->recebidos, 1) : null,
                'qualidade' => isset($notas[$id]) ? (float) $notas[$id] : null,
                'inatividade_media' => $dias->isNotEmpty() ? round($dias->avg(), 1) : null,
            ] + $faixas;
        }
        usort($linhas, fn ($a, $b) => [$b['recebidos'], $b['servidores']] <=> [$a['recebidos'], $a['servidores']]);

        $hoje = now(self::FUSO)->startOfDay()->utc();
        $janela = ['hoje' => 0, 'd7' => 0, 'd30' => 0, 'mais30' => 0, 'nunca' => 0];
        foreach ($usuarios as $u) {
            $ult = $u->ultimo_acesso_em ? Carbon::parse($u->ultimo_acesso_em, 'UTC') : null;
            $janela[match (true) { ! $ult => 'nunca', $ult->gte($hoje) => 'hoje', $ult->gte(now()->subDays(7)) => 'd7', $ult->gte(now()->subDays(30)) => 'd30', default => 'mais30' }]++;
        }
        $total = max(1, $usuarios->count());

        return [
            'linhas' => $linhas,
            'acesso_janela' => [
                ['faixa' => 'Hoje', 'servidores' => $janela['hoje'], 'pct' => round(100 * $janela['hoje'] / $total, 1)],
                ['faixa' => 'Últimos 7 dias', 'servidores' => $janela['d7'], 'pct' => round(100 * $janela['d7'] / $total, 1)],
                ['faixa' => 'Últimos 30 dias', 'servidores' => $janela['d30'], 'pct' => round(100 * $janela['d30'] / $total, 1)],
                ['faixa' => 'Mais de 30 dias', 'servidores' => $janela['mais30'], 'pct' => round(100 * $janela['mais30'] / $total, 1)],
                ['faixa' => 'Nunca', 'servidores' => $janela['nunca'], 'pct' => round(100 * $janela['nunca'] / $total, 1)],
            ],
            // Busca nominal da inatividade (4.29.18): do mais parado para o mais ativo
            'inativos' => $usuarios->map(fn ($u) => ['nome' => $u->name, 'email' => $u->email, 'setor' => $rotulo((int) $u->unidade_id),
                'ultimo' => $u->ultimo_acesso_em ? Carbon::parse($u->ultimo_acesso_em, 'UTC')->toIso8601String() : null,
                'dias' => $u->ultimo_acesso_em ? (int) Carbon::parse($u->ultimo_acesso_em, 'UTC')->diffInDays(now(), true) : null])
                ->sortByDesc(fn ($u) => $u['dias'] ?? PHP_INT_MAX)->values()->take(1000)->all(),
            'faixas' => self::FAIXAS_ACESSO,
        ];
    }

    // ---------------------------------------------------------------- processos (4.25.17–18, 4.29.19–31, 4.29.34–38)

    private function processos(): array
    {
        [$de, $ate] = $this->periodo();
        $proc = $this->processos_q();
        $tram = $this->tramitacoes_q();
        $anexos = DB::table('proc_anexos as a')->joinSub($this->processos_q()->select('p.id'), 'p', 'p.id', '=', 'a.processo_id')->whereBetween('a.created_at', [$de, $ate]);

        $criados = (clone $proc)->whereBetween('p.created_at', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'p.created_at', 'count(*)'));
        // recebidos = tramitações enviadas a um setor (sem a abertura, ordem 1)
        $recebidos = (clone $tram)->where('t.ordem', '>', 1)->whereBetween('t.created_at', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 't.created_at', 'count(*)'));
        $resolvidos = (clone $proc)->whereNotNull('p.concluido_em')->whereBetween('p.concluido_em', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'p.concluido_em', 'count(*)'));
        $paginas = (clone $anexos)->pipe(fn ($q) => $this->porBalde($q, 'a.created_at', 'sum(coalesce(a.paginas, 0))'));
        $serie = $this->serie(['criados' => $criados, 'recebidos' => $recebidos, 'resolvidos' => $resolvidos]);
        $acum = 0;
        $antes = (int) DB::table('proc_anexos as a')->joinSub($this->processos_q()->select('p.id'), 'p', 'p.id', '=', 'a.processo_id')->where('a.created_at', '<', $de)->sum('a.paginas');
        $paginasAcum = array_map(function ($l) use (&$acum, $antes) { $acum += $l['paginas']; return ['periodo' => $l['periodo'], 'paginas' => $l['paginas'], 'acumulado' => $antes + $acum]; },
            $this->serie(['paginas' => $paginas]));

        $porAssunto = (clone $proc)->whereBetween('p.created_at', [$de, $ate])->groupBy('pt.nome')->orderByDesc(DB::raw('count(*)'))->limit(15)
            ->get([DB::raw("coalesce(pt.nome, 'Sem assunto') as assunto"), DB::raw("count(*) filter (where p.status not in ('concluido','arquivado','cancelado')) as em_aberto"),
                DB::raw("count(*) filter (where p.status in ('concluido','arquivado')) as resolvidos")])
            ->map(fn ($r) => ['assunto' => $r->assunto, 'em_aberto' => (int) $r->em_aberto, 'resolvidos' => (int) $r->resolvidos])->all();
        $porModulo = (clone $proc)->whereBetween('p.created_at', [$de, $ate])->groupBy('pt.modalidade')
            ->get(['pt.modalidade', DB::raw('count(*) as n'), DB::raw("count(*) filter (where p.status in ('concluido','arquivado')) as resolvidos")])
            ->map(fn ($r) => ['modulo' => self::MODULOS[$r->modalidade ?? 'protocolo'] ?? $r->modalidade, 'processos' => (int) $r->n, 'resolvidos' => (int) $r->resolvidos])->sortByDesc('processos')->values()->all();
        // Lista por módulo e assunto com data de criação e situação (4.29.28)
        $lista = (clone $proc)->whereBetween('p.created_at', [$de, $ate])->orderByDesc('p.created_at')->limit(500)
            ->get(['p.id', 'p.numero_protocolo', 'pt.modalidade', 'pt.nome as assunto', 'p.status', 'p.created_at'])
            ->map(fn ($r) => ['id' => $r->id, 'numero' => $r->numero_protocolo, 'modulo' => self::MODULOS[$r->modalidade ?? 'protocolo'] ?? $r->modalidade,
                'assunto' => $r->assunto, 'status' => $r->status, 'criado_em' => Carbon::parse($r->created_at, 'UTC')->setTimezone(self::FUSO)->format('d/m/Y')])->all();

        $recebidosSetor = (clone $tram)->where('t.ordem', '>', 1)->whereBetween('t.created_at', [$de, $ate])->leftJoin('ug_organograma as s', 's.id', '=', 't.destino_unidade_id')
            ->groupBy('s.nome', 's.sigla')->orderByDesc(DB::raw('count(*)'))->limit(20)
            ->get([DB::raw("coalesce(s.sigla, s.nome, 'Sem setor') as setor"), DB::raw('count(*) as recebidos'),
                DB::raw("count(*) filter (where t.status <> 'pendente' or t.resolvido_em is not null) as resolvidos")])
            ->map(fn ($r) => ['setor' => $r->setor, 'recebidos' => (int) $r->recebidos, 'resolvidos' => (int) $r->resolvidos])->all();
        $anexosSetor = (clone $anexos)->join('users as ue', 'ue.id', '=', 'a.enviado_por')->leftJoin('ug_organograma as s', 's.id', '=', 'ue.unidade_id')
            ->groupBy('s.nome', 's.sigla')->orderByDesc(DB::raw('count(*)'))->limit(20)
            ->get([DB::raw("coalesce(s.sigla, s.nome, 'Sem setor') as setor"), DB::raw('count(*) as anexos'), DB::raw('sum(coalesce(a.paginas, 0)) as paginas')])
            ->map(fn ($r) => ['setor' => $r->setor, 'anexos' => (int) $r->anexos, 'paginas' => (int) $r->paginas])->all();

        // Arquivamento (4.29.27): em quantos setores o processo já foi encerrado
        $arq = DB::query()->fromSub((clone $proc)->whereBetween('p.created_at', [$de, $ate])->join('proc_tramitacoes as t', 't.processo_id', '=', 'p.id')
            ->groupBy('p.id')->select(['p.id', DB::raw("count(*) filter (where t.status = 'pendente' and t.resolvido_em is null) as abertas"), DB::raw('count(*) as total')]), 'x')
            ->selectRaw("count(*) filter (where abertas = 0) as todos, count(*) filter (where abertas > 0 and abertas < total) as parte, count(*) filter (where abertas = total) as nenhum")->first();

        // Tempos (em horas) por módulo e assunto (4.29.31, 4.29.37, 4.29.38)
        $l = fn ($c) => self::local($c);
        $tempos = (clone $proc)->whereBetween('p.created_at', [$de, $ate])
            ->leftJoinSub(DB::table('proc_tramitacoes')->groupBy('processo_id')->select(['processo_id',
                DB::raw('min(coalesce(lida_em, recebido_em)) as primeira_leitura'),
                DB::raw('min(least(resolvido_em, case when ordem > 1 then despachado_em end)) as primeira_acao')]), 'tt', 'tt.processo_id', '=', 'p.id')
            ->groupBy('pt.modalidade', 'pt.nome')
            ->get(['pt.modalidade', DB::raw("coalesce(pt.nome, 'Sem assunto') as assunto"), DB::raw('count(*) as n'),
                DB::raw('round(avg(extract(epoch from (tt.primeira_leitura - p.created_at)) / 3600)::numeric, 1) as h_leitura'),
                DB::raw('round(avg(extract(epoch from (tt.primeira_acao - p.created_at)) / 3600)::numeric, 1) as h_acao'),
                DB::raw('round(avg(extract(epoch from (p.concluido_em - p.created_at)) / 3600)::numeric, 1) as h_resolucao'),
                DB::raw('count(p.concluido_em) as resolvidos')])
            ->map(fn ($r) => ['modulo' => self::MODULOS[$r->modalidade ?? 'protocolo'] ?? $r->modalidade, 'assunto' => $r->assunto, 'processos' => (int) $r->n,
                'resolvidos' => (int) $r->resolvidos, 'h_leitura' => $r->h_leitura !== null ? (float) $r->h_leitura : null,
                'h_acao' => $r->h_acao !== null ? (float) $r->h_acao : null, 'h_resolucao' => $r->h_resolucao !== null ? (float) $r->h_resolucao : null])
            ->sortByDesc('processos')->values()->all();
        $geral = (clone $proc)->whereBetween('p.created_at', [$de, $ate])->whereNotNull('p.concluido_em')
            ->value(DB::raw('round(avg(extract(epoch from (p.concluido_em - p.created_at)) / 86400)::numeric, 1)'));

        // Por servidor (4.29.34, 4.29.35): tramitações em que ele foi o destinatário ou quem recebeu
        $porServidor = (clone $tram)->where('t.ordem', '>', 1)->whereBetween('t.created_at', [$de, $ate])
            ->join('users as us', 'us.id', '=', DB::raw('coalesce(t.recebido_por, t.destinatario_id)'))
            ->groupBy('us.id', 'us.name')->orderByDesc(DB::raw('count(*)'))->limit(50)
            ->get(['us.name', DB::raw('count(*) as recebidas'), DB::raw("count(*) filter (where t.status <> 'pendente' or t.resolvido_em is not null) as resolvidas"),
                DB::raw('round(avg(extract(epoch from (coalesce(t.resolvido_em, case when t.status <> \'pendente\' then t.updated_at end) - t.created_at)) / 3600)::numeric, 1) as h_resolucao')])
            ->map(fn ($r) => ['servidor' => $r->name, 'recebidas' => (int) $r->recebidas, 'resolvidas' => (int) $r->resolvidas,
                'pct' => $r->recebidas ? round(100 * $r->resolvidas / $r->recebidas, 1) : null, 'h_resolucao' => $r->h_resolucao !== null ? (float) $r->h_resolucao : null])->all();

        return [
            'serie' => $serie, 'paginas' => $paginasAcum, 'por_assunto' => $porAssunto, 'por_modulo' => $porModulo, 'lista' => $lista,
            'recebidos_setor' => $recebidosSetor, 'anexos_setor' => $anexosSetor,
            'arquivamento' => [['situacao' => 'Encerrado em todos os setores', 'processos' => (int) ($arq->todos ?? 0)],
                ['situacao' => 'Encerrado em parte dos setores', 'processos' => (int) ($arq->parte ?? 0)],
                ['situacao' => 'Em nenhum setor', 'processos' => (int) ($arq->nenhum ?? 0)]],
            'tempos' => $tempos, 'dias_resolucao' => $geral !== null ? (float) $geral : null, 'por_servidor' => $porServidor,
        ];
    }

    // ---------------------------------------------------------------- assinaturas (4.29.22, 4.29.32, 4.29.33, 4.29.36)

    private function assinaturas(): array
    {
        [$de, $ate] = $this->periodo();
        $base = DB::table('ged_assinaturas as a')->leftJoin('users as us', 'us.id', '=', 'a.signatario_id')
            ->when($this->setores, fn ($q) => $q->whereIn('us.unidade_id', $this->setores));
        $solicitadas = (clone $base)->whereBetween('a.created_at', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'a.created_at', 'count(*)'));
        $feitas = (clone $base)->where('a.status', 'assinado')->whereBetween('a.assinado_em', [$de, $ate])->pipe(fn ($q) => $this->porBalde($q, 'a.assinado_em', 'count(*)'));
        $porSetor = (clone $base)->where('a.status', 'assinado')->whereBetween('a.assinado_em', [$de, $ate])->leftJoin('ug_organograma as s', 's.id', '=', 'us.unidade_id')
            ->groupBy('s.nome', 's.sigla', 'a.tipo_signatario')->orderByDesc(DB::raw('count(*)'))->limit(20)
            ->get([DB::raw("coalesce(s.sigla, s.nome, case when a.tipo_signatario = 'externo' then 'Externos' else 'Sem setor' end) as setor"), DB::raw('count(*) as assinaturas')])
            ->map(fn ($r) => ['setor' => $r->setor, 'assinaturas' => (int) $r->assinaturas])->all();
        $horas = 'round(avg(extract(epoch from (a.assinado_em - a.created_at)) / 3600)::numeric, 1)';
        $porServidor = (clone $base)->whereBetween('a.created_at', [$de, $ate])->groupBy(DB::raw("coalesce(us.name, a.nome_signatario, a.email_signatario, '—')"))
            ->orderByDesc(DB::raw('count(*)'))->limit(50)
            ->get([DB::raw("coalesce(us.name, a.nome_signatario, a.email_signatario, '—') as servidor"), DB::raw('count(*) as solicitadas'),
                DB::raw("count(*) filter (where a.status = 'assinado') as assinadas"), DB::raw("count(*) filter (where a.status = 'pendente') as pendentes"),
                DB::raw("{$horas} as h_media"), DB::raw('round(sum(extract(epoch from (a.assinado_em - a.created_at)) / 3600)::numeric, 1) as h_total')])
            ->map(fn ($r) => ['servidor' => $r->servidor, 'solicitadas' => (int) $r->solicitadas, 'assinadas' => (int) $r->assinadas, 'pendentes' => (int) $r->pendentes,
                'h_media' => $r->h_media !== null ? (float) $r->h_media : null, 'h_total' => $r->h_total !== null ? (float) $r->h_total : null])->all();

        return [
            'serie' => $this->serie(['solicitadas' => $solicitadas, 'assinadas' => $feitas]),
            'cartoes' => ['solicitadas' => array_sum($solicitadas), 'assinadas' => array_sum($feitas),
                'pendentes' => (clone $base)->where('a.status', 'pendente')->count(),
                'h_media' => (fn ($v) => $v !== null ? (float) $v : null)((clone $base)->where('a.status', 'assinado')->whereBetween('a.assinado_em', [$de, $ate])->value(DB::raw($horas)))],
            'por_setor' => $porSetor, 'por_servidor' => $porServidor,
        ];
    }
}
