<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Console\Commands\Concerns\TenantAware;
use Illuminate\Console\Command;

/**
 * Roda migrations em todos os tenants (ou um específico).
 *
 *   php artisan tenant:migrate                          # interativo: pergunta o tenant
 *   php artisan tenant:migrate --tenant=santoantoniodoamparo
 *   php artisan tenant:migrate --tenant=ALL             # todos os tenants ativos
 *   php artisan tenant:migrate --tenant=ALL --fresh     # cuidado!
 *   php artisan tenant:migrate --tenant=ALL --seed
 *   php artisan tenant:migrate --tenant=ALL --pretend   # dry-run
 */
class TenantMigrate extends Command
{
    use TenantAware;

    protected $signature = 'tenant:migrate
        {--tenant= : Domínio do tenant (ALL para todos)}
        {--fresh : Recria o banco do tenant (cuidado!)}
        {--pretend : Apenas mostra o SQL que rodaria}
        {--path= : Caminho específico das migrations}';

    protected $description = 'Roda migrations no banco de tenant(s) — todos ou específico';

    public function handle(): int
    {
        $this->info("Identificando tenants somente do GPEDOCS... ");

        if ($this->option('fresh') && $this->option('tenant') === 'ALL') {
            if (!$this->confirm('Isso vai DROPAR e recriar TODOS os schemas dos banco de tenants. Tem certeza?', false)) {
                $this->info('Cancelado.');
                return self::SUCCESS;
            }
        }
//        if ()

        return $this->runForTenant(function () {
            $opts = [
                '--database' => 'tenant',
                '--force' => true,
            ];
            if ($this->option('pretend')) $opts['--pretend'] = true;
            if ($this->option('path')) $opts['--path'] = $this->option('path');

            if ($this->option('fresh')) {
                $code = $this->call('migrate:fresh', $opts);
            } else {
                $code = $this->call('migrate', $opts);
            }

            return $code;
        });
    }
}
