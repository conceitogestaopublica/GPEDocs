<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Documento;
use App\Services\PdfTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill: percorre todos os documentos PDF sem `ocr_texto` preenchido,
 * extrai texto via PdfTextExtractor e atualiza. Util pra documentos antigos
 * (uploadados antes da extracao automatica) e para reindexar tudo de uma vez.
 *
 * Uso:
 *   php artisan ged:indexar-pdfs              (somente os sem ocr_texto)
 *   php artisan ged:indexar-pdfs --reindex    (reindexa todos os PDFs)
 *   php artisan ged:indexar-pdfs --id=8       (so o documento 8)
 */
class IndexarTextoPdfs extends Command
{
    protected $signature = 'ged:indexar-pdfs
        {--reindex : Reindexa todos os PDFs (mesmo os que ja tem ocr_texto)}
        {--id= : Indexa apenas o documento com este id}';

    protected $description = 'Extrai texto dos PDFs do GED para indexar a busca full-text';

    public function handle(PdfTextExtractor $extractor): int
    {
        $query = Documento::withoutGlobalScopes()
            ->where('mime_type', 'application/pdf')
            ->whereHas('versaoAtual')
            ->with('versaoAtual');

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        } elseif (! $this->option('reindex')) {
            $query->where(fn ($q) => $q->whereNull('ocr_texto')->orWhere('ocr_texto', ''));
        }

        $total = $query->count();
        $this->info("Documentos PDF para indexar: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $atualizados = 0;
        $semTexto = 0;
        $erros = 0;

        $query->chunkById(50, function ($docs) use ($extractor, $bar, &$atualizados, &$semTexto, &$erros) {
            foreach ($docs as $doc) {
                $bar->advance();

                $versao = $doc->versaoAtual;
                if (! $versao || ! $versao->arquivo_path) {
                    $erros++;
                    continue;
                }

                if (! Storage::disk('documentos')->exists($versao->arquivo_path)) {
                    $erros++;
                    continue;
                }

                $absoluto = Storage::disk('documentos')->path($versao->arquivo_path);
                $texto = $extractor->extrair($absoluto);

                if ($texto === null || $texto === '') {
                    $semTexto++;
                    continue;
                }

                $doc->ocr_texto = $texto;
                $doc->save();
                $atualizados++;
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Atualizados: {$atualizados}");
        $this->warn("⚠️  Sem texto extraivel (PDFs scaneados/imagem): {$semTexto}");
        if ($erros > 0) {
            $this->error("❌ Erros (arquivo nao encontrado): {$erros}");
        }

        return self::SUCCESS;
    }
}
