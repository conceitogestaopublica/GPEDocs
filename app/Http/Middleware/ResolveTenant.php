<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extrai o subdomain da requisição e ativa o tenant correspondente.
 *
 *   taquaracu.gpe.com.br  →  procura tenant onde subdomain='taquaracu'
 *   www.gpe.com.br        →  rota landlord (super-admin) — não seta tenant
 *   gpe.com.br            →  idem (apex; exige TENANT_DOMINIO_BASE configurado,
 *                            senão 'gpe' é lido como subdomain de tenant)
 *   localhost / 127.0.0.1 →  host do painel landlord em dev
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

        self::$LANDLORD_PORT = $request->getPort();

        // Única definição de "é ambiente local" — cobre localhost e IP cru.
        $isLocal = $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP);

        // URL ABSOLUTA do painel landlord — é lida fora daqui para montar o link
        // "voltar ao painel" na UI do tenant, então precisa de esquema e apontar
        // para o painel canônico, não para o host corrente.
        self::$LANDLORD_URL = $isLocal
            ? $request->getSchemeAndHttpHost()   // dev: painel no mesmo host (http://localhost:8080)
            : $this->landlordUrl($request);

        $subdomain = $isLocal ? 'localhost' : $this->extractSubdomain($host);

        // Coluna `domain` do tenant: em dev guarda a porta (':8080'); em produção,
        // o host sem o subdomain (paraguacu.gpedocs.com.br → gpedocs.com.br).
        $dominio = $isLocal
            ? ':'.self::$LANDLORD_PORT
            : ($subdomain === null ? '' : substr($host, strlen($subdomain) + 1));

        // Host que serve o painel landlord: localhost/IP (dev) ou subdomínio 'admin'.
        $isAdminHost = $isLocal || $subdomain === 'admin';

        // ── Rotas do painel landlord (prefixo /landlord) ──────────────────────
        // Só são servidas no host admin. Sob host de tenant (ou www/api) o painel
        // NÃO existe — senão o login/CRUD do super-admin ficaria acessível pelo
        // subdomínio de QUALQUER município. Redireciona para o host admin canônico,
        // preservando path+query. (NÃO casa 'sso/landlord' — consumo SSO do lado
        // tenant, que precisa do tenant.)
        if ($request->is('landlord', 'landlord/*')) {
            if ($isAdminHost) {
                // Cookie de sessão próprio do painel. Em produção landlord e tenant
                // vivem em hosts distintos, e o cookie host-only já os separa; em dev
                // dividem localhost:8080. Sem separar, o `login_web_*` do tenant fica
                // visível aqui e o handler de sessão do banco — que grava
                // sessions.user_id — tenta materializar `usuario` na conexão landlord.
                // Este middleware roda antes do StartSession (prepend no grupo web),
                // então trocar a config aqui ainda tem efeito.
                config(['session.cookie' => config('session.cookie').'_landlord']);

                // E a sessão do painel vai SEMPRE para o banco do landlord. Sem isto ela
                // segue a conexão default do processo, que aqui ninguém troca (o painel não
                // ativa tenant, de propósito) — então ela herda o que estiver no
                // `DB_CONNECTION`. Em produção isso ainda era o MariaDB do legado, e o painel
                // morria com "getaddrinfo for mariadb10 failed ... select * from sessions",
                // enquanto os municípios funcionavam: neles o TenantContext troca a default
                // antes do StartSession. O painel é do landlord; a sessão dele também.
                config(['session.connection' => 'landlord']);

                // O cookie "lembrar-me" do tenant também chega aqui e o SessionGuard
                // cai nele quando a sessão não tem usuário — consultando `usuario` na
                // conexão landlord, onde a tabela não existe. Some com ele SÓ nesta
                // request (o cookie do browser fica intacto, o tenant segue logado).
                $request->cookies->remove(
                    'remember_'.config('auth.defaults.guard').'_'.sha1(SessionGuard::class)
                );

                return $next($request);
            }

            $landlordUrl = $this->landlordUrl($request);

            // Sem URL canônica não há destino seguro: mandar para o próprio host
            // cairia neste mesmo branch de novo, em loop.
            if ($landlordUrl === '') {
                abort(404);
            }

            return redirect()->away($landlordUrl.$request->getRequestUri());
        }

        // ── Demais paths (área de tenant) ─────────────────────────────────────
        if (! $isLocal) {
            // Host do painel admin fora de /landlord/* → manda para o CRUD de tenants.
            if ($subdomain === 'admin') {
                return redirect('/landlord/tenants');
            }
            // www / api / apex → não ativa tenant (landing/API). 'admin' já saiu acima.
            if (! $subdomain || in_array($subdomain, self::SUBDOMAINS_LANDLORD, true)) {
                return $next($request);
            }
        }

        $tenant = $this->resolveTenant($dominio, $subdomain);

        if (! $tenant) {
            // Tenant não existe ou está inativo → login do painel landlord.
            $msg = "Nenhum tenant encontrado para o endereço '{$subdomain}'.";

            // Já estamos no host do painel (dev): relativo basta e preserva a sessão,
            // então a mensagem de erro sobrevive ao redirect.
            if ($isAdminHost) {
                return redirect()->route('landlord.login')->withErrors([$msg]);
            }

            $landlordUrl = $this->landlordUrl($request);

            // Sem URL de landlord configurada não há para onde redirecionar com
            // segurança: relativo voltaria para este subdomínio inválido e o branch
            // de /landlord/* acima devolveria para cá → loop. Melhor 404 explícito.
            if ($landlordUrl === '') {
                abort(404, $msg);
            }

            // Cross-domain: a sessão é host-only, withErrors não atravessa.
            return redirect()->away($landlordUrl.'/landlord/login');
        }

        app(TenantContext::class)->set($tenant);

        return $next($request);
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
     * O apex de TLD composto (gpe.com.br) tem 3 rótulos e é indistinguível de
     * sub.dominio.tld pela contagem — só TENANT_DOMINIO_BASE resolve.
     */
    private function extractSubdomain(string $host): ?string
    {
        $base = (string) config('multitenancy.dominio_base');

        // O default de dev é ':8080' (marcador de porta), não um domínio.
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
