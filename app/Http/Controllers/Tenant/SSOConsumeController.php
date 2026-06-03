<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use App\Models\Landlord\SuperAdmin;
use App\Models\Landlord\SuperAdminTenantLink;
use App\Models\Landlord\TenantSSOToken;
use App\Models\User;
use App\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Consome o token SSO emitido pelo landlord, dentro do contexto do tenant.
 *
 * Sequência de validações (spec §4.4 e §3):
 *   1. ?t presente                                  → E_SSO_TOKEN_INVALID
 *   2. token_hash existe                            → E_SSO_TOKEN_INVALID
 *   3. token não usado                              → E_SSO_TOKEN_USED
 *   4. token não expirou                            → E_SSO_TOKEN_EXPIRED
 *   5. tenant atual == tenant_id do token           → E_SSO_TOKEN_TENANT_MISMATCH
 *   6. super-admin ainda existe e está ativo        → E_SSO_ADMIN_INACTIVE
 *   7. link ainda existe                            → E_SSO_LINK_REMOVED
 *   8. usuario espelho existe no tenant             → E_SSO_NO_MIRROR (404 + 2 botões)
 *
 * Em (8) falhando, o token é marcado used_at mesmo assim para não permitir
 * retry. Quem precisa entrar tem que voltar ao painel e tentar de novo (que
 * emite um novo token) ou usar o login padrão do tenant.
 *
 * Sucesso → Auth::login(), grava gestora_id/exercicio/sso_super_admin_id na
 * sessão do tenant (cookie host-only — independente da sessão do landlord).
 */
class SSOConsumeController extends Controller
{
    public function consume(Request $request, TenantContext $ctx): Response|RedirectResponse
    {
        if (!$ctx->isSet()) {
            // SSO não faz sentido fora do contexto de tenant.
            return $this->errorView('E_SSO_TOKEN_INVALID');
        }

        $tokenCru = (string) $request->query('t', '');
        if ($tokenCru === '') {
            return $this->errorView('E_SSO_TOKEN_INVALID');
        }

        $token = TenantSSOToken::findByRaw($tokenCru);
        if (!$token) {
            return $this->errorView('E_SSO_TOKEN_INVALID');
        }

        if ($token->isUsed()) {
            return $this->errorView('E_SSO_TOKEN_USED');
        }
        if ($token->isExpired()) {
            return $this->errorView('E_SSO_TOKEN_EXPIRED');
        }

        if ((int) $token->tenant_id !== (int) $ctx->id()) {
            return $this->errorView('E_SSO_TOKEN_TENANT_MISMATCH');
        }

        $admin = SuperAdmin::active()->find($token->super_admin_id);
        if (! $admin) {
            return $this->errorView('E_SSO_ADMIN_INACTIVE');
        }

        $link = SuperAdminTenantLink::query()
            ->where('super_admin_id', $token->super_admin_id)
            ->where('tenant_id', $token->tenant_id)
            ->where('tenant_user_email', $token->tenant_user_email)
            ->first();
        if (! $link) {
            return $this->errorView('E_SSO_LINK_REMOVED');
        }

        // Marca o token usado antes de tudo — defesa contra replay em corrida.
        // Mesmo se a próxima etapa falhar (sem espelho), o token está queimado.
        $token->markUsed();

        // Espelho no tenant — usa a conexão default que ResolveTenant já apontou
        // para o banco do tenant atual.
        $userTenant = User::query()
            ->where('email', $token->tenant_user_email)
            ->first();

        if (! $userTenant) {
            return $this->errorView('E_SSO_NO_MIRROR', [
                'admin_email'  => $admin->email,
                'tenant_email' => $token->tenant_user_email,
            ]);
        }

        Auth::login($userTenant);
        $request->session()->regenerate();
        $request->session()->put('gestora_id', $userTenant->gestora_id);
        $request->session()->put('exercicio',  (int) date('Y'));

        // Marca para o middleware VerifyActiveSuperAdminSSO (step 7) e auditoria.
        $request->session()->put('sso_super_admin_id', $admin->id);

        return redirect('/dashboard');
    }

    /**
     * Renderiza a tela de erro padronizada com o código + status HTTP correto.
     * Extra vars (admin_email, tenant_email) são usadas apenas em E_SSO_NO_MIRROR.
     */
    private function errorView(string $codigo, array $extra = []): Response
    {
        $catalogo = [
            'E_SSO_TOKEN_INVALID'         => ['status' => 403, 'titulo' => 'Token inválido',          'mensagem' => 'O token SSO não foi reconhecido. Volte ao painel e tente novamente.'],
            'E_SSO_TOKEN_EXPIRED'         => ['status' => 403, 'titulo' => 'Token expirado',          'mensagem' => 'O token SSO expirou. Volte ao painel e clique novamente para entrar.'],
            'E_SSO_TOKEN_USED'            => ['status' => 403, 'titulo' => 'Token já utilizado',      'mensagem' => 'Este token SSO já foi consumido. Cada token é de uso único — solicite um novo no painel.'],
            'E_SSO_TOKEN_TENANT_MISMATCH' => ['status' => 403, 'titulo' => 'Token emitido para outro tenant', 'mensagem' => 'O token foi gerado para outro município. Volte ao painel e tente de novo.'],
            'E_SSO_ADMIN_INACTIVE'        => ['status' => 403, 'titulo' => 'Conta de super-admin desativada', 'mensagem' => 'Sua conta foi desativada após a emissão do token. Faça login novamente no painel landlord.'],
            'E_SSO_LINK_REMOVED'          => ['status' => 403, 'titulo' => 'Vínculo removido',        'mensagem' => 'O vínculo com este tenant foi removido após a emissão do token. Volte ao painel.'],
            'E_SSO_TENANT_INACTIVE'       => ['status' => 403, 'titulo' => 'Tenant desativado',       'mensagem' => 'Este município foi desativado.'],
            'E_SSO_NO_MIRROR'             => ['status' => 404, 'titulo' => 'Usuário espelho não encontrado', 'mensagem' => 'Não existe um usuário com o email vinculado neste município.'],
        ];

        $info = $catalogo[$codigo] ?? ['status' => 500, 'titulo' => 'Erro desconhecido', 'mensagem' => $codigo];

        // E_SSO_NO_MIRROR oferece dois caminhos (decisão #3): voltar ao
        // painel landlord, ou tentar login padrão do tenant.
        $painelUrl = ResolveTenant::$LANDLORD_URL. '/landlord/tenants';

        $opcoes = $codigo === 'E_SSO_NO_MIRROR' ? [
            ['label' => 'Voltar ao painel landlord', 'url' => $painelUrl,      'tipo' => 'primary'],
            ['label' => 'Tentar login padrão',       'url' => route('login'),  'tipo' => 'secondary'],
        ] : [
            ['label' => 'Voltar ao painel landlord', 'url' => $painelUrl,      'tipo' => 'primary'],
        ];

        return response()
            ->view('sso.erro', [
                'codigo'      => $codigo,
                'titulo'      => $info['titulo'],
                'mensagem'    => $info['mensagem'],
                'opcoes'      => $opcoes,
                'adminEmail'  => $extra['admin_email']  ?? null,
                'tenantEmail' => $extra['tenant_email'] ?? null,
            ], $info['status']);
    }
}
