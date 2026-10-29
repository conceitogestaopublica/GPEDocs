<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\DiarioOficial;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Edição ordinária automática do Diário Oficial (TR 4.43.8): todo dia, em cada UG com atos
 * pendentes e sem edição na data, monta a ordinária com eles (fica pendente para o responsável
 * conferir e publicar). Agendado em routes/console.php.
 */
class MontarEdicaoOrdinaria extends Command
{
    protected $signature = 'diario:montar-ordinaria {--tenant=ALL : id do município ou ALL} {--data= : AAAA-MM-DD (padrão: hoje)}';

    protected $description = 'Monta a edição ordinária do Diário Oficial com os atos pendentes';

    public function handle(TenantContext $ctx): int
    {
        $data = $this->option('data') ?: now('America/Sao_Paulo')->toDateString();
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));

        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            foreach (DB::table('dof_materias')->whereNull('edicao_id')->distinct()->pluck('ug_id') as $ugId) {
                $e = DiarioOficial::montarOrdinaria((int) $ugId, $data);
                $this->info("{$tenant->id}/UG {$ugId}: " . ($e ? "edição ordinária {$e->id} montada" : 'nada a montar'));
            }
        }

        return self::SUCCESS;
    }
}