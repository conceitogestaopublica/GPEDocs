<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SelecionarUgController extends Controller
{
    /**
     * Tela onde o usuario escolhe a UG ativa apos login.
     */
    public function index()
    {
        $user = Auth::user();

        // Super-admin pode ver todas as UGs ativas
        if ($user->super_admin) {
            $ugs = Ug::where('ativo', true)->orderBy('codigo')->get();
        } else {
            $ugs = $user->ugs()->where('ugs.ativo', true)->orderBy('codigo')->get();
        }

        // 0 UGs → tela "sem UG"
        if ($ugs->count() === 0 && ! $user->super_admin) {
            return redirect()->route('sem-ug');
        }

        // 1 UG → seta direto
        if ($ugs->count() === 1 && ! $user->super_admin) {
            session(['ug_id' => $ugs->first()->id]);
            return redirect('/modulos');
        }

        return Inertia::render('Auth/SelecionarUg', [
            'ugs'       => $ugs->map(fn ($u) => [
                'id'     => $u->id,
                'codigo' => $u->codigo,
                'nome'   => $u->nome,
                'cnpj'   => $u->cnpj,
                'cidade' => $u->cidade,
                'uf'     => $u->uf,
            ])->values(),
            'ug_atual' => session('ug_id'),
        ]);
    }

    /**
     * Confirma a escolha da UG e seta na sessao.
     */
    public function selecionar(Request $request, $id)
    {
        $user = Auth::user();
        $ug = Ug::where('ativo', true)->findOrFail($id);

        if (! $user->super_admin && ! $user->temAcessoUg($ug->id)) {
            abort(403, 'Voce nao tem acesso a esta UG.');
        }

        session(['ug_id' => $ug->id]);

        return redirect()->intended('/modulos');
    }

    /**
     * Tela de aviso para usuario sem nenhuma UG vinculada.
     */
    public function semUg()
    {
        return Inertia::render('Auth/SemUg', [
            'user' => [
                'name'  => Auth::user()->name,
                'email' => Auth::user()->email,
            ],
        ]);
    }

    /**
     * Trocar UG (sem precisar deslogar).
     */
    public function trocar(Request $request)
    {
        session()->forget('ug_id');
        return redirect()->route('selecionar-ug');
    }
}
