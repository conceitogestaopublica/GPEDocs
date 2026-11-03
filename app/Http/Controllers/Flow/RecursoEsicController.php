<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Processo;
use App\Support\RecursoEsic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recurso no e-SIC pela tela do processo: o servidor registra o recurso que chegou fora do portal
 * (balcão, e-mail, telefone) e a autoridade decide (regras em App\Support\RecursoEsic).
 */
class RecursoEsicController extends Controller
{
    public function registrar(Request $request, int $id)
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can('esic.gerir'), 403);
        $d = $request->validate(['motivo' => ['required', 'string', 'max:5000'], 'origem' => ['required', 'in:' . implode(',', array_keys(RecursoEsic::ORIGENS))]]);
        try {
            $r = DB::transaction(fn () => RecursoEsic::interpor(Processo::lockForUpdate()->findOrFail($id), $d['motivo'], $d['origem'], null, Auth::user()));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Recurso de {$r->instancia}ª instância registrado: a autoridade tem " . RecursoEsic::DIAS_DECIDIR . ' dias para decidir.');
    }

    public function decidir(Request $request, int $id)
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can('esic.recursos'), 403, 'Só a autoridade que decide recursos do e-SIC pode decidir.');
        $d = $request->validate(['decisao' => ['required', 'in:' . implode(',', array_keys(RecursoEsic::DECISOES))], 'fundamentacao' => ['required', 'string', 'max:10000']]);
        $r = DB::table('esic_recursos')->where('id', $id)->first() ?? abort(404);
        $p = Processo::findOrFail($r->processo_id);   // escopo de UG
        try {
            DB::transaction(fn () => RecursoEsic::decidir($r, $p, $d['decisao'], $d['fundamentacao'], Auth::user()));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Decisão do recurso registrada e enviada ao requerente.');
    }
}
