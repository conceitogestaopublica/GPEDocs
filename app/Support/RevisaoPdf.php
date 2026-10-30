<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Desenha as anotações da revisão sobre uma cópia do PDF (TR 4.48.16–4.48.18, 4.48.39): o
 * original não muda. Coordenadas das anotações são frações da página (0..1, origem no canto
 * superior esquerdo); medidas guardam o comprimento no papel (mm) e viram metros pela escala.
 */
final class RevisaoPdf
{
    public const SELOS = ['aprovado' => ['APROVADO', [22, 128, 61]], 'reprovado' => ['REPROVADO', [200, 30, 30]], 'revisar' => ['REVISAR', [202, 120, 0]]];

    /**
     * @param list<array<string, mixed>> $anotacoes
     * @param array<string, float> $escala pagina => metros por mm do papel
     */
    public static function aplicar(string $origem, array $anotacoes, array $escala, int $precisao, string $rodape): string
    {
        $fonte = PdfCompativel::caminho($origem);
        try {
            $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends Fpdi {
                public function __construct(...$a)
                {
                    parent::__construct(...$a);
                    $this->tcpdflink = false;
                }
            };
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);
            $total = $pdf->setSourceFile($fonte);
            $porPagina = collect($anotacoes)->groupBy(fn ($a) => (int) ($a['pagina'] ?? 1));
            for ($i = 1; $i <= $total; $i++) {
                $tpl = $pdf->importPage($i);
                $tam = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($tam['orientation'], [$tam['width'], $tam['height']]);
                $pdf->useTemplate($tpl);
                $metrosPorMm = (float) ($escala[(string) $i] ?? $escala['1'] ?? 0);
                foreach ($porPagina[$i] ?? [] as $a) {
                    self::desenhar($pdf, $a, (float) $tam['width'], (float) $tam['height'], $metrosPorMm, $precisao);
                }
                $pdf->SetAlpha(1);
                $pdf->SetFont('dejavusans', '', 6);
                $pdf->SetTextColor(90, 90, 90);
                $pdf->SetXY(4, $tam['height'] - 5);
                $pdf->Cell($tam['width'] - 8, 3, $rodape, 0, 0, 'L');
            }

            return $pdf->Output('', 'S');
        } catch (\Throwable $e) {
            throw new RuntimeException('Não foi possível gerar o PDF revisado: ' . $e->getMessage(), 0, $e);
        } finally {
            PdfCompativel::liberar($fonte, $origem);
        }
    }

    public static function medida(float $mmPapel, float $metrosPorMm, int $precisao, bool $area = false): string
    {
        if ($metrosPorMm <= 0) {
            return 'sem escala';
        }
        $valor = $area ? $mmPapel * $metrosPorMm * $metrosPorMm : $mmPapel * $metrosPorMm;

        return number_format($valor, max(0, min(4, $precisao)), ',', '.') . ($area ? ' m²' : ' m');
    }

    private static function desenhar(Fpdi $pdf, array $a, float $W, float $H, float $metrosPorMm, int $precisao): void
    {
        $cor = self::rgb((string) ($a['cor'] ?? '#dc2626'));
        $esp = max(0.2, min(3, (float) ($a['espessura'] ?? 0.6)));
        $estilo = ['width' => $esp, 'color' => $cor, 'cap' => 'round', 'join' => 'round'];
        $x = (float) ($a['x'] ?? 0) * $W;
        $y = (float) ($a['y'] ?? 0) * $H;
        $w = (float) ($a['w'] ?? 0) * $W;
        $h = (float) ($a['h'] ?? 0) * $H;
        $pts = array_map(fn ($p) => [(float) $p[0] * $W, (float) $p[1] * $H], (array) ($a['pontos'] ?? []));
        $pdf->SetAlpha(1);
        $pdf->SetDrawColorArray($cor);
        $pdf->SetLineStyle($estilo);

        switch ($a['tipo'] ?? '') {
            case 'retangulo':
                $pdf->Rect($x, $y, $w, $h, 'D');
                break;
            case 'elipse':
                $pdf->Ellipse($x + $w / 2, $y + $h / 2, abs($w / 2), abs($h / 2), 0, 0, 360, 'D');
                break;
            case 'marca':
                $pdf->SetAlpha(0.3);
                $pdf->SetFillColorArray($cor);
                $pdf->Rect($x, $y, $w, $h, 'F');
                break;
            case 'linha':
            case 'seta':
            case 'caneta':
                for ($k = 1; $k < count($pts); $k++) {
                    $pdf->Line($pts[$k - 1][0], $pts[$k - 1][1], $pts[$k][0], $pts[$k][1]);
                }
                if (($a['tipo'] ?? '') === 'seta' && count($pts) >= 2) {
                    [$p0, $p1] = [$pts[count($pts) - 2], $pts[count($pts) - 1]];
                    $pdf->Arrow($p0[0], $p0[1], $p1[0], $p1[1], 2, 3, 20);
                }
                break;
            case 'medida':
                if (count($pts) >= 2) {
                    [$p0, $p1] = [$pts[0], $pts[1]];
                    $pdf->Line($p0[0], $p0[1], $p1[0], $p1[1]);
                    $mm = (float) ($a['mm'] ?? hypot($p1[0] - $p0[0], $p1[1] - $p0[1]));
                    self::rotulo($pdf, ($p0[0] + $p1[0]) / 2, ($p0[1] + $p1[1]) / 2 - 3, self::medida($mm, $metrosPorMm, $precisao), $cor);
                }
                break;
            case 'area':
                $pdf->Rect($x, $y, $w, $h, 'D');
                $mm2 = (float) ($a['mm2'] ?? abs($w * $h));
                self::rotulo($pdf, $x + $w / 2, $y + $h / 2 - 2, self::medida($mm2, $metrosPorMm, $precisao, true), $cor);
                break;
            case 'texto':
                $pdf->SetFont('dejavusans', '', max(6, min(20, (float) ($a['tamanho'] ?? 9))));
                $pdf->SetTextColorArray($cor);
                $pdf->SetFillColor(255, 255, 220);
                $pdf->SetXY($x, $y);
                $pdf->MultiCell(max(20, $w ?: 60), 0, (string) ($a['texto'] ?? ''), ['LTRB' => ['width' => 0.2, 'color' => $cor]], 'L', true);
                break;
            case 'selo':
                [$texto, $c] = self::SELOS[$a['selo'] ?? 'aprovado'] ?? self::SELOS['aprovado'];
                $sw = max(30, $w);
                $sh = max(12, $h);
                $pdf->SetLineStyle(['width' => 0.8, 'color' => $c]);
                $pdf->RoundedRect($x, $y, $sw, $sh, 2, '1111', 'D');
                $pdf->SetTextColorArray($c);
                $pdf->SetFont('dejavusans', 'B', 11);
                $pdf->SetXY($x, $y + 1);
                $pdf->Cell($sw, 6, $texto, 0, 2, 'C');
                $pdf->SetFont('dejavusans', '', 6);
                $pdf->Cell($sw, 3, (string) ($a['texto'] ?? ''), 0, 0, 'C');
                break;
        }
    }

    private static function rotulo(Fpdi $pdf, float $cx, float $cy, string $texto, array $cor): void
    {
        $pdf->SetAlpha(1);
        $pdf->SetFont('dejavusans', 'B', 8);
        $l = $pdf->GetStringWidth($texto) + 3;
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColorArray($cor);
        $pdf->SetXY($cx - $l / 2, $cy - 2);
        $pdf->Cell($l, 4, $texto, ['LTRB' => ['width' => 0.2, 'color' => $cor]], 0, 'C', true);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return [220, 38, 38];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
