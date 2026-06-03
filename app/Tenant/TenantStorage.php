<?php

declare(strict_types=1);

namespace App\Tenant;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Acesso a arquivos do tenant atual, com isolamento por subdiretório.
 *
 *   Arquivo do tenant "santoantonio":
 *     storage/app/tenants/santoantonio/Medicoes/foto.jpg
 *
 *   Arquivo público:
 *     storage/app/public/tenants/santoantonio/logo.png
 *
 * Uso:
 *
 *   TenantStorage::disk()->put('Medicoes/x.pdf', $bytes);
 *   TenantStorage::disk('public')->url('logo.png');
 *   TenantStorage::path('Documentos/Termo.pdf');  // caminho absoluto
 */
class TenantStorage
{
    /** Disco isolado para o tenant atual ("local" ou "public"). */
    public static function disk(string $base = 'local'): Filesystem
    {
        $prefix = self::prefix();
        return Storage::build([
            'driver'     => 'local',
            'root'       => self::resolveRoot($base) . '/' . $prefix,
            'throw'      => false,
            'serve'      => true,
            'visibility' => $base === 'public' ? 'public' : 'private',
            'url'        => $base === 'public'
                ? rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage/' . $prefix
                : null,
        ]);
    }

    /** Caminho absoluto dentro da pasta do tenant. */
    public static function path(string $relativePath = '', string $base = 'local'): string
    {
        return self::resolveRoot($base) . '/' . self::prefix()
            . ($relativePath ? '/' . ltrim($relativePath, '/') : '');
    }

    /** Prefixo único do tenant atual. Fallback "shared" se não houver contexto. */
    public static function prefix(): string
    {
        $ctx = app(TenantContext::class);
        return 'tenants/' . ($ctx->domain() ?? 'shared');
    }

    private static function resolveRoot(string $base): string
    {
        return $base === 'public'
            ? storage_path('app/public')
            : storage_path('app/private');
    }
}
