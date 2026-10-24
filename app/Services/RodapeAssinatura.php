<?php

declare(strict_types=1);

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Linha de autenticação no rodapé de CADA página do documento assinado — padrão único dos sistemas
 * (gpe2, GPE Docs e SIAT, ADR-0030 do gpe2): os quadros de assinatura ficam no lugar da assinatura
 * (ou na última página); o rodapé identifica toda folha como parte de um documento assinado, para
 * que uma página solta continue conferível. Vale para a assinatura por link e a ICP.
 */
class RodapeAssinatura
{
    /** Desenha nas páginas 1..$paginasDocumento (o termo de evidências, depois delas, fica sem). */
    public static function desenhar(Fpdi $pdf, int $paginasDocumento, int $assinaturas, string $urlVerificar): void
    {
        $host = preg_replace('#^https?://#', '', $urlVerificar);
        for ($p = 1; $p <= $paginasDocumento; $p++) {
            $pdf->setPage($p);
            // O TCPDF guarda a quebra automática POR PÁGINA e o setPage a restaura: com ela ligada,
            // escrever no pé joga a linha para o topo da página seguinte. Desliga aqui, depois do setPage.
            [$quebra, $margem] = [$pdf->getAutoPageBreak(), $pdf->getBreakMargin()];
            $pdf->SetAutoPageBreak(false, 0);

            $largura = $pdf->getPageWidth();
            $texto = sprintf('Documento assinado digitalmente · %d assinatura(s) · verifique em %s · página %d de %d',
                $assinaturas, $host, $p, $paginasDocumento);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetXY(10, $pdf->getPageHeight() - 7);
            // Último argumento 1 = encolhe a fonte se não couber: a linha nunca quebra nem sai da página.
            $pdf->Cell($largura - 20, 3.5, $texto, 0, 0, 'C', false, '', 1);

            $pdf->SetAutoPageBreak($quebra, $margem);
        }
    }
}
