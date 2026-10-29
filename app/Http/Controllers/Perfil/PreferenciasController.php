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
            'abrangencias' => \App\Support\Notificacoes::ABRANGENCIAS,
            'emails'       => array_values(array_filter([$user->email, $user->email_secundario])),
            'celular'      => $user->telefone,
        ]);
    }

    public function salvar(Request $request)
    {
        $dados = $request->validate([
            'editor'   => ['required', Rule::in(['avancado', 'basico'])],
            'anexos'   => ['required', Rule::in(['visualizar', 'baixar'])],
            'canais.email'     => ['required', Rule::in(array_keys(\App\Support\Notificacoes::ABRANGENCIAS))],
            'canais.sms'       => ['required', Rule::in(array_keys(\App\Support\Notificacoes::ABRANGENCIAS))],
            'email_mencao'     => ['boolean'],
            'email_assinatura' => ['boolean'],
        ]);
        $dados['email_mencao'] = (bool) ($dados['email_mencao'] ?? false);
        $dados['email_assinatura'] = (bool) ($dados['email_assinatura'] ?? false);

        Auth::user()->forceFill(['preferencias' => $dados])->save();

        return back()->with('success', 'Preferências salvas.');
    }
}
