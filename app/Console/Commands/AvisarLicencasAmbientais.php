<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\LicenciamentoAmbiental;
use App\Support\Setores;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;

/**
 * Licenciamento ambiental, diariamente: marca as licenças vencidas, avisa o empreendedor e o setor do
 * vencimento próximo (prazo da renovação) e das condicionantes perto do prazo. Agendado em routes/console.php.
 */
class AvisarLicencasAmbientais extends Command
{
    protected $signature = 'ambiental:avisar {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Vencimento de licenças ambientais e prazos de condicionantes';

    public function handle(TenantContext $ctx): int
    {
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));
        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            Setores::esquecer();
            $r = LicenciamentoAmbiental::avisar();
            $this->info("{$tenant->id}: {$r['vencidas']} vencida(s), {$r['avisos']} aviso(s)");
        }

        return self::SUCCESS;
    }
}
