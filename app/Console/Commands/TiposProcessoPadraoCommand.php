<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\TenantAware;
use App\Support\TiposProcessoPadrao;
use Illuminate\Console\Command;

/**
 * Garante os tipos de processo padrão (a migração já faz isso; este comando é para
 * rodar de novo, ex.: banco que ainda não tinha usuário na hora da migração).
 *
 *   php artisan docs:tipos-processo-padrao --tenant=ALL
 */
class TiposProcessoPadraoCommand extends Command
{
    use TenantAware;

    protected $signature = 'docs:tipos-processo-padrao {--tenant= : Tenant (id, subdomínio ou ALL)}';

    protected $description = 'Cria os tipos de processo padrão (REQ, RH, LIC, OUV, LAI, TRIB) que faltarem';

    public function handle(): int
    {
        return $this->runForTenant(function () {
            $criados = TiposProcessoPadrao::garantir();
            $this->line($criados === null
                ? '  Sem usuário no banco ainda — nada criado.'
                : "  Tipos de processo padrão criados: {$criados}");

            return self::SUCCESS;
        });
    }
}
