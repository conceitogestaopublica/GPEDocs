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
        \App\Support\Captcha::validar($request, 'senha');

        // Resposta igual exista ou não a conta: não revela quais e-mails estão cadastrados.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.');
    }

    /** Senha vencida ou provisória: a pessoa acabou de entrar com ela e cria a nova antes de seguir. */
    public function expirada(Request $request)
    {
        $user = $this->usuarioDaTroca($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/SenhaExpirada', ['nome' => $user->name, 'provisoria' => (bool) $user->senha_trocar,
            'regra' => \App\Support\PoliticaSenha::descricao()]);
    }

    public function trocarExpirada(Request $request, \App\Services\Auth\EntradaService $entrada)
    {
        $user = $this->usuarioDaTroca($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => 'O tempo para criar a nova senha acabou. Entre de novo.']);
        }
        $d = $request->validate(['password' => ['required', 'string', 'confirmed', \App\Support\PoliticaSenha::regra()]]);
        \App\Support\PoliticaSenha::conferirHistorico($user, $d['password']);
        \App\Support\PoliticaSenha::gravar($user, $d['password']);
        \App\Support\HistoricoUsuario::registrar($user, 'Senha', null, $request->session()->get('troca_senha.provisoria') ? 'Senha provisória trocada' : 'Senha vencida trocada');
        $dados = $request->session()->pull('troca_senha');

        // segue a entrada normal (verificação em duas etapas, escolha da UG)
        return $entrada->concluir($request, $user->refresh(), (bool) $dados['lembrar'], $dados['origem']);
    }

    private function usuarioDaTroca(Request $request): ?User
    {
        $t = $request->session()->get('troca_senha');
        if (! $t || ($t['ate'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget('troca_senha');

            return null;
        }

        return User::find($t['user_id']);
    }

    public function formulario(Request $request, string $token): Response
    {
        return Inertia::render('Auth/RedefinirSenha', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'regra' => \App\Support\PoliticaSenha::descricao(),
        ]);
    }

    public function redefinir(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', \App\Support\PoliticaSenha::regra()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $senha) {
                \App\Support\PoliticaSenha::conferirHistorico($user, $senha);
                \App\Support\PoliticaSenha::gravar($user, $senha);
                $user->forceFill(['remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Link inválido ou expirado. Peça um novo.']);
        }

        return redirect()->route('login')->with('success', 'Senha redefinida. Entre com a nova senha.');
    }
}
