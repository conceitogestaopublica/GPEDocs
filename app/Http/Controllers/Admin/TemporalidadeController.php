<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Relatorio;
use App\Support\Temporalidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

/**
 * Avaliação do acervo pela tabela de temporalidade e eliminação de documentos (Resolução CONARQ nº 40/2014):
 * listagem → aprovação → edital de ciência → eliminação com termo. Regras em App\Support\Temporalidade.
 */
class TemporalidadeController extends Controller
{
    public function index(Request $request)
    {
        $ug = (int) session('ug_id') ?: null;
        $situacao = array_key_exists($request->input('situacao'), Temporalidade::SITUACOES) ? $request->input('situacao') : 'eliminacao';

        return Inertia::render('GED/Admin/Temporalidade/Index', [
            'resumo' => Temporalidade::resumo($ug),
            'situacao' => $situacao,
            'itens' => Temporalidade::avaliar($ug, $situacao, 2000)->take(500)->values(),
            'situacoes' => Temporalidade::SITUACOES,
            'listagens' => DB::table('ged_eliminacoes as e')->leftJoin('users as u', 'u.id', '=', 'e.criada_por')
                ->when($ug, fn ($q) => $q->where('e.ug_id', $ug))->orderByDesc('e.id')->limit(100)
                ->get(['e.*', 'u.name as criador'])
                ->map(fn ($l) => (array) $l + ['itens' => DB::table('ged_eliminacao_itens')->where('eliminacao_id', $l->id)->count(),
                    'liberada_em' => Temporalidade::liberadaEm($l)?->toIso8601String(), 'status_rotulo' => Temporalidade::STATUS[$l->status] ?? $l->status]),
            'pode_eliminar' => Auth::user()->super_admin || Auth::user()->can('arquivo.eliminar'),
            'tem_tabela' => DB::table('ged_classificacoes')->whereNotNull('ttd_destinacao')->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['itens' => ['required', 'array', 'min:1', 'max:2000'], 'itens.*.tipo' => ['required', 'in:processo,memorando,oficio,circular,documento'],
            'itens.*.id' => ['required', 'integer'], 'observacao' => ['nullable', 'string', 'max:2000']]);
        try {
            $id = Temporalidade::criarListagem((int) session('ug_id') ?: null, Auth::user(), $d['itens'], $d['observacao'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $numero = DB::table('ged_eliminacoes')->where('id', $id)->value('numero');

        return back()->with('success', "Listagem de eliminação {$numero} criada. Próximo passo: aprovação.");
    }

    public function aprovar(int $id)
    {
        return $this->etapa($id, fn ($l) => Temporalidade::aprovar($l, Auth::user()), 'Listagem aprovada. Próximo passo: publicar o edital de ciência.', true);
    }

    public function edital(Request $request, int $id)
    {
        $d = $request->validate(['prazo_dias' => ['required', 'integer', 'between:30,45']], ['prazo_dias.between' => 'O prazo do edital é de 30 a 45 dias (Resolução CONARQ nº 40/2014).']);

        return $this->etapa($id, fn ($l) => Temporalidade::publicarEdital($l, (int) $d['prazo_dias']),
            "Edital publicado. A eliminação fica liberada depois de {$d['prazo_dias']} dias, se não houver oposição.", true);
    }

    public function eliminar(int $id)
    {
        $apagados = 0;
        $r = $this->etapa($id, function ($l) use (&$apagados) {
            $apagados = Temporalidade::eliminar($l, Auth::user());
        }, '', true);

        return session('error') ? $r : back()->with('success', "Eliminação concluída: {$apagados} arquivo(s) apagado(s). O termo de eliminação está disponível.");
    }

    public function cancelar(Request $request, int $id)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        return $this->etapa($id, fn ($l) => Temporalidade::cancelar($l, Auth::user(), $d['motivo']), 'Listagem cancelada: os itens voltam a ficar disponíveis.', false);
    }

    /** PDF da listagem, do edital de ciência ou do termo de eliminação. */
    public function pdf(int $id, string $documento)
    {
        $l = $this->listagem($id);
        abort_if($documento === 'edital' && ! $l->edital_em, 404);
        abort_if($documento === 'termo' && $l->status !== 'eliminada', 404);
        $itens = DB::table('ged_eliminacao_itens as i')->leftJoin('ged_classificacoes as c', 'c.id', '=', 'i.classificacao_id')
            ->where('i.eliminacao_id', $l->id)->orderBy('c.codigo')->orderBy('i.identificacao')
            ->get(['i.*', 'c.codigo', 'c.descricao as classificacao']);
        $titulo = ['listagem' => 'Listagem de eliminação de documentos', 'edital' => 'Edital de ciência de eliminação de documentos',
            'termo' => 'Termo de eliminação de documentos'][$documento];
        $nome = fn ($uid) => $uid ? DB::table('users')->where('id', $uid)->value('name') : null;

        return Relatorio::pdf('pdf.eliminacao', ['l' => $l, 'itens' => $itens, 'documento' => $documento, 'titulo' => $titulo,
            'ug' => $l->ug_id ? \App\Models\Ug::find($l->ug_id) : null, 'usuario' => Auth::user()->name,
            'criador' => $nome($l->criada_por), 'aprovador' => $documento === 'termo' ? $nome($l->eliminada_por) : $nome($l->aprovada_por)])
            ->stream(str_replace('/', '-', $l->numero) . "-{$documento}.pdf");
    }

    private function etapa(int $id, callable $fn, string $sucesso, bool $exigeEliminar)
    {
        abort_if($exigeEliminar && ! (Auth::user()->super_admin || Auth::user()->can('arquivo.eliminar')), 403,
            'Aprovar, publicar o edital e eliminar exigem a permissão de eliminação de documentos.');
        try {
            DB::transaction(fn () => $fn(DB::table('ged_eliminacoes')->where('id', $this->listagem($id)->id)->lockForUpdate()->first()));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $sucesso ? back()->with('success', $sucesso) : back();
    }

    private function listagem(int $id): object
    {
        $ug = (int) session('ug_id') ?: null;

        return DB::table('ged_eliminacoes')->where('id', $id)->when($ug, fn ($q) => $q->where('ug_id', $ug))->first() ?? abort(404);
    }
}
