<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\TenantAware;
use App\Models\Tenant;
use Database\Seeders\InsertUsersGPDSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Roda o InsertUsersGPDSeeder em um tenant específico (interativo) ou em
 * todos os tenants ativos do landlord. O seeder é responsável por
 * popular/atualizar usuários a partir da fonte externa (GPD).
 *
 *   php artisan db:mass-insert-users                 # pergunta o tenant
 *   php artisan db:mass-insert-users --tenant=ALL    # itera todos
 *   php artisan db:mass-insert-users --force         # ignora confirmação em prod
 */
class MassInsertUsersComm extends Command
{
    protected $signature = "db:mass-insert-users
        {--tenant= : ID do tenant ou ALL para todos (vazio = prompt interativo)}
        {--force : Não pede confirmação interativa}";

    protected $description = 'Roda InsertUsersGPDSeeder em um tenant ou em todos os tenants ativos.';

    use TenantAware;

    private const ALL_SCHEMAS = 'todos';

    public function handle(): int
    {
        $env = config('app.env');

        if ($env === 'production' && !$this->option('force')) {
            $this->components->error('Este comando NÃO pode rodar em produção sem --force. Abortando.');
            return self::FAILURE;
        }

        $tenants = Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->components->error('Nenhum tenant registrado no landlord. Cadastre um antes de rodar este comando.');
            return self::FAILURE;
        }

        // ── 1. Tenant ───────────────────────────────────────────────────────
        $tenant = $this->resolveTenant();

        // ── 2. Loop por schema (1 ou N) ─────────────────────────────────────

        $callback = function () {
            $this->call("db:seed", [
                '--class' => InsertUsersGPDSeeder::class,
            ]);
        };
        $result = !$tenant ? $this->runForAllTenants($callback) : $this->runForOne($tenant, $callback);

        // ── 4. Limpa caches (uma vez no final) ──────────────────────────────
        if ($result)
            $this->components->task('Limpando caches da aplicação', function () {
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('view:clear');
                return true;
            });

        $this->newLine();
        $this->components->info('✓ Insert concluído.');
        return self::SUCCESS;
    }

    /**
     * Resolve o tenant via prompt interativo listando todos os tenants
     * registrados no landlord.
     */
    private function resolveTenant2(): ?Tenant
    {
        $tenants = Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->components->error('Nenhum tenant registrado no landlord. Cadastre um antes de rodar este comando.');
            return null;
        }
        $choices = [0 => "Todos"];
        $choices = array_merge($choices, $tenants->mapWithKeys(fn ($t) => [
            (string) $t->id => sprintf('%s [%s/%s] (%s @ %s)', $t->nome, $t->subdomain, $t->driver, $t->db_name, $t->db_host),
        ])->all());

        $label = $this->choice('Qual tenant você quer resetar?', $choices, array_key_first($choices));

        // O choice() do Symfony retorna o LABEL quando a chave é string. Reverte para id.
        $id = array_search($label, $choices, true) ?: array_key_first($choices);

        // Caso a oppção "todos" seja selencionada retorna NULL;
        if ($id == 0) return null;

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
