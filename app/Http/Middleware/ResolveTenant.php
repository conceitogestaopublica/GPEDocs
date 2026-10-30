<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenant\LandlordUrl;
use App\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extrai o subdomain da requisição e ativa o tenant correspondente — mesmo
 * método do gpe2: o tenant é a linha de `tenants` (landlord) que casa com
 * (domain, subdomain) do host.
 *
 *   paraguacu.gpedocs.com.br  →  domain='gpedocs.com.br', subdomain='paraguacu'
 *   www / api / apex          →  não seta tenant
 *   localhost:8090 / IP       →  dev: domain=':8090', subdomain='localhost'
 *
 * O painel landlord (login de super-admin, CRUD de tenants) NÃO existe neste
 * projeto — vive só no gpe2. Tudo que cair em /landlord/* (ou tenant não
 * encontrado) é mandado para lá via LANDLORD_URL.
 */
class ResolveTenant
{
    /** Subdomains "reservados" que não correspondem a tenants. */
    private const SUBDOMAINS_RESERVADOS = ['www', 'admin', 'api'];

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $landlord = LandlordUrl::de($request);

        // Única definição de "é ambiente local" — cobre localhost e IP cru.
        $isLocal = $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP);

        $subdomain = $isLocal ? 'localhost' : $this->extractSubdomain($host);

        // Coluna `domain` do tenant: em dev guarda a porta (':8090'); em produção,
        // o host sem o subdomain (paraguacu.gpedocs.com.br → gpedocs.com.br).
        $dominio = $isLocal
            ? ':'.$request->getPort()
            : ($subdomain === null ? '' : substr($host, strlen($subdomain) + 1));

        // ── Painel landlord: só existe no gpe2 ────────────────────────────────
        // (NÃO casa 'sso/landlord' — consumo SSO do lado tenant, que precisa do tenant.)
        if ($request->is('landlord', 'landlord/*') || (! $isLocal && $subdomain === 'admin')) {
            return $this->paraLandlord($landlord, '/landlord/tenants');
        }

        // www / api / apex → não ativa tenant (landing/API).
        if (! $isLocal && (! $subdomain || in_array($subdomain, self::SUBDOMAINS_RESERVADOS, true))) {
            return $next($request);
        }

        $tenant = $this->resolveTenant($dominio, $subdomain);

        if (! $tenant) {
            // Tenant não existe ou está inativo → login do painel landlord (gpe2).
            return $this->paraLandlord(
                $landlord,
                '/landlord/login',
                "Nenhum tenant encontrado para o endereço '{$subdomain}{$dominio}'."
            );
        }

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }

    /**
     * Redireciona para o painel do gpe2. Sem LANDLORD_URL não há destino seguro
     * (relativo cairia neste mesmo host, em loop) → 404 explícito.
     */
    private function paraLandlord(string $landlord, string $path, ?string $msg = null): Response
    {
        if ($landlord === '') {
            abort(404, $msg ?? '');
        }

        return redirect()->away($landlord.$path);
    }

    /**
     * Primeiro rótulo do host, ou null quando não há subdomain.
     *
     * O apex de TLD composto (gpedocs.com.br) tem 3 rótulos e é indistinguível de
     * sub.dominio.tld pela contagem — só TENANT_DOMINIO_BASE resolve.
     */
    private function extractSubdomain(string $host): ?string
    {
        $base = (string) config('multitenancy.dominio_base');

        // O default de dev é ':8090' (marcador de porta), não um domínio.
        if (str_contains($base, '.') && $host === $base) {
            return null;
        }

        $partes = explode('.', $host);
        // Precisa de pelo menos 3 partes (sub.dominio.tld)
        if (count($partes) < 3) {
            return null;
        }

        return $partes[0];
    }

    /**
     * Tenant de (domain, subdomain). O cache guarda SÓ O ID — nunca o model, que levaria a
     * senha do banco para o disco; e o tenant é relido pela chave primária a cada request,
     * então desativado no gpe2 deixa de valer na hora. 0 marca "não existe" (TTL curto: o
     * tenant recém-criado no painel aparece rápido). A porta entra na chave via $dominio,
     * senão duas instâncias dev em portas diferentes se misturam.
     */
    private function resolveTenant(string $dominio, string $subdomain): ?Tenant
    {
        $buscarId = fn (): int => (int) Tenant::active()
            ->where('domain', $dominio)
            ->where('subdomain', $subdomain)
            ->value('id');

        try {
            $cache = Cache::store('file');
            $chave = "tenant:id:{$dominio}.{$subdomain}";
            $id = $cache->get($chave);
            if (! is_int($id)) {
                $id = $buscarId();
                $cache->put($chave, $id, $id === 0 ? 60 : 300);
            }
        } catch (\Throwable) {
            $id = $buscarId(); // cache fora do ar não derruba a app
        }

        return $id > 0 ? Tenant::active()->find($id) : null;
    }
}
