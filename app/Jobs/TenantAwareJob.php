<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job base com isolamento de tenant. Captura o tenant_id no momento do dispatch
 * (na request com contexto ativo) e reativa o contexto no worker antes de executar.
 *
 * Uso:
 *   class GerarRelatorioBalancete extends TenantAwareJob {
 *       protected function handleTenant(): void {
 *           // ... aqui o tenant já está ativo, queries vão para o banco do município
 *       }
 *   }
 *
 *   GerarRelatorioBalancete::dispatch();  // automaticamente captura o tenant atual
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tenant_id;

    public function __construct()
    {
        $tenantId = app(TenantContext::class)->id();
        if (! $tenantId) {
            throw new \RuntimeException(
                'TenantAwareJob criado sem tenant ativo. Use ::dispatch() apenas dentro de uma requisição com tenant resolvido, ou setManualmente: app(TenantContext::class)->set($tenant) antes.'
            );
        }
        $this->tenant_id = $tenantId;
    }

    final public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenant_id);
        if (! $tenant->active) {
            // Tenant desativado entre o dispatch e a execução — descarta sem erro
            Log::error('USUARIO: ERROR: O tenant foi desativado!', []);
            return;
        }
        app(TenantContext::class)->set($tenant);
        $this->handleTenant();
    }

    abstract protected function handleTenant(): void;
}
