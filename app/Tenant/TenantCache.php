<?php

declare(strict_types=1);

namespace App\Tenant;

use Illuminate\Support\Facades\Cache;

/**
 * Helper para cache isolado por tenant. Prefixa toda chave com o domínio
 * do tenant ativo, evitando vazamento entre municípios.
 *
 * Uso:
 *
 *   $valor = TenantCache::remember('balancete-painel', 300, fn() => calcular());
 *   TenantCache::forget('balancete-painel');
 *
 * Internamente vira: "tenant:<dominio>:balancete-painel"
 *
 * Para flush total do tenant (ex: ao migrar):
 *   TenantCache::flushTenant();   — só funciona com cache store que suporta tags (redis/memcached)
 *
 * Em filesystem cache (default), o flush não tem efeito; o prefixo garante isolamento na leitura.
 */
class TenantCache
{
    public static function key(string $key): string
    {
        // Pelo BANCO, não pelo subdomínio: o banco é único por município em qualquer
        // ambiente. Em dev os tenants compartilham `subdomain = localhost` e se separam pela
        // porta — por subdomínio, todos cairiam na mesma chave e o cache vazaria igual.
        $ctx = app(TenantContext::class);
        $subdomain = $ctx->get()?->db_name ?? $ctx->subdomain() ?? 'shared';

        return "tenant:{$subdomain}:{$key}";
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::get(self::key($key), $default);
    }

    public static function put(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return Cache::put(self::key($key), $value, $ttl);
    }

    /**
     * O `$ttl` aceita o mesmo que o `Cache::remember` do Laravel — inclusive
     * `DateTimeInterface`, que é o que `now()->addMinutes(20)` devolve e o que os serviços
     * usam. A assinatura antiga era mais estreita que a do framework e estourava TypeError;
     * ninguém tinha visto porque a classe inteira era inutilizável (ver o histórico do
     * `TenantContext::subdomain()`).
     */
    public static function remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, \Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $ttl, $callback);
    }

    public static function rememberForever(string $key, \Closure $callback): mixed
    {
        return Cache::rememberForever(self::key($key), $callback);
    }

    public static function forget(string $key): bool
    {
        return Cache::forget(self::key($key));
    }

    public static function has(string $key): bool
    {
        return Cache::has(self::key($key));
    }

    public static function flushTenant(): void
    {
        $subdomain = app(TenantContext::class)->subdomain();
        if (! $subdomain) {
            return;
        }

        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            Cache::tags(["tenant:{$subdomain}"])->flush();
        }
        // file/database cache não suportam flush por prefix; sem-op silencioso
    }
}
