<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Mantém o tenant ativo da requisição/job atual e configura a conexão "tenant"
 * dinamicamente. Registrado como scoped() no container — uma instância por
 * ciclo de request/job.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    /** Define o tenant ativo e configura a conexão. */
    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        Config::set('database.connections.tenant', $this->buildConnectionConfig($tenant));

        // Limpa pool de conexão anterior (se houve troca) e força reconexão
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Monta o array de config da conexão "tenant" conforme o driver do tenant.
     * Suporta mysql, mariadb e pgsql — cada um tem chaves específicas que o
     * Laravel/PDO espera, então fazer um único shape "universal" gera erros
     * silenciosos (ex.: utf8mb4 com pgsql, sslmode com mysql).
     */
    private function buildConnectionConfig(Tenant $tenant): array
    {
        $base = [
            'driver'   => $tenant->driver,
            'host'     => $tenant->db_host,
            'port'     => $tenant->db_port,
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
            'prefix'   => '',
        ];

        return match ($tenant->driver) {
            'mysql', 'mariadb' => array_merge($base, [
                'charset'        => $tenant->db_charset ?? 'utf8mb4',
                'collation'      => $tenant->db_collation ?? 'utf8mb4_unicode_ci',
                'prefix_indexes' => true,
                'strict'         => false,
                'engine'         => null,
                'options'        => [],
            ]),

            'pgsql' => array_merge($base, [
                'charset'        => $tenant->db_charset ?? 'utf8',
                'prefix_indexes' => true,
                'search_path'    => $tenant->db_schema ?? 'public',
                'sslmode'        => $tenant->db_sslmode ?? 'prefer',
            ]),

            default => throw new \InvalidArgumentException(
                "Driver de tenant não suportado: [{$tenant->driver}]. Use mysql, mariadb ou pgsql."
            ),
        };
    }

    /** Limpa o tenant ativo — usado em testes e shutdown de jobs. */
    public function clear(): void
    {
        $this->tenant = null;
        DB::purge('tenant');
        DB::setDefaultConnection(env('DB_CONNECTION', 'sqlite'));
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function domain(): ?string
    {
        return $this->tenant?->domain;
    }

    public function nome(): ?string
    {
        return $this->tenant?->nome;
    }

    public function isSet(): bool
    {
        return $this->tenant !== null;
    }
}
