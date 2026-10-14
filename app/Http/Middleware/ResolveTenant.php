<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
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

    /** URL absoluta do painel landlord (gpe2) — usada fora daqui para links "voltar ao painel". */
    public static $LANDLORD_URL = '';

    public static $LANDLORD_PORT = '';

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        self::$LANDLORD_PORT = $request->getPort();
        self::$LANDLORD_URL = $this->landlordUrl($request);

        // Única definição de "é ambiente local" — cobre localhost e IP cru.
        $isLocal = $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP);

        $subdomain = $isLocal ? 'localhost' : $this->extractSubdomain($host);

        // Coluna `domain` do tenant: em dev guarda a porta (':8090'); em produção,
        // o host sem o subdomain (paraguacu.gpedocs.com.br → gpedocs.com.br).
        $dominio = $isLocal
            ? ':'.self::$LANDLORD_PORT
            : ($subdomain === null ? '' : substr($host, strlen($subdomain) + 1));

        // ── Painel landlord: só existe no gpe2 ────────────────────────────────
        // (NÃO casa 'sso/landlord' — consumo SSO do lado tenant, que precisa do tenant.)
        if ($request->is('landlord', 'landlord/*') || (! $isLocal && $subdomain === 'admin')) {
            return $this->paraLandlord('/landlord/tenants');
        }

        // www / api / apex → não ativa tenant (landing/API).
        if (! $isLocal && (! $subdomain || in_array($subdomain, self::SUBDOMAINS_RESERVADOS, true))) {
            return $next($request);
        }

        $tenant = $this->resolveTenant($dominio, $subdomain);

        if (! $tenant) {
            // Tenant não existe ou está inativo → login do painel landlord (gpe2).
            return $this->paraLandlord(
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
    private function paraLandlord(string $path, ?string $msg = null): Response
    {
        if (self::$LANDLORD_URL === '') {
            abort(404, $msg ?? '');
        }

        return redirect()->away(self::$LANDLORD_URL.$path);
    }

    /**
     * URL absoluta do painel landlord, com esquema garantido.
     *
     * Location sem esquema é interpretado como caminho relativo pelo browser
     * (vira /landlord/admin.exemplo.com.br), então herda o esquema da request
     * quando a config vier só com o host.
     */
    private function landlordUrl(Request $request): string
    {
        $url = (string) config('multitenancy.landlord_url');

        if ($url === '') {
            return '';
        }

        if (! str_contains($url, '://')) {
            $url = $request->getScheme().'://'.$url;
        }

        return rtrim($url, '/');
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
     * Cache do tenant por (domain, subdomain) — a porta entra na chave via
     * $dominio, senão duas instâncias dev em portas diferentes se misturam.
     */
    private function resolveTenant(string $dominio, string $subdomain): ?Tenant
    {
        $consulta = fn (): ?Tenant => Tenant::active()
            ->where('domain', $dominio)
            ->where('subdomain', $subdomain)
            ->first();

        try {
            $cache = Cache::store('file');
            $chave = "tenant:subdomain:{$dominio}.{$subdomain}";

            // `false` marca "não existe": Cache::remember trata null como ausência
            // e repetiria a query no landlord a cada request. TTL curto no miss para
            // o tenant recém-criado no painel aparecer rápido.
            $tenant = $cache->get($chave);

            if ($tenant === null) {
                $tenant = $consulta() ?: false;
                $cache->put($chave, $tenant, $tenant === false ? 60 : 300);
            }

            return $tenant ?: null;
        } catch (\Throwable $e) {
            // Se cache falhar, consulta direto (não derruba a app)
            return $consulta();
        }
    }
}
