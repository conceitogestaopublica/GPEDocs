<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'As credenciais informadas não correspondem aos nossos registros.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = Auth::user();

        // UGs disponiveis: super_admin enxerga TODAS ativas; demais users sao
        // limitados as UGs do pivot.
        $ugs = $user->super_admin
            ? \App\Models\Ug::where('ativo', true)->get(['id'])
            : $user->ugs()->where('ugs.ativo', true)->get(['ugs.id']);

        if ($ugs->count() === 0) {
            // Super_admin sem nenhuma UG no sistema — segue para modulos (so pode ver/criar UG)
            if ($user->super_admin) {
                return redirect()->intended('/modulos');
            }
            return redirect()->route('sem-ug');
        }

        if ($ugs->count() === 1) {
            session(['ug_id' => $ugs->first()->id]);
            return redirect()->intended('/modulos');
        }

        // Mais de uma UG — usuario escolhe (inclusive super_admin)
        return redirect()->route('selecionar-ug');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
