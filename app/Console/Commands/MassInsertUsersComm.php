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
}
