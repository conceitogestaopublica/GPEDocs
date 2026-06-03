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
        $domain = app(TenantContext::class)->domain() ?? 'shared';
        return "tenant:{$domain}:{$key}";
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::get(self::key($key), $default);
    }

    public static function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return Cache::put(self::key($key), $value, $ttl);
    }

    public static function remember(string $key, int|\DateInterval $ttl, \Closure $callback): mixed
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
        $domain = app(TenantContext::class)->domain();
        if (! $domain) return;

        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            Cache::tags(["tenant:{$domain}"])->flush();
        }
        // file/database cache não suportam flush por prefix; sem-op silencioso
    }
}
