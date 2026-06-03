<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SuperAdmin;
use App\Models\Landlord\SuperAdminTenantLink;
use App\Models\Landlord\TenantSSOToken;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Emissão do token SSO para "entrar como" um usuário de tenant a partir do
 * painel landlord.
 *
 * Fluxo (spec §4.3):
 *   1. Confirma super-admin ativo (já garantido pelo middleware super-admin).
 *   2. Localiza o link explícito (ou o default do tenant).
 *   3. Emite token com TTL curto via TenantSSOToken::emit() — só o sha256
 *      vai pro banco; o cru viaja na query string e é descartado em seguida.
 *   4. Redireciona 302 para a URL pública do tenant + ?t=<token>.
 *
 * Consumo é tratado em TenantSSOController (step 6) no contexto do tenant.
 */
class LandlordTenantSSOController extends Controller
{
    public function create(Request $request, int $tenantId, ?int $linkId = null): JsonResponse
    {
        // 1. Super-admin (garantido ativo pelo middleware, mas reapanhamos
        //    o model — precisamos do id e do email pra emitir o token).
        $admin = SuperAdmin::active()->findOrFail($request->session()->get('super_admin_id'));
;
        // 2. Tenant alvo deve existir e estar ativo.
        $tenant = Tenant::active()->findOrFail($tenantId);

        // 3. Vínculo: específico (linkId) ou o default deste par.
        $link = SuperAdminTenantLink::query()
            ->where('super_admin_id', $admin->id)
            ->where('tenant_id', $tenant->id)
            ->when($linkId !== null, fn ($q) => $q->where('id', $linkId))
            ->when($linkId === null, fn ($q) => $q->where('default', true))
            ->first();

        if (! $link) {
            // E_SSO_NO_LINK — só ocorre se o admin tentar entrar num tenant
            // onde não tem vínculo (UI deveria nem mostrar o botão, mas
            // defendemos no backend mesmo assim).
            return response()->json(['success' => false, 'message' => ['sso' => "E_SSO_NO_LINK — Você não tem vínculo cadastrado em {$tenant->nome}."]], 400);
        }

        // 4. Emite o token. O modelo gera o cru, persiste só o hash, e
        //    devolve [persistido, cru].
        [, $tokenCru] = TenantSSOToken::emit(
            admin:           $admin,
            tenant:          $tenant,
            tenantUserEmail: $link->tenant_user_email,
            ip:              $request->ip(),
            ttlSeconds:      (int) config('multitenancy.sso_token_ttl', 30),
        );

        // 5. Redireciona para o tenant. URL fora do app — usar away() pra
        //    não ser tratada como rota interna.
        return response()->json(['success' => true, 'url' => $tenant->url('/sso/landlord?t=' . $tokenCru)]);
    }
}
