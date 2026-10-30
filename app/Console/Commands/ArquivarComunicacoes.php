<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arquivamento automático na data marcada no memorando e na circular (TR 4.46.12) — a
 * data era gravada e nada arquivava. Agendado diariamente (routes/console.php).
 */
class ArquivarComunicacoes extends Command
{
    protected $signature = 'comunicacao:arquivar-agendados {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Arquiva memorandos e circulares cuja data de arquivamento automático chegou';

    public function handle(TenantContext $ctx): int
    {
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));
        $hoje = now('America/Sao_Paulo')->toDateString();

        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            $total = 0;
            foreach (['proc_memorandos', 'proc_circulares'] as $tabela) {
                $total += DB::table($tabela)->whereNull('deleted_at')->whereNotNull('data_arquivamento_auto')
                    ->where('data_arquivamento_auto', '<=', $hoje)->where('status', '<>', 'arquivado')
                    ->update(['status' => 'arquivado', 'arquivado_em' => now(), 'updated_at' => now()]);
            }
            $this->info("{$tenant->id}: {$total} arquivado(s)");
        }

        return self::SUCCESS;
    }
}
