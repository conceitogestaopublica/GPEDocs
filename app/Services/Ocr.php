<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Texto de anexos para a busca (TR 4.15.1, 4.15.4): PDF digital pelo extrator de texto;
 * PDF escaneado e imagem pelo OCR (Tesseract em português; páginas rasterizadas com o
 * pdftoppm). Sem Tesseract no contêiner, escaneado fica sem texto, como antes.
 */
final class Ocr
{
    /** Páginas que o OCR lê no máximo (o resto do texto do PDF digital entra igual). */
    public const MAX_PAGINAS = 40;

    public function __construct(private readonly PdfTextExtractor $pdf)
    {
    }

    public static function disponivel(): bool
    {
        return (bool) trim((string) @shell_exec('command -v tesseract 2>/dev/null'));
    }

    /** Texto do arquivo, ou null se não houver como ler. */
    public function texto(string $caminho, string $mime): ?string
    {
        if (! is_file($caminho)) {
            return null;
        }
        if (str_contains($mime, 'pdf')) {
            $digital = $this->pdf->extrair($caminho);
            // Pouco texto em PDF com páginas: é escaneado (ou só tem a assinatura em texto)
            return mb_strlen((string) $digital) >= 50 ? $digital : ($this->ocrPdf($caminho) ?? $digital);
        }
        if (str_starts_with($mime, 'image/')) {
            return $this->tesseract($caminho);
        }
        if (str_starts_with($mime, 'text/')) {
            return $this->limpar((string) file_get_contents($caminho, false, null, 0, 2_000_000));
        }

        return null;
    }

    private function ocrPdf(string $caminho): ?string
    {
        if (! self::disponivel()) {
            return null;
        }
        $dir = storage_path('app/ocr-tmp/' . uniqid());
        File::ensureDirectoryExists($dir);
        try {
            $r = Process::timeout(600)->run(['pdftoppm', '-r', '200', '-gray', '-png', '-l', (string) self::MAX_PAGINAS, $caminho, "{$dir}/pg"]);
            if (! $r->successful()) {
                return null;
            }
            $partes = [];
            foreach (collect(File::files($dir))->sortBy(fn ($f) => $f->getFilename()) as $img) {
                $partes[] = $this->tesseract($img->getPathname());
            }

            return $this->limpar(implode(' ', array_filter($partes)));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function tesseract(string $imagem): ?string
    {
        if (! self::disponivel()) {
            return null;
        }
        $r = Process::timeout(180)->run(['tesseract', $imagem, 'stdout', '-l', 'por', '--psm', '3']);

        return $r->successful() ? $this->limpar($r->output()) : null;
    }

    private function limpar(string $texto): ?string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto)));

        return $texto === '' ? null : mb_substr($texto, 0, 500_000);
    }
}
