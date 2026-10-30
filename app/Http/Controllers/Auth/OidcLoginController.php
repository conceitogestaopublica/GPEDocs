<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Listeners\RegistrarAcesso;
use App\Models\User;
use App\Services\Auth\EntradaService;
use App\Services\Auth\OidcCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Entrada do servidor pelo Login Único gov.br ou pelo Google (TR 4.7, 4.8.2,
 * 4.8.4). O servidor precisa já estar cadastrado: gov.br é casado pelo CPF
 * (o "sub" do gov.br), Google pelo e-mail confirmado. Quem tem verificação em
 * duas etapas ativa continua precisando do código.
 */
class OidcLoginController extends Controller
{
    public function redirecionar(Request $request, string $provedor)
    {
        abort_unless(OidcCliente::ativo($provedor), 404);

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verificador = Str::random(64);
        $request->session()->put("oidc.$provedor", [
            'state' => $state, 'nonce' => $nonce, 'verificador' => $verificador,
            'lembrar' => $request->boolean('lembrar'), 'ate' => time() + 600,
        ]);

        try {
            return redirect()->away((new OidcCliente($provedor))->urlAutorizacao($this->urlRetorno($provedor), $state, $nonce, $verificador));
        } catch (Throwable $e) {
            Log::warning("Provedor de entrada $provedor indisponível", ['erro' => $e->getMessage()]);

            return $this->falhar(($provedor === 'govbr' ? 'O gov.br' : 'O Google') . ' não respondeu. Tente de novo em instantes ou entre com CPF e senha.');
        }
    }

    public function retorno(Request $request, string $provedor, EntradaService $entrada)
    {
        abort_unless(OidcCliente::ativo($provedor), 404);
        $nome = $provedor === 'govbr' ? 'gov.br' : 'Google';

        $sessao = $request->session()->pull("oidc.$provedor");
        if (! is_array($sessao) || ($sessao['ate'] ?? 0) < time()
            || ! hash_equals((string) $sessao['state'], (string) $request->query('state'))) {
            return $this->falhar("A entrada pelo $nome expirou. Tente de novo.");
        }
        if ($request->query('error')) {
            return $this->falhar("A entrada pelo $nome foi cancelada.");
        }

        try {
            $dados = (new OidcCliente($provedor))->identificar(
                (string) $request->query('code'), $this->urlRetorno($provedor), $sessao['verificador'], $sessao['nonce'],
            );
        } catch (Throwable $e) {
            Log::warning("Entrada pelo $nome recusada", ['erro' => $e->getMessage()]);

            return $this->falhar("Não foi possível confirmar sua identidade pelo $nome.");
        }

        $user = $provedor === 'govbr' ? $this->peloCpf((string) ($dados['sub'] ?? '')) : $this->peloEmail($dados);
        if (! $user) {
            RegistrarAcesso::recusa(null, "sem usuário cadastrado para a conta $nome", $provedor,
                $provedor === 'govbr' ? null : ($dados['email'] ?? null));

            return $this->falhar("Sua conta $nome não corresponde a nenhum servidor cadastrado. Procure o administrador.");
        }

        return $entrada->concluir($request, $user, (bool) $sessao['lembrar'], $provedor);
    }

    private function peloCpf(string $cpf): ?User
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return null;
        }
        $achados = User::query()->whereNotNull('cpf')
            ->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [$cpf])->limit(2)->get();

        return $achados->count() === 1 ? $achados->first() : null;
    }

    /** Só com e-mail confirmado pelo Google. */
    private function peloEmail(array $dados): ?User
    {
        $email = mb_strtolower(trim((string) ($dados['email'] ?? '')));
        if ($email === '' || ($dados['email_verified'] ?? false) !== true) {
            return null;
        }

        return User::whereRaw('lower(email) = ?', [$email])->first();
    }

    private function urlRetorno(string $provedor): string
    {
        return url("/login/oidc/$provedor/retorno");
    }

    private function falhar(string $mensagem)
    {
        return redirect()->route('login')->withErrors(['login' => $mensagem]);
    }
}
