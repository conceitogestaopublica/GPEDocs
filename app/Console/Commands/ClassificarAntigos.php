<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Classificacao;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;

/**
 * Classificação retroativa (TR 4.27.10): dá aos itens antigos sem classificação a do
 * assunto ou do módulo, como se tivessem sido criados agora. Fica no histórico como automática.
 *
 *   php artisan classificacao:aplicar --tenant=ALL
 */
class ClassificarAntigos extends Command
{
    protected $signature = 'classificacao:aplicar {--tenant=ALL : id do município ou ALL} {--modulo= : processo, memorando, oficio, circular ou documento}';

    protected $description = 'Classifica pelo plano de classificação os itens antigos que ainda não têm classificação';

    public function handle(TenantContext $ctx): int
    {
        $modulo = $this->option('modulo') ?: null;
        if ($modulo && ! isset(Classificacao::MODULOS[$modulo])) {
            $this->error('Módulo inválido.');

            return self::FAILURE;
        }
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));

        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            $feitos = Classificacao::retroativa($modulo);
            $this->info("{$tenant->id}: " . collect($feitos)->map(fn ($n, $m) => "{$m} {$n}")->implode(', '));
        }

        return self::SUCCESS;
    }
}
