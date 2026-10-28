<?php

declare(strict_types=1);

namespace App\Http\Controllers\Perfil;

use App\Http\Controllers\Controller;
use App\Support\PreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Meu perfil › Preferências (TR 4.7.16, 4.7.17, 4.7.19). */
class PreferenciasController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('GED/Perfil/Preferencias', [
            'preferencias' => PreferenciasUsuario::de($user),
            'avisos'       => collect(PreferenciasUsuario::AVISOS)->map(fn ($g) => $g['rotulo']),
            'emails'       => array_values(array_filter([$user->email, $user->email_secundario])),
        ]);
    }

    public function salvar(Request $request)
    {
        $dados = $request->validate([
            'editor'   => ['required', Rule::in(['avancado', 'basico'])],
            'anexos'   => ['required', Rule::in(['visualizar', 'baixar'])],
            'avisos'   => ['array'],
            'avisos.*' => [Rule::in(array_keys(PreferenciasUsuario::AVISOS))],
        ]);
        $dados['avisos'] = array_values(array_unique($dados['avisos'] ?? []));

        Auth::user()->forceFill(['preferencias' => $dados])->save();

        return back()->with('success', 'Preferências salvas.');
    }
}
