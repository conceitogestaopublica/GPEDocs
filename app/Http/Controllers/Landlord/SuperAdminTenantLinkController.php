<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SuperAdmin;
use App\Models\Landlord\SuperAdminTenantLink;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD dos vínculos super-admin × usuário de tenant (super_admin_tenant_links).
 *
 * Cada vínculo diz: "o super-admin X, ao entrar no tenant Y via SSO, opera como
 * o usuário de email Z". O flag `default` resolve qual email usar quando o SSO
 * jump não especifica um vínculo (LandlordTenantSSOController::create).
 *
 * Regras de `default` (escopo = par super_admin_id + tenant_id):
 *   - só um vínculo por par pode ser default;
 *   - o primeiro vínculo criado para um par vira default automaticamente
 *     (senão o SSO sem linkId falharia com E_SSO_NO_LINK);
 *   - ao excluir o default, promove o vínculo remanescente mais antigo do par.
 *
 * Painel Blade (mesmo padrão de landlord.tenants), protegido por 'super-admin'.
 */
class SuperAdminTenantLinkController extends Controller
{
    public function index()
    {
        $links = DB::connection('landlord')->table('super_admin_tenant_links as l')
            ->join('super_admins as sa', 'l.super_admin_id', '=', 'sa.id')
            ->join('tenants as t', 'l.tenant_id', '=', 't.id')
            ->select(
                'l.id', 'l.super_admin_id', 'l.tenant_id', 'l.tenant_user_email',
                'l.default', 'l.observacoes',
                'sa.email as sa_email', 'sa.nome as sa_nome', 'sa.active as sa_active',
                't.domain as t_domain', 't.nome as t_nome', 't.active as t_active'
            )
            ->orderBy('sa.email')->orderBy('t.domain')->orderByDesc('l.default')
            ->get();

        $superAdmins = SuperAdmin::orderBy('email')->get(['id', 'email', 'nome', 'active']);
        $tenants     = Tenant::orderBy('domain')->get(['id', 'domain', 'nome', 'active']);

        return view('landlord.links.index', compact('links', 'superAdmins', 'tenants'));
    }

    /** JSON do vínculo para popular o modal de edição. */
    public function show(int $id)
    {
        $l = SuperAdminTenantLink::findOrFail($id);
        return response()->json([
            'id'                => $l->id,
            'super_admin_id'    => $l->super_admin_id,
            'tenant_id'         => $l->tenant_id,
            'tenant_user_email' => $l->tenant_user_email,
            'default'           => (bool) $l->default,
            'observacoes'       => $l->observacoes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'super_admin_id'    => 'required|integer|exists:landlord.super_admins,id',
            'tenant_id'         => 'required|integer|exists:landlord.tenants,id',
            'tenant_user_email' => 'required|email|max:150',
            'observacoes'       => 'nullable|string|max:5000',
        ]);

        // A validação não faz cast — vêm como string do form. Normaliza para int
        // (a coluna é inteira e os helpers exigem int).
        $v['super_admin_id'] = (int) $v['super_admin_id'];
        $v['tenant_id']      = (int) $v['tenant_id'];

        if ($this->duplicado($v['super_admin_id'], $v['tenant_id'], $v['tenant_user_email'])) {
            return back()->withErrors([
                'tenant_user_email' => 'Já existe um vínculo com este email para este super-admin e tenant.',
            ])->withInput();
        }

        // Primeiro vínculo do par sempre vira default; senão respeita o checkbox.
        $parTemDefault = SuperAdminTenantLink::where('super_admin_id', $v['super_admin_id'])
            ->where('tenant_id', $v['tenant_id'])->where('default', true)->exists();
        $default = $request->boolean('default') || ! $parTemDefault;

        $link = SuperAdminTenantLink::create($v + ['default' => $default]);

        if ($default) {
            $this->limparOutrosDefaults($link);
        }

        return back()->with('success', 'Vínculo criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $link = SuperAdminTenantLink::findOrFail($id);

        $v = $request->validate([
            'tenant_user_email' => 'required|email|max:150',
            'observacoes'       => 'nullable|string|max:5000',
        ]);

        // super_admin_id e tenant_id são imutáveis (recriar é mais limpo que migrar).
        if ($this->duplicado($link->super_admin_id, $link->tenant_id, $v['tenant_user_email'], $link->id)) {
            return back()->withErrors([
                'tenant_user_email' => 'Já existe um vínculo com este email para este super-admin e tenant.',
            ])->withInput();
        }

        $default = $request->boolean('default');

        // Não permite tirar o default do único vínculo do par (deixaria o SSO
        // sem linkId quebrado). Se é o único, força default=true.
        $totalDoPar = SuperAdminTenantLink::where('super_admin_id', $link->super_admin_id)
            ->where('tenant_id', $link->tenant_id)->count();
        if ($totalDoPar === 1) {
            $default = true;
        }

        $link->update($v + ['default' => $default]);

        if ($default) {
            $this->limparOutrosDefaults($link);
        }

        return back()->with('success', 'Vínculo atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $link = SuperAdminTenantLink::findOrFail($id);
        $eraDefault = (bool) $link->default;
        $superId = $link->super_admin_id;
        $tenantId = $link->tenant_id;

        $link->delete();

        // Se removemos o default e ainda há vínculos para o par, promove o
        // mais antigo — assim o SSO sem linkId continua funcionando.
        if ($eraDefault) {
            $remanescente = SuperAdminTenantLink::where('super_admin_id', $superId)
                ->where('tenant_id', $tenantId)->orderBy('id')->first();
            $remanescente?->update(['default' => true]);
        }

        return back()->with('success', 'Vínculo removido.');
    }

    private function duplicado(int $superId, int $tenantId, string $email, ?int $exceptId = null): bool
    {
        return SuperAdminTenantLink::where('super_admin_id', $superId)
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_email', $email)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /** Garante que só o $link seja default dentro do par (super_admin, tenant). */
    private function limparOutrosDefaults(SuperAdminTenantLink $link): void
    {
        SuperAdminTenantLink::where('super_admin_id', $link->super_admin_id)
            ->where('tenant_id', $link->tenant_id)
            ->where('id', '!=', $link->id)
            ->update(['default' => false]);
    }
}