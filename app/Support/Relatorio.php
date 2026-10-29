<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/**
 * PDF dos relatórios no padrão da casa (cabeçalho e rodapé de pdf.partials.cabecalho-ug). O dompdf não
 * conhece o total de páginas no CSS, então o "Página N de M" é escrito no rodapé depois de renderizar.
 */
class Relatorio
{
    public static function pdf(string $view, array $dados = []): DomPdf
    {
        $pdf = Pdf::loadView($view, $dados)->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(function (int $n, int $total, Canvas $canvas, FontMetrics $metricas) {
            $fonte = $metricas->getFont('helvetica');
            $texto = "Página {$n} de {$total}";
            $tamanho = 6;
            $largura = $metricas->getTextWidth($texto, $fonte, $tamanho);
            // Margem direita de 12mm e segunda linha do rodapé (ver .rel-rod no partial)
            $canvas->text($canvas->get_width() - 34.02 - $largura, $canvas->get_height() - 28.5, $texto, $fonte, $tamanho);
        });

        return $pdf;
    }
}
