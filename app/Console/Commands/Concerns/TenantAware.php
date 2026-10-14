<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;

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
 * Resolve a flag --tenant=<id|subdomínio|sub.domínio> (ou pergunta interativamente se ausente).
 * Só enxerga tenants do domínio do GPEDocs (TENANT_DOMINIO_BASE) — o landlord é
 * compartilhado com o gpe2 e os bancos de lá nunca entram nas rodadas daqui.
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

        // Sem --tenant mas com tenant JÁ ATIVO (chamado por `Artisan::call` de dentro de um job —
        // a finalização da conversão roda assim): executa nele, sem trocar nem limpar o contexto
        // (é do chamador). Antes caía no menu interativo, que sem terminal devolve nulo e o
        // comando saía com FAILURE em silêncio — `Artisan::call` não propaga o código.
        if (($opt === null || $opt === '') && app(TenantContext::class)->isSet()) {
            return (int) ($callback() ?? self::SUCCESS);
        }

        // Sem --tenant: menu interativo (inclui a opção "TODOS").
        if ($opt === null || $opt === '') {
            $opt = $this->escolherTenantInterativo();
            if ($opt === null) {
                return self::FAILURE;
            }
        }

        if (in_array(strtolower((string) $opt), ['all', 'todos', '*'], true)) {
            return $this->runForAllTenants($callback);
        }

        $tenant = $this->resolveTenant($opt);
        if (! $tenant) {
            return self::FAILURE;
        }

        return $this->runForOne($tenant, $callback);
    }

    /**
     * Base de tenants elegíveis: ativos E do domínio base configurado
     * (`multitenancy.dominio_base` ← env TENANT_DOMINIO_BASE, default ':8090'),
     * conforme salvo na coluna `domain`. Os comandos deste namespace operam só nesses.
     *
     * @return Builder<Tenant>
     */
    private function tenantsAtivos()
    {
        // O domínio já vem do escopo global `gpedocs` do model Tenant.
        return Tenant::active();
    }

    /** Rótulo legível de um tenant: `subdomínio.domínio — Nome (driver)`. */
    private function rotuloTenant(Tenant $t): string
    {
        return sprintf('%s.%s — %s (%s)', $t->subdomain, $t->domain, $t->nome, $t->driver);
    }

    /**
     * Menu interativo. Retorna 'ALL', o ID do tenant escolhido, ou null se não há tenants.
     * ID e não subdomínio: em dev os tenants compartilham subdomain='localhost'.
     */
    private function escolherTenantInterativo(): ?string
    {
        $tenants = $this->tenantsAtivos()->orderBy('domain')->get();
        if ($tenants->isEmpty()) {
            $this->error("Nenhum tenant ativo do domínio '".config('multitenancy.dominio_base')."' cadastrado.");

            return null;
        }

        $TODOS = 'TODOS os tenants';
        $mapa = [];
        foreach ($tenants as $t) {
            $mapa[$this->rotuloTenant($t)] = (string) $t->id;
        }

        $escolha = $this->choice('Selecione o tenant', array_merge(array_keys($mapa), [$TODOS]));

        return $escolha === $TODOS ? 'ALL' : $mapa[$escolha];
    }

    /**
     * Hook de confirmação antes de rodar em TODOS os tenants (via --tenant=ALL OU
     * escolha "TODOS" no menu). Retorne false para cancelar. Padrão: sem confirmação.
     * Comandos com operação destrutiva (ex.: --fresh/--rollback) devem sobrescrever.
     */
    protected function confirmarExecucaoAll(int $qtd): bool
    {
        return true;
    }

    private function runForAllTenants(callable $callback): int
    {
        $tenants = $this->tenantsAtivos()->orderBy('domain')->get();
        if ($tenants->isEmpty()) {
            $this->warn("Nenhum tenant ativo do domínio '".config('multitenancy.dominio_base')."' cadastrado no landlord.");

            return self::SUCCESS;
        }

        if (! $this->confirmarExecucaoAll($tenants->count())) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Executando para %d tenant(s)...', $tenants->count()));
        $worst = self::SUCCESS;

        foreach ($tenants as $t) {
            $this->newLine();
            $this->line("<bg=blue;fg=white> {$t->subdomain}.{$t->domain} </> — {$t->nome} ({$t->driver})");
            $exit = $this->runForOne($t, $callback);
            if ($exit !== self::SUCCESS) {
                $worst = $exit;
            }
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
            $this->error("[{$tenant->domain}] erro: ".$e->getMessage());

            return self::FAILURE;
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    /**
     * Resolve por ID, subdomínio, domínio ou FQDN (`sub.domínio`).
     *
     * Sem valor, abre o menu interativo. Retorna null tanto para "TODOS" quanto para
     * não encontrado — os comandos que chamam direto (db:mass-insert-users) tratam null como "rodar em todos".
     */
    private function resolveTenant(?string $valor = null): ?Tenant
    {
        if ($valor === null || $valor === '') {
            $valor = $this->escolherTenantInterativo();
            if ($valor === null || $valor === 'ALL') {
                return null;
            }
        }

        $tenant = $this->tenantsAtivos()->where(function ($q) use ($valor) {
            if (ctype_digit($valor)) {
                $q->where('id', (int) $valor);
            }
            $q->orWhere('subdomain', $valor)
                ->orWhere('domain', $valor)
                ->orWhereRaw("CONCAT(subdomain, '.', domain) = ?", [$valor]);
        })->first();

        if (! $tenant) {
            $this->error("Tenant '{$valor}' não encontrado, inativo ou fora do domínio '".config('multitenancy.dominio_base')."'.");

            return null;
        }

        return $tenant;
    }
}
