<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Processo;
use App\Models\Processo\Vereador;
use App\Support\Legislativo;
use App\Support\Relatorio;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Protocolo legislativo (TR 4.37): painel com consulta por tipo, vereador, situação do prazo,
 * período e recebidas/respondidas; relatório gerencial (PDF e CSV) com os mesmos filtros;
 * cadastro de vereadores.
 */
class LegislativoController extends Controller
{
    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $lista = Legislativo::consulta($f)->with('vereador:id,nome,partido')
            ->orderByRaw("case when status in ('concluido','arquivado','cancelado') then 1 else 0 end")
            ->orderBy('prazo_legal_em')->orderByDesc('id')
            ->paginate(20)->withQueryString()->through(fn (Processo $p) => Legislativo::linha($p));

        return Inertia::render('GED/Flow/Legislativo', [
            'filtros'     => $f,
            'indicadores' => Legislativo::indicadores(Legislativo::consulta($f)),
            'lista'       => $lista,
            'opcoes'      => ['proposicoes' => Legislativo::PROPOSICOES, 'vereadores' => Vereador::orderBy('nome')->get(['id', 'nome', 'partido', 'ativo'])],
        ]);
    }

    /** Relatório gerencial com os filtros da tela (TR 4.37.4). */
    public function relatorio(Request $request)
    {
        $f = $this->filtros($request);
        $linhas = Legislativo::consulta($f)->with('vereador:id,nome,partido')->orderBy('proc_processos.created_at')->limit(5000)->get()
            ->map(fn (Processo $p) => Legislativo::linha($p));
        $situacao = ['em_dia' => 'No prazo', 'vencendo' => 'Vence em breve', 'vencido' => 'Vencida', 'no_prazo' => 'Respondida no prazo', 'fora_do_prazo' => 'Respondida fora do prazo'];
        $data = fn (?string $iso) => $iso ? \Carbon\Carbon::parse($iso)->setTimezone('America/Sao_Paulo')->format('d/m/Y') : '';

        if ($request->input('formato') === 'csv') {
            return response()->streamDownload(function () use ($linhas, $situacao, $data) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Protocolo', 'Proposição', 'Número', 'Vereador', 'Partido', 'Assunto', 'Recebida em', 'Prazo', 'Respondida em', 'Situação do prazo'], ';');
                foreach ($linhas as $l) {
                    fputcsv($out, [$l['numero'], $l['proposicao'], $l['proposicao_numero'], $l['vereador'], $l['partido'], $l['assunto'],
                        $data($l['recebido_em']), $data($l['prazo']['vence_em'] ?? null), $data($l['respondido_em']), $situacao[$l['prazo']['situacao'] ?? ''] ?? ''], ';');
                }
                fclose($out);
            }, 'protocolo-legislativo.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $vereador = ! empty($f['vereador']) ? Vereador::find($f['vereador'])?->nome : null;

        return Relatorio::pdf('pdf.relatorio-legislativo', [
            'linhas' => $linhas, 'ind' => Legislativo::indicadores(Legislativo::consulta($f)), 'situacao' => $situacao, 'data' => $data,
            'filtros' => array_filter([
                'Proposição' => Legislativo::PROPOSICOES[$f['proposicao'] ?? ''] ?? null, 'Vereador' => $vereador,
                'Situação' => ['recebidas' => 'Recebidas (sem resposta)', 'respondidas' => 'Respondidas'][$f['situacao'] ?? ''] ?? null,
                'Prazo' => $situacao[$f['prazo'] ?? ''] ?? null,
                'Período' => ($f['de'] ?? null) || ($f['ate'] ?? null) ? trim(($f['de'] ?? '…') . ' a ' . ($f['ate'] ?? '…')) : null,
                'Busca' => $f['busca'] ?? null,
            ]),
            'ug' => \App\Models\Ug::find(session('ug_id')),
        ], 'landscape')->stream('protocolo-legislativo.pdf');
    }

    public function vereadores()
    {
        return Inertia::render('GED/Flow/Vereadores', [
            'vereadores' => Vereador::orderByDesc('ativo')->orderBy('nome')->get()->map(fn (Vereador $v) => $v->only(['id', 'nome', 'partido', 'email', 'ativo'])
                + ['proposicoes' => Processo::where('vereador_id', $v->id)->count()]),
        ]);
    }

    public function salvarVereador(Request $request, ?int $id = null)
    {
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:150'], 'partido' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'], 'ativo' => ['nullable', 'boolean'],
        ]);
        $d['ativo'] = $request->boolean('ativo', true);
        $id ? Vereador::findOrFail($id)->update($d) : Vereador::create($d);

        return back()->with('success', 'Vereador salvo.');
    }

    /** @return array<string, mixed> */
    private function filtros(Request $request): array
    {
        return array_filter([
            'proposicao' => array_key_exists((string) $request->input('proposicao'), Legislativo::PROPOSICOES) ? $request->input('proposicao') : null,
            'vereador'   => $request->integer('vereador') ?: null,
            'situacao'   => in_array($request->input('situacao'), ['recebidas', 'respondidas'], true) ? $request->input('situacao') : null,
            'prazo'      => in_array($request->input('prazo'), ['em_dia', 'vencendo', 'vencido', 'no_prazo', 'fora_do_prazo'], true) ? $request->input('prazo') : null,
            'de'         => $request->date('de')?->format('Y-m-d'),
            'ate'        => $request->date('ate')?->format('Y-m-d'),
            'busca'      => trim((string) $request->input('busca')) ?: null,
        ], fn ($v) => $v !== null);
    }
}
