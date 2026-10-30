<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integracoes\Tramita\Fabrica;
use App\Models\Tenant;
use App\Support\Tramita;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;

/** Tramita GOV.BR (TR 4.28): busca recibos e recusas dos enviados e recebe o que outros entes mandaram. */
class SincronizarTramita extends Command
{
    protected $signature = 'tramita:sincronizar {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Sincroniza os trâmites com o Tramita GOV.BR';

    public function handle(TenantContext $ctx): int
    {
        foreach (Tenant::active()->get()->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant')) as $tenant) {
            $ctx->set($tenant);
            if (! Fabrica::config()->ativo) {
                continue;
            }
            $r = Tramita::sincronizar();
            $this->info("{$tenant->id}: {$r['concluidos']} recibo(s), {$r['recusados']} recusa(s), {$r['recebidos']} recebido(s)" . ($r['erros'] ? ' — ' . implode(' | ', $r['erros']) : ''));
        }

        return self::SUCCESS;
    }
}
