<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EntradaService;
use App\Support\DocumentoPessoa;
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
            'certificado'   => self::entradaComCertificado($request),
        ]);
    }

    /**
     * Como entrar com certificado neste ambiente: pelo certificado cliente do HTTPS, sem plugin,
     * quando há endereço configurado; senão pela extensão Web PKI, que fora de localhost só funciona
     * com licença. Sem nenhum dos dois o botão não aparece.
     */
    public static function entradaComCertificado(Request $request): ?array
    {
        $url = (string) config('icp_brasil.login_tls.url');
        if ($url !== '') {
            return ['modo' => 'tls', 'url' => $url.'/certificado/entrar'];
        }
        $local = $request->getHost() === 'localhost' || filter_var($request->getHost(), FILTER_VALIDATE_IP);
        if ($local || config('icp_brasil.webpki_licenca') !== '') {
            return ['modo' => 'webpki'];
        }

        return null;
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

        // Aceita e-mail, CPF ou CNPJ (com ou sem pontuação) no mesmo campo.
        $email = $this->emailDoLogin(trim($dados['login']));
        if ($email === null) {
            return back()->withErrors([
                'login' => 'Informe um e-mail válido, um CPF com 11 dígitos ou um CNPJ com 14 dígitos.',
            ])->onlyInput('login');
        }
        if ($email === false) {
            return back()->withErrors([
                'login' => 'Este documento está vinculado a mais de um usuário. Entre com o seu e-mail.',
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
     *   e-mail                    → ele mesmo (a validação da senha fica com o Auth::attempt)
     *   CPF (11) ou CNPJ (14 dígitos) → e-mail do usuário com esse documento; compara só os
     *                               dígitos, porque há documentos com e sem máscara
     *
     * Retorna null quando não é e-mail nem documento, e false quando o documento é ambíguo (regra em
     * User::emailParaLoginPorDocumento: a conta interna vence o externo duplicado). Documento sem usuário
     * devolve um e-mail vazio: o attempt falha com a mesma mensagem de senha errada, sem revelar se
     * o documento existe.
     */
    private function emailDoLogin(string $login): string|false|null
    {
        if (str_contains($login, '@')) {
            return filter_var($login, FILTER_VALIDATE_EMAIL) ? $login : null;
        }

        $documento = DocumentoPessoa::soDigitos($login);
        if (! in_array(strlen($documento), [11, 14], true)) {
            return null;
        }

        return User::emailParaLoginPorDocumento($documento);
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
