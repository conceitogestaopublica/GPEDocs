<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Apaga TODAS as tabelas do tenant escolhido e recria o schema do zero rodando
 * todas as migrations + os seeders de demonstração (DatabaseSeeder).
 *
 * O comando pergunta interativamente:
 *   1. Qual tenant resetar (carregados do landlord)
 *   2. Qual driver usar (mysql / mariadb / pgsql / sqlite)
 *   3. Qual schema usar (apenas pgsql)
 *
 *   php artisan db:reset-demo
 *   php artisan db:reset-demo --tenant=2 --driver=pgsql --schema=public --force
 *   php artisan db:reset-demo --force --no-seed
 */
class DbResetDemo extends Command
{
    protected $signature = 'db:reset-demo
                            {--tenant= : ID do tenant no landlord (pula o prompt)}
                            {--driver= : mysql | mariadb | pgsql | sqlite (pula o prompt)}
                            {--schema= : Schema do postgres (pula o prompt; só usado se driver=pgsql)}
                            {--force : Não pede confirmação interativa}
                            {--no-seed : Apenas dropa e migra, sem rodar os seeders demo}';

    protected $description = 'Apaga todas as tabelas e recria o banco do tenant com dados de demonstração.';

    private const DRIVERS = ['mysql', 'mariadb', 'pgsql', 'sqlite'];

    public function handle(TenantContext $tenantContext): int
    {
        $env = config('app.env');

        if ($env === 'production' && !$this->option('force')) {
            $this->components->error('Este comando NÃO pode rodar em produção sem --force. Abortando.');
            return self::FAILURE;
        }

        // ── 1. Tenant ───────────────────────────────────────────────────────
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            return self::FAILURE;
        }

        // ── 2. Driver ───────────────────────────────────────────────────────
        $driver = $this->resolveDriver($tenant);
        if (! in_array($driver, self::DRIVERS, true)) {
            $this->components->error("Driver inválido: [{$driver}]. Use um de: " . implode(', ', self::DRIVERS));
            return self::FAILURE;
        }

        // ── 3. Schema (só pgsql) ────────────────────────────────────────────
        $schema = $driver === 'pgsql' ? $this->resolveSchema($tenant) : null;

        // ── 4. Resumo + confirmação ─────────────────────────────────────────
        $this->components->info(sprintf(
            'Reset demo — tenant: %s (#%d) | driver: %s | host: %s | database: %s%s | env: %s',
            $tenant->nome,
            $tenant->id,
            $driver,
            $tenant->db_host,
            $tenant->db_name,
            $schema ? " | schema: {$schema}" : '',
            $env,
        ));

        if (!$this->option('force')) {
            $this->warn("⚠  Todas as tabelas em [{$tenant->db_name}] serão APAGADAS.");
            if (! $this->confirm('Tem certeza que deseja continuar?', false)) {
                $this->components->warn('Operação cancelada.');
                return self::SUCCESS;
            }
        }

        // ── 5. Aplica overrides na conexão "tenant" ─────────────────────────
        $tenant->driver = $driver;
        if ($schema !== null) {
            // Atributo virtual usado por TenantContext::buildConnectionConfig().
            $tenant->db_schema = $schema;
        }
        dd($tenant);
        $tenantContext->set($tenant);
        DB::setDefaultConnection('tenant');

        // ── 6. migrate:fresh ────────────────────────────────────────────────
        $this->components->task('Apagando tabelas e rodando migrations', function () {
            return Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--force'    => true,
            ], $this->output) === 0;
        });

        // ── 7. Seeders demo ─────────────────────────────────────────────────
        if (!$this->option('no-seed')) {
            $this->components->task('Rodando DatabaseSeeder (dados de demonstração)', function () {
                return Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--force'    => true,
                ], $this->output) === 0;
            });
        } else {
            $this->components->info('--no-seed: pulando os seeders.');
        }

        // ── 8. Limpa caches ─────────────────────────────────────────────────
        $this->components->task('Limpando caches da aplicação', function () {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            return true;
        });

        $this->newLine();
        $this->components->info('✓ Reset demo concluído.');
        $this->line('  Credenciais padrão dos usuários demo (senha: <fg=yellow>demo1234</>):');
        $this->line('    super@modelo.local       — Super Admin (acesso a todas UGs)');
        $this->line('    admin.ug@modelo.local    — Admin da UG modelo');
        $this->line('    gestor@modelo.local      — Gestor Documental');
        $this->line('    operador@modelo.local    — Operador');
        $this->line('    admin@ged.local          — Admin GED legado (senha: admin123)');
        $this->line('    beatriz@email.com        — Cidadão do Portal');

        return self::SUCCESS;
    }

    /**
     * Resolve o tenant via --tenant (id ou subdomain) ou via prompt interativo
     * listando todos os tenants registrados no landlord.
     */
    private function resolveTenant(): ?Tenant
    {
        $tenants = Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->components->error('Nenhum tenant registrado no landlord. Cadastre um antes de rodar este comando.');
            return null;
        }

        if ($id = $this->option('tenant')) {
            /** @var Tenant|null $tenant */
            $tenant = $tenants->firstWhere('id', (int) $id);
            if (! $tenant) {
                $this->components->error("Tenant com id [{$id}] não encontrado no landlord.");
                return null;
            }
            return $tenant;
        }

        $choices = $tenants->mapWithKeys(fn ($t) => [
            (string) $t->id => sprintf('%s [%s/%s] (%s @ %s)', $t->nome, $t->subdomain, $t->driver, $t->db_name, $t->db_host),
        ])->all();

        $label = $this->choice('Qual tenant você quer resetar?', $choices, array_key_first($choices));

        // O choice() do Symfony retorna o LABEL quando a chave é string. Reverte para id.
        $id = array_search($label, $choices, true) ?: array_key_first($choices);

        /** @var Tenant|null $tenant */
        $tenant = $tenants->firstWhere('id', (int) $id);
        return $tenant;
    }

    private function resolveDriver(Tenant $tenant): string
    {
        if ($d = $this->option('driver')) {
            return $d;
        }

        $default = in_array($tenant->driver, self::DRIVERS, true) ? $tenant->driver : 'pgsql';

        return $this->choice('Qual driver usar?', self::DRIVERS, array_search($default, self::DRIVERS, true));
    }

    private function resolveSchema(Tenant $tenant): string
    {
        if ($s = $this->option('schema')) {
            return $s;
        }

        $default = $tenant->db_schema ?? 'public';

        return (string) ($this->ask('Qual schema do postgres usar?', $default) ?: 'public');
    }
}
