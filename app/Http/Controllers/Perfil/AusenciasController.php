<?php

declare(strict_types=1);

namespace App\Http\Controllers\Perfil;

use App\Http\Controllers\Controller;
use App\Models\UsuarioAusencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Meu perfil › Ausências: férias, afastamentos e licenças (TR 4.7.15). */
class AusenciasController extends Controller
{
    public function index()
    {
        $hoje = now('America/Sao_Paulo')->startOfDay();

        return Inertia::render('GED/Perfil/Ausencias', [
            'tipos'     => UsuarioAusencia::TIPOS,
            'ausencias' => Auth::user()->ausencias()->limit(50)->get()->map(fn (UsuarioAusencia $a) => [
                'id'         => $a->id,
                'tipo'       => UsuarioAusencia::TIPOS[$a->tipo] ?? $a->tipo,
                'inicio'     => $a->inicio->format('d/m/Y'),
                'fim'        => $a->fim->format('d/m/Y'),
                'dias'       => $a->inicio->diffInDays($a->fim) + 1,
                'observacao' => $a->observacao,
                'situacao'   => $a->fim->lt($hoje) ? 'encerrada' : ($a->inicio->gt($hoje) ? 'futura' : 'vigente'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'tipo'       => ['required', Rule::in(array_keys(UsuarioAusencia::TIPOS))],
            'inicio'     => ['required', 'date'],
            'fim'        => ['required', 'date', 'after_or_equal:inicio', 'before_or_equal:' . now()->addYears(2)->toDateString()],
            'observacao' => ['nullable', 'string', 'max:300'],
        ], [], ['inicio' => 'início', 'fim' => 'fim']);

        $sobrepoe = UsuarioAusencia::where('user_id', Auth::id())
            ->whereDate('inicio', '<=', $dados['fim'])->whereDate('fim', '>=', $dados['inicio'])->exists();
        if ($sobrepoe) {
            throw ValidationException::withMessages(['inicio' => 'Já existe um período cadastrado nessas datas.']);
        }

        UsuarioAusencia::create($dados + ['user_id' => Auth::id()]);

        return back()->with('success', 'Período cadastrado.');
    }

    public function destroy(int $id)
    {
        $ausencia = UsuarioAusencia::where('user_id', Auth::id())->findOrFail($id);
        // Período já encerrado fica como registro
        abort_if($ausencia->fim->lt(now('America/Sao_Paulo')->startOfDay()), 403, 'Período encerrado não pode ser removido.');
        $ausencia->delete();

        return back()->with('success', 'Período removido.');
    }
}
