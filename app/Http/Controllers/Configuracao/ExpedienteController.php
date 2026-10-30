<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Support\Expediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Configurações › Expediente e feriados (TR 4.13.9–4.13.11): dias úteis da semana,
 * horário de funcionamento, feriados e pontos facultativos — a base do prazo em
 * dias úteis dos assuntos.
 */
class ExpedienteController extends Controller
{
    public function index(Request $request)
    {
        $ano = $request->integer('ano') ?: (int) now(Expediente::FUSO)->year;

        return Inertia::render('Configuracao/Expediente', [
            'config'   => Expediente::config(),
            'ano'      => $ano,
            'feriados' => DB::table('expediente_feriados')->whereYear('data', $ano)->orderBy('data')
                ->get(['id', 'data', 'nome', 'tipo'])
                ->map(fn ($f) => ['id' => $f->id, 'data' => substr((string) $f->data, 0, 10), 'nome' => $f->nome, 'tipo' => $f->tipo]),
        ]);
    }

    public function salvar(Request $request)
    {
        $dados = $request->validate([
            'dias_semana'   => ['required', 'array', 'min:1'],
            'dias_semana.*' => ['integer', 'between:1,7'],
            'hora_inicio'   => ['required', 'date_format:H:i'],
            'hora_fim'      => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        DB::table('expediente')->updateOrInsert(['id' => DB::table('expediente')->min('id') ?? 1], [
            'dias_semana' => json_encode(array_values(array_unique(array_map('intval', $dados['dias_semana'])))),
            'hora_inicio' => $dados['hora_inicio'],
            'hora_fim'    => $dados['hora_fim'],
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Expediente salvo.');
    }

    public function adicionarFeriado(Request $request)
    {
        $dados = $request->validate([
            'data' => ['required', 'date', 'unique:expediente_feriados,data'],
            'nome' => ['required', 'string', 'max:150'],
            'tipo' => ['required', 'in:feriado,ponto_facultativo'],
        ], ['data.unique' => 'Já existe feriado ou ponto facultativo nesta data.']);

        DB::table('expediente_feriados')->insert($dados + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Data incluída.');
    }

    public function removerFeriado(int $id)
    {
        DB::table('expediente_feriados')->where('id', $id)->delete();

        return back()->with('success', 'Data removida.');
    }

    /** Feriados nacionais do ano (fixos e móveis); os que já existem não são duplicados. */
    public function importarNacionais(Request $request)
    {
        $ano = $request->validate(['ano' => ['required', 'integer', 'between:2000,2100']])['ano'];
        $novos = 0;
        foreach (Expediente::feriadosNacionais((int) $ano) as $f) {
            $novos += DB::table('expediente_feriados')->insertOrIgnore($f + ['created_at' => now(), 'updated_at' => now()]);
        }

        return back()->with('success', $novos ? "{$novos} feriado(s) nacional(is) de {$ano} incluído(s)." : 'Os feriados nacionais deste ano já estavam cadastrados.');
    }
}
