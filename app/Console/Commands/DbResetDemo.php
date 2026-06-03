<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Apaga TODAS as tabelas do banco configurado em DB_CONNECTION e recria o
 * schema do zero rodando todas as migrations + os seeders de demonstração
 * (DatabaseSeeder). Util para resetar um ambiente de dev/demo a um estado
 * conhecido e reproduzível.
 *
 *   php artisan db:reset-demo               (pede confirmação)
 *   php artisan db:reset-demo --force       (sem confirmação — para CI/Docker)
 *   php artisan db:reset-demo --no-seed     (apenas migra, não popula demo)
 */
class DbResetDemo extends Command
{
    protected $signature = 'db:reset-demo
                            {--force : Não pede confirmação interativa}
                            {--no-seed : Apenas dropa e migra, sem rodar os seeders demo}';

    protected $description = 'Apaga todas as tabelas e recria o banco com dados de demonstração (DatabaseSeeder).';

    public function handle(): int
    {
        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");
        $host       = config("database.connections.{$connection}.host", 'local');
        $env        = config('app.env');

        $this->components->info("Reset demo — conexão: {$connection} | host: {$host} | database: {$database} | env: {$env}");

        // ── Salvaguarda: em produção, exige --force EXPLÍCITO ───────────────
        if ($env === 'production' && ! $this->option('force')) {
            $this->components->error('Este comando NÃO pode rodar em produção sem --force. Abortando.');
            return self::FAILURE;
        }

        // ── Confirmação interativa ──────────────────────────────────────────
        if (! $this->option('force')) {
            $this->warn("⚠  Todas as tabelas em [{$database}] serão APAGADAS.");
            if (! $this->confirm("Tem certeza que deseja continuar?", false)) {
                $this->components->warn('Operação cancelada.');
                return self::SUCCESS;
            }
        }

        // ── 1. migrate:fresh — dropa todas as tabelas e roda migrations ─────
        $this->components->task('Apagando tabelas e rodando migrations', function () {
            return Artisan::call('migrate:fresh', ['--force' => true], $this->output) === 0;
        });

        // ── 2. Seeders demo (a menos que --no-seed) ─────────────────────────
        if (! $this->option('no-seed')) {
            $this->components->task('Rodando DatabaseSeeder (dados de demonstração)', function () {
                return Artisan::call('db:seed', ['--force' => true], $this->output) === 0;
            });
        } else {
            $this->components->info('--no-seed: pulando os seeders.');
        }

        // ── 3. Limpa caches que dependem do schema/dados ────────────────────
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
}
