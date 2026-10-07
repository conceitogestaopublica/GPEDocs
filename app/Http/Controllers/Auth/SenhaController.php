<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recuperação de senha pelo próprio usuário. O link "Esqueceu a senha?" da tela de
 * login apontava para /forgot-password, que não existia.
 */
class SenhaController extends Controller
{
    public function solicitar(): Response
    {
        return Inertia::render('Auth/EsqueciSenha', ['status' => session('status')]);
    }

    public function enviar(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Resposta igual exista ou não a conta: não revela quais e-mails estão cadastrados.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.');
    }

    public function formulario(Request $request, string $token): Response
    {
        return Inertia::render('Auth/RedefinirSenha', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function redefinir(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $senha) {
                $user->forceFill([
                    'password'       => Hash::make($senha),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Link inválido ou expirado. Peça um novo.']);
        }

        return redirect()->route('login')->with('success', 'Senha redefinida. Entre com a nova senha.');
    }
}
