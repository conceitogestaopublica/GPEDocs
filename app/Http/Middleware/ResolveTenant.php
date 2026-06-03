<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extrai o subdomain da requisição e ativa o tenant correspondente.
 *
 *   taquaracu.gpe.com.br  →  procura tenant onde subdomain='taquaracu'
 *   www.gpe.com.br        →  rota landlord (super-admin) — não seta tenant
 *   gpe.com.br            →  idem
 *   localhost / 127.0.0.1 →  usa env('TENANT_DEFAULT_DOMAIN') se definido (dev)
 */
class ResolveTenant
{
    /** Subdomains "reservados" que não correspondem a tenants. */
    private const SUBDOMAINS_LANDLORD = ['www', 'admin', 'api'];

    public static $LANDLORD_URL = '';
    public static $LANDLORD_PORT = '';

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        self::$LANDLORD_URL = $request->host() !== 'localhost' ? $host :
            config('multitenancy.dev_landlord_url');

        self::$LANDLORD_PORT = $request->getPort();
        $isLocal = $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP);
        $subdomain = $isLocal ? 'localhost' : $this->extractSubdomain($host);
        $domain = $isLocal ? '' : $this->extractDomain($host);

        // Host que serve o painel landlord: localhost/IP (dev) ou subdomínio 'admin'.
        $isAdminHost = $isLocal || $subdomain === 'admin';

        // ── Rotas do painel landlord (prefixo /landlord) ──────────────────────
        // Só são servidas no host admin. Sob host de tenant (ou www/api) o painel
        // NÃO existe — senão o login/CRUD do super-admin ficaria acessível pelo
        // subdomínio de QUALQUER município. Redireciona para o host admin canônico,
        // preservando path+query. (NÃO casa 'sso/landlord' — consumo SSO do lado
        // tenant, que precisa do tenant.)
        dd($subdomain,
$domain);
        if ($request->is('landlord', 'landlord/*')) {
            if ($isAdminHost) {
                return $next($request);
            }
            $landlordUrl = config('multitenancy.landlord_url');
            if (!$landlordUrl) {
                abort(404);
            }

            return redirect()->away(rtrim($landlordUrl, '/')/*.$request->getRequestUri()*/);
        }
        // ── Demais paths (área de tenant) ─────────────────────────────────────
        // Em dev/local (localhost, 127.0.0.1, IP), opcionalmente força tenant pelo .env.
        // Sem TENANT_DEFAULT_DOMAIN, segue como landlord (sem tenant).
        if (!$isLocal) {
            // Host do painel admin fora de /landlord/* → manda para o CRUD de tenants.
            if ($subdomain === 'admin') {
                return redirect('/landlord/tenants');
            }
            // www / api / domínio raiz → não ativa tenant (landing/API).
            if (! $subdomain || in_array($subdomain, self::SUBDOMAINS_LANDLORD, true)) {
                return $next($request);
            }
        }

        $tenant = $this->resolveTenant($domain, $subdomain, $isLocal);

        if (!$tenant) {
            // Tenant não existe ou está inativo. Redireciona para o login do
            // painel admin (landlord) com a mensagem explícita na query string.
            // Cross-domain: o host 'admin' está em SUBDOMAINS_LANDLORD, então o
            // ResolveTenant deixa passar lá — sem risco de loop de redirect.
            $msg = "Nenhum tenant encontrado para o endereço '{$subdomain}'.";

            $landlordUrl = $host === 'localhost'
                ? config('multitenancy.dev_landlord_url')
                : config('multitenancy.landlord_url');

            // Sem URL de landlord configurada não há para onde redirecionar com
            // segurança (relativo causaria loop no subdomínio inválido) → 404.
            if (!$landlordUrl) {
                abort(404, $msg);
            }
            return redirect()->route('landlord.login')->withErrors([$msg]);
        }

        app(TenantContext::class)->set($tenant);
        return $next($request);
    }

    private function extractDomain(string $host): ?string
    {
        $partes = explode('.', $host);
        // Precisa de pelo menos 3 partes (sub.dominio.tld)
        if (count($partes) < 3) {
            return null;
        }
        return $partes[1];
    }

    private function extractSubdomain(string $host): ?string
    {
        $partes = explode('.', $host);
        // Precisa de pelo menos 3 partes (sub.dominio.tld)
        if (count($partes) < 3) {
            return null;
        }
        return $partes[0];
    }

    /** Cache de 5min para evitar query no landlord a cada request. */
    private function resolveTenant(string $domain, string $subdomain, $isLocal = false): ?Tenant
    {
        try {
            return Cache::store('file')->remember(
                "tenant:subdomain:{$domain}.{$subdomain}",  300,
                fn () => Tenant::active()
                    ->where('domain', $isLocal ? ":" . self::$LANDLORD_PORT : "$domain.com.br" )
                    ->where('subdomain', $subdomain)
                    ->first()
            );
        } catch (\Throwable $e) {
            // Se cache falhar, consulta direto (não derruba a app)
            return Tenant::active()
                ->where('domain', $isLocal ? ":" . self::$LANDLORD_PORT : "$domain.com.br" )
                ->where('subdomain', $subdomain)
                ->first();
        }
    }
}
