<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\RelatorioController;
use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Disponibilização automática dos relatórios (TR 4.25.15): de hora em hora, em cada município, gera os
 * agendamentos vencidos (dia anterior, últimos 7 dias ou mês anterior) e programa o próximo envio.
 */
class RelatoriosAgendados extends Command
{
    protected $signature = 'relatorios:agendados {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Gera os relatórios agendados que venceram';

    public function handle(TenantContext $ctx): int
    {
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));

        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            foreach (DB::table('ged_relatorios_agendados')->where('ativo', true)->where('proximo_em', '<=', now())->get() as $ag) {
                $id = RelatorioController::enfileirar($ag->tipo, RelatorioController::periodoDoAgendamento($ag), (int) $ag->usuario_id, (int) $ag->id);
                DB::table('ged_relatorios_agendados')->where('id', $ag->id)->update(['ultimo_em' => now(),
                    'proximo_em' => RelatorioController::proximoEnvio($ag->frequencia), 'updated_at' => now()]);
                $this->info("{$tenant->id}: agendamento {$ag->id} → relatório {$id}");
            }
        }

        return self::SUCCESS;
    }
}
