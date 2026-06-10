<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use Illuminate\Console\Command;

/**
 * Roda migrations do banco landlord (catálogo de tenants).
 *
 *   php artisan landlord:migrate
 *   php artisan landlord:migrate --fresh           # apaga tudo e recria
 *   php artisan landlord:migrate --rollback        # desfaz o último batch
 *   php artisan landlord:migrate --rollback --step=N
 */
class LandlordMigrate extends Command
{
    protected $signature = 'landlord:migrate
                            {--fresh    : Apaga o catálogo e recria do zero}
                            {--rollback : Desfaz o último batch de migrations}
                            {--step=    : Quantos batches reverter (usado com --rollback)}';

    protected $description = 'Roda migrations do banco landlord (database/migrations/landlord/)';

    public function handle(): int
    {
        $opts = [
            '--database' => 'landlord',
            '--path'     => 'database/migrations/landlord',
            '--force'    => true,
        ];

        if ($this->option('fresh')) {
            if (! $this->confirm('Isso vai apagar o catálogo de tenants. Tem certeza?', false)) {
                return self::SUCCESS;
            }
            return $this->call('migrate:fresh', $opts);
        }

        if ($this->option('rollback')) {
            if (! $this->confirm('Reverter migrations do landlord pode apagar dados. Continuar?', false)) {
                return self::SUCCESS;
            }
            if ($step = $this->option('step')) {
                $opts['--step'] = (int) $step;
            }
            return $this->call('migrate:rollback', $opts);
        }

        return $this->call('migrate', $opts);
    }
}
