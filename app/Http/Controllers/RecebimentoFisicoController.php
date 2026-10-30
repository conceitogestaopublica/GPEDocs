<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Processo;
use App\Support\Relatorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recebimento de documento físico no processo (TR 4.32.28): o servidor registra o que recebeu
 * no balcão (descrição, folhas, quem entregou) e imprime o comprovante para entregar à pessoa.
 */
class RecebimentoFisicoController extends Controller
{
    public function store(Request $request, int $id)
    {
        $d = $request->validate([
            'descricao'              => ['required', 'string', 'max:2000'],
            'folhas'                 => ['nullable', 'integer', 'min:1', 'max:9999'],
            'entregue_por_nome'      => ['required', 'string', 'max:200'],
            'entregue_por_documento' => ['nullable', 'string', 'max:30'],
        ]);
        $p = Processo::findOrFail($id);
        $rid = DB::transaction(function () use ($request, $d, $p) {
            $rid = DB::table('proc_recebimentos_fisicos')->insertGetId($d + [
                'processo_id' => $p->id, 'codigo' => 'RF-' . Str::upper(Str::random(8)), 'recebido_por' => Auth::id(), 'created_at' => now(),
            ]);
            DB::table('proc_historico')->insert([
                'processo_id' => $p->id, 'usuario_id' => Auth::id(), 'acao' => 'recebimento_fisico',
                'detalhes' => json_encode($d + ['recebimento_id' => $rid], JSON_UNESCAPED_UNICODE), 'ip' => $request->ip(), 'created_at' => now(),
            ]);

            return $rid;
        });

        $codigo = DB::table('proc_recebimentos_fisicos')->where('id', $rid)->value('codigo');

        return back()->with('success', "Recebimento {$codigo} registrado. Imprima o comprovante em \"Documentos físicos recebidos\".");
    }

    public function comprovante(int $id, int $recebimento)
    {
        $p = Processo::with('tipoProcesso:id,nome')->findOrFail($id);
        $r = DB::table('proc_recebimentos_fisicos as r')->join('users as u', 'u.id', '=', 'r.recebido_por')
            ->where('r.processo_id', $p->id)->where('r.id', $recebimento)->first(['r.*', 'u.name as servidor']);
        abort_unless($r, 404);
        $setor = DB::table('ug_organograma')->where('id', Auth::user()->unidade_id)->first(['nome', 'sigla']);

        return Relatorio::pdf('pdf.comprovante-recebimento', ['processo' => $p, 'r' => $r, 'setor' => $setor,
            'ug' => \App\Models\Ug::find($p->ug_id)])->stream("comprovante-{$r->codigo}.pdf");
    }

    /** Recebimentos do processo para a tela (lista com o link do comprovante). */
    public static function doProcesso(int $processoId): array
    {
        return DB::table('proc_recebimentos_fisicos as r')->join('users as u', 'u.id', '=', 'r.recebido_por')
            ->where('r.processo_id', $processoId)->orderByDesc('r.id')
            ->get(['r.id', 'r.codigo', 'r.descricao', 'r.folhas', 'r.entregue_por_nome', 'r.created_at', 'u.name as servidor'])
            ->map(fn ($r) => (array) $r + ['em' => \Illuminate\Support\Carbon::parse($r->created_at, 'UTC')->toIso8601String(),
                'comprovante_url' => "/processos/{$processoId}/recebimentos/{$r->id}/comprovante"])->all();
    }
}
