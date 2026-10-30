<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EntradaService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'exige_captcha' => \App\Support\Captcha::exigidoNaEntrada($request, 'login'),
            // Botões gov.br / Google só aparecem com as credenciais configuradas
            'provedores'    => array_values(array_filter(\App\Services\Auth\OidcCliente::PROVEDORES,
                fn ($p) => \App\Services\Auth\OidcCliente::ativo($p))),
        ]);
    }

    public function store(Request $request, EntradaService $entrada)
    {
        $dados = $request->validate([
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Depois de algumas senhas erradas deste IP, a entrada passa a pedir o código da imagem.
        if (\App\Support\Captcha::exigidoNaEntrada($request, 'login')) {
            \App\Support\Captcha::validar($request, 'login');
        }

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

        // Confere a senha SEM abrir a sessão: com verificação em duas etapas a
        // sessão só abre depois do código do aplicativo (EntradaService).
        $credenciais = ['email' => $email, 'password' => $dados['password']];
        if (! Auth::validate($credenciais)) {
            \App\Support\Captcha::registrarFalha($request, 'login');
            event(new Failed('web', Auth::getProvider()->retrieveByCredentials(['email' => $email]), $credenciais));

            return back()->withErrors([
                'login' => 'As credenciais informadas não correspondem aos nossos registros.',
            ])->onlyInput('login');
        }

        \App\Support\Captcha::limparFalhas($request, 'login');
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $email]);

        return $entrada->concluir($request, $user, $request->boolean('remember'), 'senha');
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
        \App\Support\Eventos::registrar('ACE.SAIDA', 'acesso', 'Saída do sistema');
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
