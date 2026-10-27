<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\Process\Process;

/**
 * O parser gratuito do FPDI não lê PDFs com object streams / xref comprimido (PDF 1.5+,
 * comum em PDFs exportados do Word). Antes de carimbar ou assinar, se o FPDI recusar o
 * arquivo, gera uma cópia temporária "achatada" com o qpdf (instalado na imagem Docker).
 *
 *   $caminho = PdfCompativel::caminho($original);   // original ou cópia temporária
 *   ... usa $caminho ...
 *   PdfCompativel::liberar($caminho, $original);     // apaga a cópia, se houve
 */
final class PdfCompativel
{
    public static function caminho(string $original): string
    {
        try {
            (new Fpdi())->setSourceFile($original);

            return $original;
        } catch (CrossReferenceException) {
            // segue para a conversão
        }

        $saida = tempnam(sys_get_temp_dir(), 'ged_pdf_');
        if ($saida === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para o PDF.');
        }

        $qpdf = new Process(['qpdf', '--object-streams=disable', '--stream-data=uncompress', $original, $saida]);
        $qpdf->setTimeout(60);
        $qpdf->run();

        // Código 3 = concluído com avisos (PDF levemente fora do padrão, mas regravado).
        if (! in_array($qpdf->getExitCode(), [0, 3], true) || ! is_file($saida) || filesize($saida) === 0) {
            @unlink($saida);
            throw new RuntimeException('Este PDF usa um formato que não pôde ser convertido para assinatura'
                . ($qpdf->getErrorOutput() ? ': ' . trim($qpdf->getErrorOutput()) : '.'));
        }

        return $saida;
    }

    public static function liberar(string $caminho, string $original): void
    {
        if ($caminho !== $original && is_file($caminho)) {
            @unlink($caminho);
        }
    }
}
