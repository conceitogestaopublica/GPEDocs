<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Paginas;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Conta as páginas dos anexos antigos (os novos são contados na indexação). Rodar uma vez após o deploy. */
class ContarPaginasAnexos extends Command
{
    protected $signature = 'anexos:contar-paginas {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Preenche proc_anexos.paginas dos anexos que ainda não foram contados';

    public function handle(TenantContext $ctx): int
    {
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));

        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            $disco = Storage::disk('documentos');
            $n = 0;
            DB::table('proc_anexos')->whereNull('paginas')->orderBy('id')->select(['id', 'arquivo_path', 'mime_type'])
                ->chunkById(200, function ($lote) use ($disco, &$n) {
                    foreach ($lote as $a) {
                        $p = $disco->exists($a->arquivo_path) ? Paginas::contar($disco->path($a->arquivo_path), $a->mime_type) : null;
                        // 0 = não contável (formato sem páginas ou arquivo ausente): não tenta de novo
                        DB::table('proc_anexos')->where('id', $a->id)->update(['paginas' => $p ?? 0]);
                        $n++;
                    }
                });
            $this->info("{$tenant->id}: {$n} anexo(s) contado(s)");
        }

        return self::SUCCESS;
    }
}
