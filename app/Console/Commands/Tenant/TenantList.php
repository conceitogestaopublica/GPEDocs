<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantList extends Command
{
    protected $signature = 'tenant:list {--all : Inclui inativos}';
    protected $description = 'Lista tenants cadastrados no landlord';

    public function handle(): int
    {
        $q = Tenant::query();
        if (! $this->option('all')) $q->active();

        $tenants = $q->orderBy('domain')->orderBy('subdomain')->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant cadastrado.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Subdomínio', 'Domínio base', 'Nome', 'UF', 'Banco', 'Host', 'Ativo'],
            $tenants->map(fn ($t) => [
                $t->id, $t->subdomain, $t->domain, $t->nome, $t->uf ?? '—',
                $t->db_name, $t->db_host, $t->active ? '✓' : '✗',
            ])->all()
        );
        $this->info($tenants->count() . ' tenant(s) listado(s).');
        return self::SUCCESS;
    }
}
