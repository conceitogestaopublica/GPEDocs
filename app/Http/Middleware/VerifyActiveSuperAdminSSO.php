<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificação contínua da sessão SSO dentro do tenant (spec §4.5, decisão #4).
 *
 * Quando um super-admin entra num tenant via SSO jump, o SSOConsumeController
 * grava `sso_super_admin_id` na sessão do tenant. A sessão é um cookie host-only
 * — independente da sessão do landlord. Se o super-admin for desativado no painel
 * landlord DEPOIS do jump, nada nesse cookie expira sozinho.
 *
 * Este middleware fecha essa janela: a cada request do tenant, se a sessão é uma
 * sessão SSO, revalida que o super-admin ainda está ativo no banco landlord. Se
 * não estiver, mata a sessão e manda pro login.
 *
 * Aplicado globalmente no grupo `web` (bootstrap/app.php). Em sessões que NÃO são
 * SSO (login comum do tenant, ou qualquer rota do landlord), faz short-circuit no
 * primeiro `if` — custo é uma leitura de sessão.
 *
 * O cache de 60s evita bater no landlord a cada clique. Invalidação imediata ao
 * desativar um super-admin: chamar `Cache::forget("super_admin_active:{$id}")` no
 * ponto onde `active` é alterado.
 */
class VerifyActiveSuperAdminSSO
{
    public function handle(Request $request, Closure $next): Response
    {
        $superId = $request->session()->get('sso_super_admin_id');

        if (!$superId) {
            return $next($request); // sessão não é SSO — ignora.
        }

        $ativo = Cache::remember(
            "super_admin_active:{$superId}",
            60,
            fn (): bool => DB::connection('landlord')->table('super_admins')
                ->where('id', $superId)
                ->where('active', true)
                ->exists()
        );

        if (!$ativo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'Sessão SSO encerrada — super-admin desativado.',
            ]);
        }

        return $next($request);
    }
}
