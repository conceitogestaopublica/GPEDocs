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

        $driver = $tenant->driver ?: 'mariadb';

        // Base comum a todos os drivers.
        $config = [
            'driver'   => $driver,
            'host'     => $tenant->db_host,
            'port'     => $tenant->db_port,
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
            'prefix'   => '',
            'options'  => [],
        ];

        // Defaults por driver. Postgres exige search_path (schema do tenant).
        if ($driver === 'pgsql') {
            $config += [
                'charset'        => 'utf8',
                'search_path'    => $tenant->db_schema ?: 'public',
                'sslmode'        => 'prefer',
                'prefix_indexes' => true,
            ];
        } else {
            $config += [
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict'    => false,
                'engine'    => null,
            ];
        }

        Config::set('database.connections.tenant', $config);

        // Limpa pool de conexão anterior (se houve troca) e força reconexão
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
        DB::reconnect('tenant');
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

    /** Subdomínio (rótulo único do tenant — ex: paraguacu). */
    public function subdomain(): ?string
    {
        return $this->tenant?->subdomain;
    }

    /** Domínio base (ex: maatgpecloud.com.br). */
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
