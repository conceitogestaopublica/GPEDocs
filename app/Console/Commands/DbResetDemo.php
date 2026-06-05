<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Apaga TODAS as tabelas do tenant escolhido e recria o schema do zero rodando
 * todas as migrations + os seeders de demonstração (DatabaseSeeder).
 *
 * O comando pergunta interativamente:
 *   1. Qual tenant resetar (carregados do landlord)
 *   2. Qual schema usar (apenas pgsql) — aceita "todos" para iterar em
 *      todos os schemas do banco
 *
 * O driver é tirado direto do tenant (`tenants.driver`) — não há prompt.
 *
 *   php artisan db:reset-demo
 *   php artisan db:reset-demo --force
 *   php artisan db:reset-demo --force --no-seed
 */
class DbResetDemo extends Command
{
    protected $signature = 'db:reset-demo
                            {--force : Não pede confirmação interativa}
                            {--no-seed : Apenas dropa e migra, sem rodar os seeders demo}';

    protected $description = 'Apaga todas as tabelas e recria o banco do tenant com dados de demonstração.';

    private const DRIVERS = ['mysql', 'mariadb', 'pgsql', 'sqlite'];
    private const ALL_SCHEMAS = 'todos';

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

        // ── 2. Driver (direto do tenant) ────────────────────────────────────
        $driver = (string) $tenant->driver;
        if (! in_array($driver, self::DRIVERS, true)) {
            $this->components->error("Driver do tenant inválido: [{$driver}]. Esperado um de: " . implode(', ', self::DRIVERS));
            return self::FAILURE;
        }

        // ── 3. Schema(s) — só pgsql ─────────────────────────────────────────
        $schemas = $driver === 'pgsql' ? $this->resolveSchemas($tenant) : [null];
        if ($schemas === []) {
            $this->components->error('Nenhum schema selecionado/encontrado.');
            return self::FAILURE;
        }

        // ── 4. Resumo + confirmação ─────────────────────────────────────────
        $this->components->info(sprintf(
            'Reset demo — tenant: %s (#%d) | driver: %s | host: %s | database: %s%s | env: %s',
            $tenant->nome,
            $tenant->id,
            $driver,
            $tenant->db_host,
            $tenant->db_name,
            $driver === 'pgsql' ? ' | schema(s): ' . implode(', ', $schemas) : '',
            $env,
        ));

        if (!$this->option('force')) {
            $alvo = count($schemas) > 1
                ? count($schemas) . " schemas do banco [{$tenant->db_name}]"
                : "tabelas em [{$tenant->db_name}]" . ($schemas[0] ? " (schema {$schemas[0]})" : '');
            $this->warn("⚠  {$alvo} serão APAGADAS.");
            if (! $this->confirm('Tem certeza que deseja continuar?', false)) {
                $this->components->warn('Operação cancelada.');
                return self::SUCCESS;
            }
        }

        // ── 5. Loop por schema (1 ou N) ─────────────────────────────────────
        foreach ($schemas as $idx => $schema) {
            if (count($schemas) > 1) {
                $this->newLine();
                $this->components->info(sprintf('▶ Schema %d/%d: %s', $idx + 1, count($schemas), $schema));
            }

            $tenant->driver = $driver;
            if ($schema !== null) {
                // Atributo virtual usado por TenantContext::buildConnectionConfig().
                $tenant->db_schema = $schema;
            }

            $tenantContext->set($tenant);
            DB::setDefaultConnection('tenant');

            $this->components->task('Apagando tabelas e rodando migrations', function () {
                return Artisan::call('migrate:fresh', [
                    '--database' => 'tenant',
                    '--force'    => true,
                ], $this->output) === 0;
            });

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
        }

        // ── 6. Limpa caches (uma vez no final) ──────────────────────────────
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
     * Resolve o tenant via prompt interativo listando todos os tenants
     * registrados no landlord.
     */
    private function resolveTenant(): ?Tenant
    {
        $tenants = Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->components->error('Nenhum tenant registrado no landlord. Cadastre um antes de rodar este comando.');
            return null;
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

    /**
     * Retorna lista de schemas a operar:
     *   - ['public']               → 1 schema específico (selecionado pelo usuário)
     *   - ['s1', 's2', 's3', ...]  → "todos" → lista vinda do information_schema
     */
    private function resolveSchemas(Tenant $tenant): array
    {
        $existing = $this->listPgsqlSchemas($tenant);
        $options  = [...$existing, self::ALL_SCHEMAS];
        $default  = in_array('public', $existing, true) ? 'public' : (string) $options[0];

        $choice = $this->choice(
            'Qual schema do postgres usar? (escolha "todos" para iterar em todos os schemas existentes)',
            $options,
            array_search($default, $options, true),
        );

        return $choice === self::ALL_SCHEMAS ? $existing : [$choice];
    }

    /**
     * Lista schemas reais do banco postgres do tenant (excluindo schemas
     * internos pg_* e information_schema). Usa uma conexão temporária.
     */
    private function listPgsqlSchemas(Tenant $tenant): array
    {
        Config::set('database.connections._schema_list', [
            'driver'      => 'pgsql',
            'host'        => $tenant->db_host,
            'port'        => $tenant->db_port,
            'database'    => $tenant->db_name,
            'username'    => $tenant->db_username,
            'password'    => $tenant->db_password,
            'charset'     => $tenant->db_charset ?? 'utf8',
            'prefix'      => '',
            'search_path' => 'public',
            'sslmode'     => $tenant->db_sslmode ?? 'prefer',
        ]);

        try {
            $rows = DB::connection('_schema_list')->select(
                "SELECT schema_name FROM information_schema.schemata
                 WHERE schema_name NOT LIKE 'pg_%' AND schema_name != 'information_schema'
                 ORDER BY schema_name"
            );
        } finally {
            DB::purge('_schema_list');
        }

        $names = array_map(fn ($r) => (string) $r->schema_name, $rows);

        // Garantia mínima: 'public' sempre disponível como opção, mesmo se o
        // banco estiver vazio ou inacessível por algum motivo.
        if (! in_array('public', $names, true)) {
            array_unshift($names, 'public');
        }

        return $names;
    }
}
