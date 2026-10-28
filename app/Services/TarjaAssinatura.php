<?php

declare(strict_types=1);

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Tarja lateral de autenticação em CADA página do documento assinado — padrão único dos sistemas
 * (gpe2, GPE Docs e SIAT, ADR-0030 do gpe2) e exigência do TR ("tarja lateral indicativa em todas as
 * páginas"): os quadros de assinatura ficam no lugar da assinatura (ou na última página); a tarja,
 * vertical na margem direita, identifica toda folha como parte de um documento assinado. Vale para a
 * assinatura por link e a ICP. O desenho é o que a ICP já usava (estilo 1doc): texto lido girando a
 * cabeça para a direita e o selo "GPE Docs" no pé.
 */
class TarjaAssinatura
{
    /** Desenha nas páginas 1..$paginasDocumento (o termo de evidências, depois delas, fica sem). */
    public static function desenhar(Fpdi $pdf, int $paginasDocumento, int $assinaturas, string $urlVerificar): void
    {
        $host = preg_replace('#^https?://#', '', $urlVerificar);
        $texto = sprintf('Assinado digitalmente por %d %s. Para verificar a validade das assinaturas, acesse %s',
            $assinaturas, $assinaturas === 1 ? 'pessoa' : 'pessoas', $host);

        for ($p = 1; $p <= $paginasDocumento; $p++) {
            $pdf->setPage($p);
            // O TCPDF guarda a quebra automática POR PÁGINA e o setPage a restaura: com ela ligada,
            // escrever na margem de baixo (o selo) joga o desenho para a página seguinte. Desliga aqui.
            [$quebra, $margem] = [$pdf->getAutoPageBreak(), $pdf->getBreakMargin()];
            $pdf->SetAutoPageBreak(false, 0);
            self::tarja($pdf, $texto);
            $pdf->SetAutoPageBreak($quebra, $margem);
        }
    }

    private static function tarja(Fpdi $pdf, string $texto): void
    {
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        $xLinha = $pageWidth - 8;   // 8 mm da margem direita

        // Texto: preto, lendo de cima para baixo (gire a cabeça para a DIREITA), entre o topo e o selo.
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7.5);
        $alturaTexto = $pageHeight - 40;
        $centerY = ($pageHeight - 30) / 2 + 5;
        $pdf->StartTransform();
        $pdf->Rotate(90, $xLinha + 2, $centerY);   // +90 = horário
        $pdf->SetXY($xLinha + 2 - ($alturaTexto / 2), $centerY - 1.2);
        // Último argumento 1 = encolhe a fonte se não couber: a tarja nunca sai da página.
        $pdf->Cell($alturaTexto, 3, $texto, 0, 0, 'C', false, '', 1);
        $pdf->StopTransform();

        // Selo "GPE Docs" no pé da tarja.
        $logoX = $xLinha - 1;
        $logoY = $pageHeight - 18;
        $pdf->SetFillColor(69, 181, 187);
        $pdf->RoundedRect($logoX, $logoY, 9, 9, 1.5, '1111', 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 4.5);
        $pdf->SetXY($logoX, $logoY + 1);
        $pdf->Cell(9, 2.5, 'GPE', 0, 1, 'C');
        $pdf->SetTextColor(255, 200, 100);
        $pdf->SetXY($logoX, $logoY + 4.5);
        $pdf->Cell(9, 2.5, 'Docs', 0, 1, 'C');
    }
}
