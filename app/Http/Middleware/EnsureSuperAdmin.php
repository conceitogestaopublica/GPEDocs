<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Landlord\SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware das rotas do painel landlord.
 *
 * Verifica, a cada request:
 *   1. session('super_admin_id') existe → senão, redirect para login.
 *   2. O registro ainda existe e está ativo → senão, limpa a sessão e força
 *      novo login com mensagem ("Sua conta foi desativada").
 *
 * A checagem é feita fresh todo request (sem cache) — decisão #4 do spec.
 * Isso permite desativar um super-admin no painel e ter efeito imediato no
 * próximo clique dele, sem janela de cache. Como o painel é baixa-frequência
 * (poucos super-admins), o custo é desprezível.
 *
 * Para sessões SSO dentro de tenants, há um middleware separado com cache
 * (VerifyActiveSuperAdminSSO, step 7 do roteiro).
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('super_admin_id');

        if (!$id) {
            return redirect()->route('landlord.login');
        }

        $admin = SuperAdmin::active()->find($id);

        if (!$admin) {
            // Desativado entre requests — limpa sessão e força novo login.
            $request->session()->forget(['super_admin_id', 'super_admin_email']);
            $request->session()->regenerate();
            return redirect()->route('landlord.login')
                ->withErrors(['email' => 'Sua conta foi desativada.']);
        }

        return $next($request);
    }
}
