<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Suporte;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;

/** Fecha os atendimentos de suporte resolvidos em que o usuário não respondeu no prazo (TR 4.26.8). */
class FecharSuporteResolvido extends Command
{
    protected $signature = 'suporte:fechar-resolvidos {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Fecha os atendimentos de suporte resolvidos sem resposta do usuário';

    public function handle(TenantContext $ctx): int
    {
        foreach (Tenant::active()->get()->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant')) as $tenant) {
            $ctx->set($tenant);
            $this->info("{$tenant->id}: " . Suporte::fecharResolvidos() . ' atendimento(s) fechado(s)');
        }

        return self::SUCCESS;
    }
}
