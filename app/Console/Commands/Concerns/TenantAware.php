<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Trait para comandos que precisam rodar em contexto de tenant.
 *
 * Use em qualquer Command:
 *
 *   class MeuCommand extends Command {
 *       use TenantAware;
 *
 *       protected $signature = 'meu:comando {--tenant=}';
 *
 *       public function handle() {
 *           return $this->runForTenant(fn() => $this->doWork());
 *       }
 *
 *       private function doWork(): int { ... }
 *   }
 *
 * Resolve a flag --tenant=<subdomain> (ou pergunta interativamente se ausente).
 * Use --tenant=ALL para iterar todos os tenants ativos.
 */
trait TenantAware
{
    /**
     * Executa $callback para 1 tenant (--tenant=domain) ou todos (--tenant=ALL).
     * Retorna o pior código de saída.
     */
    protected function runForTenant(callable $callback): int
    {
        $opt = $this->option('tenant');

        if ($opt === 'ALL' || $opt === 'all' || $opt === '*') {
            return $this->runForAllTenants($callback);
        }

        $tenant = $this->resolveTenant($opt);

        if (!$tenant) return self::FAILURE;

        return $this->runForOne($tenant, $callback);
    }

    private function runForAllTenants(callable $callback): int
    {
        $tenants = Tenant::active()
            ->where('driver', 'pgsql')
            ->where('domain', 'gpedocs.com.br')
            ->orderBy('subdomain')->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo cadastrado no landlord.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Executando para %d tenant(s)...', $tenants->count()));
        $worst = self::SUCCESS;

        foreach ($tenants as $t) {
            $this->newLine();
            $this->line("<bg=blue;fg=white> {$t->subdomain} </> — {$t->nome}");
            $exit = $this->runForOne($t, $callback);
            if ($exit !== self::SUCCESS) $worst = $exit;
        }

        $this->newLine();
        $this->info('Concluído.');
        return $worst;
    }

    private function runForOne(Tenant $tenant, callable $callback): int
    {
        try {
            app(TenantContext::class)->set($tenant);
            return (int) ($callback() ?? self::SUCCESS);
        } catch (\Throwable $e) {
            $this->error("[{$tenant->subdomain}] erro: " . $e->getMessage());
            return self::FAILURE;
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    private function resolveTenant(?string $subdomain): ?Tenant
    {
        if (! $subdomain) {
            $disponiveis = Tenant::active()->orderBy('subdomain')->pluck('subdomain')->all();
            if (empty($disponiveis)) {
                $this->error('Nenhum tenant ativo cadastrado.');
                return null;
            }
            $subdomain = $this->choice('Selecione o tenant', $disponiveis);
        }

        $tenant = Tenant::active()->where('subdomain', $subdomain)->first();
        if (! $tenant) {
            $this->error("Tenant '{$subdomain}' não encontrado ou inativo.");
            return null;
        }
        return $tenant;
    }

    private function resolveAllSchemas () {}

}
