<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $dados = $request->validate([
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Aceita e-mail OU CPF (com ou sem pontuação) no mesmo campo.
        $email = $this->emailDoLogin(trim($dados['login']));
        if ($email === null) {
            return back()->withErrors([
                'login' => 'Informe um e-mail válido ou um CPF com 11 dígitos.',
            ])->onlyInput('login');
        }
        if ($email === false) {
            return back()->withErrors([
                'login' => 'Este CPF está vinculado a mais de um usuário. Entre com o seu e-mail.',
            ])->onlyInput('login');
        }

        if (! Auth::attempt(['email' => $email, 'password' => $dados['password']], $request->boolean('remember'))) {
            return back()->withErrors([
                'login' => 'As credenciais informadas não correspondem aos nossos registros.',
            ])->onlyInput('login');
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

    /**
     * E-mail da conta a partir do que foi digitado no campo de login.
     *
     *   e-mail           → ele mesmo (a validação da senha fica com o Auth::attempt)
     *   CPF (11 dígitos) → e-mail do usuário com esse CPF; `users.cpf` é texto livre
     *                      (com ou sem máscara), então compara só os dígitos
     *
     * Retorna null quando não é nem e-mail nem CPF, e false quando o CPF está em mais de
     * um usuário (ambíguo — não dá para escolher por ele). CPF sem usuário devolve um
     * e-mail vazio: o attempt falha com a mesma mensagem de senha errada, sem revelar se
     * o CPF existe.
     */
    private function emailDoLogin(string $login): string|false|null
    {
        if (str_contains($login, '@')) {
            return filter_var($login, FILTER_VALIDATE_EMAIL) ? $login : null;
        }

        $cpf = preg_replace('/\D/', '', $login);
        if (strlen($cpf) !== 11) {
            return null;
        }

        $conn = User::query()->getConnection();
        $soDigitos = $conn->getDriverName() === 'pgsql'
            ? "regexp_replace(cpf, '[^0-9]', '', 'g')"
            : "regexp_replace(cpf, '[^0-9]', '')";

        $emails = User::query()
            ->whereNotNull('cpf')
            ->whereRaw("{$soDigitos} = ?", [$cpf])
            ->limit(2)
            ->pluck('email');

        if ($emails->count() > 1) {
            return false;
        }

        return (string) ($emails->first() ?? '');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
