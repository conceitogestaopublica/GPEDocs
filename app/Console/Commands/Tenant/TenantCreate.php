<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
        {--subdomain= : Subdomínio (ex: santoantoniodoamparo)}
        {--domain=maatgpecloud.com.br : Domínio base (ex: maatgpecloud.com.br)}
        {--nome= : Nome do município}
        {--db-host=127.0.0.1}
        {--db-port=5432}
        {--db-name= : Nome do banco}
        {--driver=pgsql : Driver do banco do tenant (o GPE2 é PostgreSQL — ver ADR-0001)}
        {--db-username= : Usuário do banco}
        {--db-password= : Senha do banco}
        {--cnpj=}
        {--uf=}';

    protected $description = 'Cadastra um novo tenant no landlord';

    public function handle(): int
    {
        $subdomain = $this->option('subdomain') ?: $this->ask('Subdomínio (ex: santoantoniodoamparo)');
        $domain = $this->option('domain') ?: 'maatgpecloud.com.br';
        $nome = $this->option('nome') ?: $this->ask('Nome do município');
        $dbHost = $this->option('db-host') ?: '127.0.0.1';
        $dbPort = $this->option('db-port') ?: '5432';
        $dbName = $this->option('db-name') ?: $this->ask('Nome do banco PostgreSQL');
        // PostgreSQL é o único banco de tenant (ADR-0001). O default era `mariadb` — herdado
        // da fase de migração — e o tenant nascia invisível para `gpe:template-restore` e
        // `tenant:migrate`, que filtram driver='pgsql'.
        $driver = $this->option('driver') ?: 'pgsql';
        $dbUser = $this->option('db-username') ?: $this->ask('Usuário do banco');
        $dbPass = $this->option('db-password') ?: $this->secret('Senha do banco');

        if (Tenant::where('subdomain', $subdomain)->exists()) {
            $this->error("Tenant '{$subdomain}' já existe.");

            return self::FAILURE;
        }

        $tenant = Tenant::create([
            'subdomain' => $subdomain,
            'domain' => $domain,
            'nome' => $nome,
            'cnpj' => $this->option('cnpj'),
            'uf' => $this->option('uf'),
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_username' => $dbUser,
            'db_password' => $dbPass,
            'driver' => $driver,
            'active' => true,
        ]);

        // Limpa cache do middleware
        Cache::forget("tenant:subdomain:{$subdomain}");

        $this->info("Tenant '{$subdomain}' criado com sucesso. ID: {$tenant->id}");
        $this->newLine();
        $this->line('Próximos passos (nesta ordem — ver docs/instalacao/):');
        $this->line("  1. Criar o banco '{$dbName}' VAZIO: <fg=cyan>CREATE DATABASE {$dbName};</>");
        $this->line('  2. Criar o SCHEMA a partir do modelo: <fg=cyan>php artisan gpe:template-restore</>');
        $this->line('     (o schema legado não está em migrations: sem este passo, o migrate falha em cascata)');
        $this->line("  3. Aplicar as migrations: <fg=cyan>php artisan tenant:migrate --tenant={$subdomain}</>");
        $this->line("  4. Acessar via: <fg=cyan>{$subdomain}.{$domain}</>");

        return self::SUCCESS;
    }
}
