<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Indicadores;
use App\Support\Planilha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Painel de indicadores (TR 4.25.1, 4.25.3, 4.25.7, 4.25.17–20, 4.29): uso, setores, processos e
 * assinaturas com filtros de período, grão, setor, módulo e assunto; cada gráfico baixa em PNG, CSV
 * e XLSX (este gerado aqui, a partir dos dados que o gráfico mostra).
 */
class IndicadorController extends Controller
{
    public function index(Request $request)
    {
        $ug = (int) session('ug_id', Auth::user()->ug_id);
        $ind = new Indicadores($request->all(), $ug);

        return Inertia::render('Relatorios/Indicadores', [
            'dados' => $ind->calcular($request->boolean('recalcular')),
            'filtros' => $ind->filtrosAplicados(),
            'setores' => DB::table('ug_organograma')->where('ug_id', $ug)->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']),
            'modulos' => Indicadores::MODULOS,
            'assuntos' => DB::table('proc_tipos_processo')->orderBy('nome')->get(['id', 'nome', 'modalidade']),
            'aba' => in_array($request->input('aba'), ['uso', 'setores', 'processos', 'assinaturas'], true) ? $request->input('aba') : 'uso',
        ]);
    }

    /** XLSX dos dados de um gráfico (as colunas e linhas que a tela mostra). */
    public function planilha(Request $request)
    {
        $d = $request->validate(['titulo' => ['required', 'string', 'max:120'], 'colunas' => ['required', 'array', 'max:50'], 'colunas.*' => ['string', 'max:120'],
            'linhas' => ['present', 'array', 'max:20000'], 'linhas.*' => ['array']]);
        $nome = \Illuminate\Support\Str::slug($d['titulo']) ?: 'indicador';

        return response(Planilha::xlsx($d['titulo'], $d['colunas'], $d['linhas']), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$nome}.xlsx\"",
        ]);
    }
}
