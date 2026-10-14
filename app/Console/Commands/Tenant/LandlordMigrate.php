<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use Illuminate\Console\Command;

/**
 * Cria no landlord as tabelas de fila do GPEDocs (gpedocs_jobs, gpedocs_job_batches,
 * gpedocs_failed_jobs).
 *
 *   php artisan landlord:migrate
 *
 * O landlord é do gpe2 (compartilhado): o schema dele é migrado lá. Por isso aqui NÃO
 * se usa o migrator — ele gravaria na tabela `migrations` do gpe2, e fresh/rollback
 * apagariam o catálogo de tenants de todos os sistemas. As migrations de
 * database/migrations/landlord/ são executadas direto (up()), e são idempotentes
 * (checam hasTable antes de criar).
 */
class LandlordMigrate extends Command
{
    protected $signature = 'landlord:migrate';

    protected $description = 'Cria as tabelas de fila do GPEDocs no landlord compartilhado (idempotente)';

    public function handle(): int
    {
        $arquivos = glob(database_path('migrations/landlord/*.php')) ?: [];
        sort($arquivos);

        foreach ($arquivos as $arquivo) {
            $this->components->task(basename($arquivo), function () use ($arquivo) {
                (require $arquivo)->up();

                return true;
            });
        }

        return self::SUCCESS;
    }
}
