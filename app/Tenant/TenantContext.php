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
        Config::set('database.connections.tenant', $this->buildConfig($tenant));

        // Limpa pool de conexão anterior (se houve troca) e força reconexão
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Monta a config da conexão do tenant conforme o driver do banco.
     * Suporta mariadb/mysql (padrão) e pgsql (migração para PostgreSQL).
     */
    private function buildConfig(Tenant $tenant): array
    {
        // Sem driver no cadastro: o mesmo da conexão landlord (os tenants do GPE Docs são PostgreSQL).
        $driver = $tenant->driver ?: config('database.connections.landlord.driver');
        $base = [
            'driver' => $driver,
            'host' => $tenant->db_host,
            'port' => $tenant->db_port,
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
            'prefix' => '',
        ];

        if ($driver === 'pgsql') {
            return $base + [
                'charset' => 'utf8',
                // Um BANCO por tenant (não um schema): tudo vive no public do banco dele.
                'search_path' => 'public',
                'sslmode' => 'prefer',
                'prefix_indexes' => true,
            ];
        }

        // mariadb / mysql
        return $base + [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => false,
            'engine' => null,
            'options' => [],
        ];
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

    /**
     * Subdomínio do tenant ativo — o identificador curto do município. Não usar domain()
     * no lugar: em produção ele guarda o domínio base, igual para todos os municípios.
     */
    public function subdomain(): ?string
    {
        return $this->tenant?->subdomain;
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
