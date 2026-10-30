<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Documento;
use App\Models\Processo\ProcessoAnexo;
use App\Services\Ocr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lê o texto de um anexo de processo ou de um documento do GED (PDF digital, PDF
 * escaneado ou imagem — OCR) para a busca (TR 4.15.1, 4.15.4). Roda na fila: o OCR
 * de um PDF escaneado leva segundos por página.
 */
class IndexarTextoArquivo extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $timeout = 900;

    public int $tries = 2;

    /** @param  'anexo'|'documento'  $tipo */
    public function __construct(public string $tipo, public int $id)
    {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        $ocr = app(Ocr::class);
        $disco = Storage::disk('documentos');

        if ($this->tipo === 'anexo') {
            $a = ProcessoAnexo::find($this->id);
            if (! $a || ! $disco->exists($a->arquivo_path)) {
                return;
            }
            $texto = $ocr->texto($disco->path($a->arquivo_path), (string) $a->mime_type);
            // páginas para os indicadores (TR 4.29.23): 0 = formato sem páginas
            DB::table('proc_anexos')->where('id', $a->id)->update(['texto_indexado' => $texto, 'indexado_em' => now(),
                'paginas' => \App\Support\Paginas::contar($disco->path($a->arquivo_path), (string) $a->mime_type) ?? 0]);

            return;
        }

        $doc = Documento::query()->withoutGlobalScopes()->with('versaoAtual')->find($this->id);
        $caminho = $doc?->versaoAtual?->arquivo_path;
        if (! $doc || ! $caminho || ! $disco->exists($caminho) || trim((string) $doc->ocr_texto) !== '') {
            return;   // já tem texto (PDF digital extraído no upload)
        }
        $texto = $ocr->texto($disco->path($caminho), (string) $doc->mime_type);
        if ($texto) {
            DB::table('ged_documentos')->where('id', $doc->id)->update(['ocr_texto' => $texto]);
        }
    }

    /** Enfileira sem derrubar quem gravou o arquivo (fila fora, sem prefeitura no contexto). */
    public static function enfileirar(string $tipo, int $id): void
    {
        try {
            static::dispatch($tipo, $id)->afterCommit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Indexação de texto não enfileirada', ['tipo' => $tipo, 'id' => $id, 'motivo' => $e->getMessage()]);
        }
    }
}
