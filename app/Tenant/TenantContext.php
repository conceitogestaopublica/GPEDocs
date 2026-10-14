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
     * Config de conexão de um tenant a partir da LINHA dele — para quem precisa abrir uma
     * conexão nomeada para outro banco SEM trocar o tenant ativo (publicação das universais,
     * comparações matriz × município).
     */
    public function configPara(Tenant $tenant): array
    {
        return $this->buildConfig($tenant);
    }

    /**
     * Config para um banco de tenant pelo NOME do banco.
     *
     * Existe porque clonar a conexão default e trocar só o `database` só funciona quando
     * todos os tenants moram no mesmo servidor, com as mesmas credenciais — o que vale no
     * ambiente de desenvolvimento e NÃO vale em produção. Lá a conexão falhava e o erro
     * ainda saía disfarçado ("banco sem as tabelas universais").
     *
     * Banco não cadastrado (template, clone de teste) cai na conexão default, que é o
     * comportamento antigo e continua correto para eles.
     */
    public function configPorBanco(string $database): array
    {
        $tenant = Tenant::query()->where('db_name', $database)->first();
        if ($tenant) {
            return $this->buildConfig($tenant);
        }

        $base = config('database.connections.'.config('database.default'));
        if (! is_array($base)) {
            throw new \RuntimeException('Conexão default não encontrada.');
        }
        $base['database'] = $database;

        return $base;
    }

    /**
     * Monta a config da conexão do tenant conforme o driver do banco.
     * Suporta mariadb/mysql (padrão) e pgsql (migração para PostgreSQL).
     */
    private function buildConfig(Tenant $tenant): array
    {
        $driver = $tenant->driver ?: 'mariadb';
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
     * Subdomínio do tenant ativo — o identificador curto e único do município.
     *
     * O método simplesmente nunca existiu, e TRÊS lugares o chamavam: `TenantCache` (que
     * prefixa a chave de cache para não vazar dado entre municípios), `TenantStorage` (que
     * separa os arquivos por tenant) e o `PcaspRoteirosSeeder`. Todos morriam com "Call to
     * undefined method" no primeiro uso — o `TenantCache` inteiro estava inutilizável, e por
     * isso ninguém o usava. O PHPStan apontava desde sempre, com as 3 ocorrências no baseline.
     *
     * `domain()` NÃO serve como substituto: em produção ele guarda o domínio base
     * (maatgpecloud.com.br), igual para todos os municípios.
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
