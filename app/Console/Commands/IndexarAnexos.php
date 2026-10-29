<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\IndexarTextoArquivo;
use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Indexa para a busca o que já existia antes do OCR (TR 4.15.4): anexos de processo sem
 * texto e documentos do GED sem texto (escaneados). Só enfileira — a fila faz o OCR.
 *
 *   php artisan busca:indexar --tenant=ALL
 */
class IndexarAnexos extends Command
{
    protected $signature = 'busca:indexar {--tenant=ALL : id do município ou ALL} {--limite=5000 : máximo por município}';

    protected $description = 'Enfileira a leitura de texto (com OCR) de anexos de processo e documentos do GED ainda sem texto';

    public function handle(TenantContext $ctx): int
    {
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));
        $limite = (int) $this->option('limite');

        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            $anexos = DB::table('proc_anexos')->whereNull('indexado_em')->orderBy('id')->limit($limite)->pluck('id');
            $docs = DB::table('ged_documentos')->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('ocr_texto')->orWhere('ocr_texto', ''))
                ->where(fn ($q) => $q->where('mime_type', 'application/pdf')->orWhere('mime_type', 'like', 'image/%'))
                ->orderBy('id')->limit($limite)->pluck('id');
            foreach ($anexos as $id) {
                IndexarTextoArquivo::dispatch('anexo', (int) $id);
            }
            foreach ($docs as $id) {
                IndexarTextoArquivo::dispatch('documento', (int) $id);
            }
            $this->info("{$tenant->id}: {$anexos->count()} anexo(s) e {$docs->count()} documento(s) na fila");
        }

        return self::SUCCESS;
    }
}
