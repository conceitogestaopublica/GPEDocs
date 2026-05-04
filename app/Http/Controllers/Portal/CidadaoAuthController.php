<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Cidadao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CidadaoAuthController extends Controller
{
    public function showLogin(string $ug): Response
    {
        return Inertia::render('Portal/Auth/Login', [
            'ugCodigo' => $ug,
        ]);
    }

    public function login(Request $request, string $ug)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $cidadao = Cidadao::where('email', $data['email'])->where('ativo', true)->first();
        if (! $cidadao || ! Hash::check($data['password'], $cidadao->senha)) {
            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }

        Auth::guard('cidadao')->login($cidadao, true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function showRegister(string $ug): Response
    {
        return Inertia::render('Portal/Auth/Cadastrar', [
            'ugCodigo' => $ug,
        ]);
    }

    public function register(Request $request, string $ug)
    {
        $data = $request->validate([
            'nome'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('portal_cidadaos', 'email')],
            'cpf'      => ['nullable', 'string', 'max:14'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $cidadao = Cidadao::create([
            'nome'     => $data['nome'],
            'email'    => $data['email'],
            'cpf'      => $data['cpf'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'senha'    => Hash::make($data['password']),
            'ativo'    => true,
        ]);

        Auth::guard('cidadao')->login($cidadao, true);
        $request->session()->regenerate();

        return redirect()->intended('/')->with('success', 'Cadastro realizado. Bem-vindo!');
    }

    public function logout(Request $request, string $ug)
    {
        Auth::guard('cidadao')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
