<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extrai texto de PDFs digitais (sem imagem/scan) para popular o campo
 * `ocr_texto` da tabela ged_documentos. Permite busca full-text em
 * todo o conteudo do documento via Repositorio.
 *
 * Para PDFs escaneados (apenas imagens), seria necessario rodar OCR
 * (Tesseract/AWS Textract/etc) — nao implementado aqui.
 */
class PdfTextExtractor
{
    /**
     * Extrai texto de um arquivo PDF. Retorna null em caso de erro
     * (PDF corrompido, encriptado, ou apenas imagens).
     */
    public function extrair(string $caminhoAbsoluto): ?string
    {
        if (! is_file($caminhoAbsoluto)) {
            return null;
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($caminhoAbsoluto);
            $texto = $pdf->getText();

            // Normaliza espacos em branco e remove caracteres de controle
            $texto = preg_replace('/\s+/u', ' ', $texto);
            $texto = preg_replace('/[\x00-\x1F\x7F]/u', '', $texto);
            $texto = trim((string) $texto);

            // Se nada foi extraido (PDF puramente imagem) retorna null
            return $texto !== '' ? $texto : null;
        } catch (Throwable $e) {
            // Logar mas nao quebrar o upload por causa de extracao falha
            \Log::warning('Falha ao extrair texto do PDF', [
                'arquivo' => $caminhoAbsoluto,
                'erro'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Versao a partir de bytes (para extrair sem persistir em arquivo).
     */
    public function extrairBytes(string $pdfBytes): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdfx_');
        if ($tmp === false) {
            return null;
        }
        try {
            file_put_contents($tmp, $pdfBytes);
            return $this->extrair($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
