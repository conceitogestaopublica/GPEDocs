<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GerarRelatorio;
use App\Support\Eventos;
use App\Support\RelatorioEventos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Relatórios da trilha (TR 4.25.4–6, 4.25.12–16), para os servidores com a permissão delegada
 * relatorio.eventos: eventos e acessos diários com filtros, CSV na hora (até LIMITE_DIRETO linhas) ou
 * em segundo plano com aviso, os relatórios gerados nos últimos 7 dias e os agendamentos.
 */
class RelatorioController extends Controller
{
    public const LIMITE_DIRETO = 5000;

    public const FREQUENCIAS = ['diaria' => 'Todo dia (dia anterior)', 'semanal' => 'Toda segunda (últimos 7 dias)', 'mensal' => 'Todo dia 1º (mês anterior)'];

    public function eventos(Request $request)
    {
        return $this->tela($request, 'eventos');
    }

    public function acessos(Request $request)
    {
        return $this->tela($request, 'acessos');
    }

    private function tela(Request $request, string $tipo)
    {
        $f = RelatorioEventos::filtros($request->all());
        $q = $tipo === 'acessos' ? RelatorioEventos::acessos($f) : RelatorioEventos::eventos($f);
        $pagina = $q->paginate(50)->withQueryString();
        $pagina->setCollection($pagina->getCollection()->map(fn ($r) => $tipo === 'acessos' ? RelatorioEventos::linhaAcesso($r) : RelatorioEventos::linhaEvento($r)));

        return Inertia::render('Relatorios/' . ($tipo === 'acessos' ? 'Acessos' : 'Eventos'), [
            'linhas' => $pagina, 'filtros' => $f, 'limiteDireto' => self::LIMITE_DIRETO,
            'categorias' => Eventos::CATEGORIAS, 'codigos' => $tipo === 'eventos' ? $this->codigos() : [],
            'setores' => DB::table('ug_organograma')->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']),
            'usuarios' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'gerados' => $this->gerados(), 'agendados' => $this->agendados(), 'frequencias' => self::FREQUENCIAS,
            'destaque' => $request->integer('gerado') ?: null,
        ]);
    }

    /** CSV: na hora quando é pequeno; senão vai para a fila e avisa (4.25.5). */
    public function exportar(Request $request, string $tipo)
    {
        abort_unless(isset(RelatorioEventos::TIPOS[$tipo]), 404);
        $f = RelatorioEventos::filtros($request->all());
        $total = RelatorioEventos::contar($tipo, $f);
        if ($total > self::LIMITE_DIRETO || $request->boolean('segundo_plano')) {
            self::enfileirar($tipo, $f, Auth::id());

            return back()->with('success', "O relatório ({$total} linha(s)) está sendo gerado. Você recebe um aviso quando ficar pronto; ele aparece em \"Relatórios gerados\".");
        }
        Eventos::registrar('OPE.EXE', 'operacao', 'Exportou CSV: ' . RelatorioEventos::TIPOS[$tipo] . ' (' . RelatorioEventos::resumoFiltros($f) . ')');

        return response()->streamDownload(function () use ($tipo, $f) {
            $out = fopen('php://output', 'w');
            RelatorioEventos::csv($tipo, $f, $out);
            fclose($out);
        }, "{$tipo}-" . now(RelatorioEventos::FUSO)->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function baixar(int $id)
    {
        $r = DB::table('ged_relatorios')->find($id);
        abort_unless($r && ((int) $r->solicitado_por === Auth::id() || Auth::user()->super_admin), 404);
        abort_unless($r->status === 'pronto' && $r->arquivo_path && Storage::disk('documentos')->exists($r->arquivo_path)
            && Carbon::parse($r->expira_em, 'UTC')->isFuture(), 410, 'Relatório expirado: gere de novo.');

        return Storage::disk('documentos')->download($r->arquivo_path, "{$r->tipo}-{$r->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Link da notificação: abre a tela do relatório com o gerado em destaque. */
    public function gerado(int $id)
    {
        $tipo = DB::table('ged_relatorios')->where('id', $id)->value('tipo');
        abort_unless($tipo, 404);

        return redirect("/relatorios/{$tipo}?gerado={$id}");
    }

    public function agendar(Request $request)
    {
        $d = $request->validate(['nome' => ['required', 'string', 'max:120'], 'tipo' => ['required', 'in:' . implode(',', array_keys(RelatorioEventos::TIPOS))],
            'frequencia' => ['required', 'in:' . implode(',', array_keys(self::FREQUENCIAS))], 'enviar_email' => ['boolean']]);
        // o período é calculado a cada envio; guarda só os demais filtros
        $f = collect(RelatorioEventos::filtros($request->input('filtros', [])))->except(['de', 'ate'])->all();
        DB::table('ged_relatorios_agendados')->insert(['nome' => $d['nome'], 'tipo' => $d['tipo'], 'filtros' => json_encode($f, JSON_UNESCAPED_UNICODE),
            'frequencia' => $d['frequencia'], 'enviar_email' => $request->boolean('enviar_email', true), 'ativo' => true, 'usuario_id' => Auth::id(),
            'proximo_em' => self::proximoEnvio($d['frequencia']), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', "Agendamento \"{$d['nome']}\" criado. Primeiro envio em " . self::proximoEnvio($d['frequencia'])->setTimezone(RelatorioEventos::FUSO)->format('d/m/Y H:i') . '.');
    }

    public function alternarAgendamento(int $id)
    {
        $q = DB::table('ged_relatorios_agendados')->where('id', $id)->where('usuario_id', Auth::id());
        $ag = $q->first();
        abort_unless($ag, 404);
        $q->update(['ativo' => ! $ag->ativo, 'proximo_em' => $ag->ativo ? $ag->proximo_em : self::proximoEnvio($ag->frequencia), 'updated_at' => now()]);

        return back()->with('success', $ag->ativo ? 'Agendamento pausado.' : 'Agendamento reativado.');
    }

    public function excluirAgendamento(int $id)
    {
        DB::table('ged_relatorios_agendados')->where('id', $id)->where('usuario_id', Auth::id())->delete();

        return back()->with('success', 'Agendamento excluído.');
    }

    // ---- apoio (também usado pelo comando relatorios:agendados) ----

    public static function enfileirar(string $tipo, array $filtros, int $usuarioId, ?int $agendamentoId = null): int
    {
        $id = DB::table('ged_relatorios')->insertGetId(['tipo' => $tipo, 'filtros' => json_encode($filtros, JSON_UNESCAPED_UNICODE), 'status' => 'na_fila',
            'solicitado_por' => $usuarioId, 'agendamento_id' => $agendamentoId, 'created_at' => now(), 'updated_at' => now()]);
        GerarRelatorio::dispatch($id);

        return $id;
    }

    public static function periodoDoAgendamento(object $ag): array
    {
        $hoje = now(RelatorioEventos::FUSO)->startOfDay();
        [$de, $ate] = match ($ag->frequencia) {
            'semanal' => [$hoje->copy()->subDays(7), $hoje->copy()->subDay()],
            'mensal' => [$hoje->copy()->subMonthNoOverflow()->startOfMonth(), $hoje->copy()->subMonthNoOverflow()->endOfMonth()],
            default => [$hoje->copy()->subDay(), $hoje->copy()->subDay()],
        };

        return ['de' => $de->toDateString(), 'ate' => $ate->toDateString()] + (json_decode((string) $ag->filtros, true) ?: []);
    }

    public static function proximoEnvio(string $frequencia): Carbon
    {
        $agora = now(RelatorioEventos::FUSO);
        $p = match ($frequencia) {
            'semanal' => $agora->copy()->next(Carbon::MONDAY),
            'mensal' => $agora->copy()->addMonthNoOverflow()->startOfMonth(),
            default => $agora->copy()->addDay()->startOfDay(),
        };

        return $p->setTime(6, 0)->utc();
    }

    /** Últimos 5 relatórios do usuário nos últimos 7 dias (4.25.6). */
    private function gerados(): array
    {
        return DB::table('ged_relatorios')->where('solicitado_por', Auth::id())->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('id')->limit(5)->get()->map(fn ($r) => [
                'id' => $r->id, 'tipo' => RelatorioEventos::TIPOS[$r->tipo] ?? $r->tipo, 'status' => $r->status, 'linhas' => $r->linhas, 'erro' => $r->erro,
                'filtros' => RelatorioEventos::resumoFiltros(json_decode((string) $r->filtros, true)), 'agendado' => (bool) $r->agendamento_id,
                'pedido_em' => Carbon::parse($r->created_at, 'UTC')->toIso8601String(),
                'expira_em' => $r->expira_em ? Carbon::parse($r->expira_em, 'UTC')->toIso8601String() : null,
                'url' => $r->status === 'pronto' ? "/relatorios/gerados/{$r->id}/baixar" : null])->all();
    }

    private function agendados(): array
    {
        return DB::table('ged_relatorios_agendados')->where('usuario_id', Auth::id())->orderBy('nome')->get()->map(fn ($a) => [
            'id' => $a->id, 'nome' => $a->nome, 'tipo' => RelatorioEventos::TIPOS[$a->tipo] ?? $a->tipo, 'frequencia' => self::FREQUENCIAS[$a->frequencia] ?? $a->frequencia,
            'filtros' => (RelatorioEventos::resumoFiltros(json_decode((string) $a->filtros, true) ?: []) ?: 'sem filtros'),
            'enviar_email' => (bool) $a->enviar_email, 'ativo' => (bool) $a->ativo,
            'proximo_em' => Carbon::parse($a->proximo_em, 'UTC')->toIso8601String(), 'ultimo_em' => $a->ultimo_em ? Carbon::parse($a->ultimo_em, 'UTC')->toIso8601String() : null])->all();
    }

    private function codigos(): array
    {
        $usados = DB::table('ged_eventos')->distinct()->orderBy('codigo')->pluck('codigo')->all();
        $doc = DB::table('ged_audit_logs')->distinct()->pluck('acao')->map(fn ($a) => 'DOC.' . mb_strtoupper($a))->all();

        return collect(array_merge($usados, $doc))->unique()->sort()->values()->all();
    }
}
