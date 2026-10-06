<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Console\Commands\Concerns\TenantAware;
use Illuminate\Console\Command;

/**
 * Roda migrations em todos os tenants do GPEDocs (ou um específico).
 *
 *   php artisan tenant:migrate                          # interativo: pergunta o tenant
 *   php artisan tenant:migrate --tenant=paraguacu       # subdomínio, sub.domínio ou ID
 *   php artisan tenant:migrate --tenant=ALL             # todos os tenants ativos
 *   php artisan tenant:migrate --tenant=ALL --fresh     # cuidado!
 *   php artisan tenant:migrate --tenant=ALL --pretend   # dry-run
 *   php artisan tenant:migrate --tenant=paraguacu --rollback
 *   php artisan tenant:migrate --tenant=paraguacu --rollback --step=2
 *   php artisan tenant:migrate --tenant=paraguacu --status   # aplicadas/pendentes (1 banco)
 *
 * Só enxerga tenants do domínio do GPEDocs (TENANT_DOMINIO_BASE) — os bancos do gpe2,
 * cadastrados no mesmo landlord, nunca entram. Diferente do gpe2, aqui as migrations
 * CRIAM o schema (não há template SQL), então banco vazio é ponto de partida válido.
 */
class TenantMigrate extends Command
{
    use TenantAware;

    protected $signature = 'tenant:migrate
        {--tenant= : Tenant: subdomínio, sub.domínio ou ID (ALL para todos)}
        {--fresh : Recria o banco do tenant (cuidado!)}
        {--rollback : Reverte a última leva de migrations}
        {--step= : Nº de levas a reverter (com --rollback)}
        {--status : Lista migrations aplicadas/pendentes de UM tenant (não altera nada)}
        {--pretend : Apenas mostra o SQL que rodaria}
        {--path= : Caminho específico das migrations}';

    protected $description = 'Roda migrations no banco de tenant(s) — todos ou específico';

    public function handle(): int
    {
        // --status é leitura de UM banco: a tabela do migrate:status não identifica de
        // quem é cada linha, então empilhá-la para N tenants produz um paredão que não
        // se lê. Exige tenant individual — inclusive na escolha "TODOS" do menu
        // interativo, barrada em confirmarExecucaoAll().
        if ($this->option('status') && $this->tenantEhTodos()) {
            $this->error('--status roda em um banco por vez. Informe --tenant=<subdominio>.');

            return self::FAILURE;
        }

        $this->info("Identificando tenants somente do GPEDOCS (domínio '".config('multitenancy.dominio_base')."')... ");

        return $this->runForTenant(function () {
            // migrate:status é read-only e não aceita --force/--pretend.
            if ($this->option('status')) {
                $opts = ['--database' => 'tenant'];
                if ($this->option('path')) {
                    $opts['--path'] = $this->option('path');
                }

                return $this->call('migrate:status', $opts);
            }

            $opts = [
                '--database' => 'tenant',
                '--force' => true,
            ];
            if ($this->option('pretend')) {
                $opts['--pretend'] = true;
            }
            if ($this->option('path')) {
                $opts['--path'] = $this->option('path');
            }

            if ($this->option('fresh')) {
                $code = $this->call('migrate:fresh', $opts);
            } elseif ($this->option('rollback')) {
                if ($this->option('step')) {
                    $opts['--step'] = (int) $this->option('step');
                }
                $code = $this->call('migrate:rollback', $opts);
            } else {
                $code = $this->call('migrate', $opts);
            }

            return $code;
        });
    }

    /**
     * Confirmação antes de rodar em TODOS os tenants — dispara tanto para
     * --tenant=ALL quanto para a escolha "TODOS" no menu interativo.
     */
    protected function confirmarExecucaoAll(int $qtd): bool
    {
        if ($this->option('status')) {
            $this->error('--status roda em um banco por vez. Escolha um tenant no menu, ou use --tenant=<subdominio>.');

            return false;
        }
        if ($this->option('fresh')) {
            return $this->confirm("Isso vai DROPAR e recriar TODOS os schemas de {$qtd} tenant(s). Tem certeza?", false);
        }
        if ($this->option('rollback')) {
            return $this->confirm("Isso vai REVERTER migrations em TODOS os {$qtd} tenant(s). Tem certeza?", false);
        }

        return true;
    }

    /** true quando o --tenant informado pede TODOS os tenants (all/todos/*). */
    private function tenantEhTodos(): bool
    {
        return in_array(strtolower((string) $this->option('tenant')), ['all', 'todos', '*'], true);
    }
}
