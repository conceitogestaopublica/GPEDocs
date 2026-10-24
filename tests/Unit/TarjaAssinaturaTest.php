<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TarjaAssinatura;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Tarja lateral de autenticação (padrão único dos sistemas e exigência do TR: "tarja lateral indicativa
 * em todas as páginas"): TODA página do documento leva a tarja — uma folha solta tem de continuar
 * identificável como parte de um documento assinado. O termo de evidências, depois delas, fica sem.
 *
 * Armadilha que o teste trava: o TCPDF guarda a quebra automática POR PÁGINA; com ela ligada na criação
 * da página, desenhar o selo no pé jogava a tarja da página N para a N+1 (a página 1 ficava sem).
 */
class TarjaAssinaturaTest extends TestCase
{
    /** Conteúdo (stream) de cada página, antes do Output. */
    private function paginas(Fpdi $pdf): array
    {
        return (new ReflectionProperty(\TCPDF::class, 'pages'))->getValue($pdf);
    }

    public function test_toda_pagina_do_documento_leva_a_tarja_e_o_termo_nao(): void
    {
        foreach ([true, false] as $quebra) {
            $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak($quebra, 20);   // margem padrão do TCPDF (2 cm): o selo fica dentro dela
            foreach ([1, 2, 3] as $p) {
                $pdf->AddPage();
                $pdf->Write(0, "conteudo {$p}");
            }

            TarjaAssinatura::desenhar($pdf, 2, 3, 'https://ged.teste/verificar/abc');   // página 3 = termo
            $paginas = $this->paginas($pdf);

            $this->assertSame(3, $pdf->getNumPages(), 'a tarja criou página nova');
            foreach ([1, 2] as $p) {
                $this->assertStringContainsString('3 pessoas', $paginas[$p],
                    "a página {$p} saiu sem a tarja (quebra automática ".($quebra ? 'ligada' : 'desligada').')');
                $this->assertStringContainsString('ged.teste/verificar/abc', $paginas[$p], 'a tarja não diz onde conferir');
                // O selo fica no pé da margem: é ele que a quebra automática jogava para a página seguinte.
                $this->assertStringContainsString('Docs', $paginas[$p], "o selo GPE Docs da página {$p} foi parar em outra página");
            }
            $this->assertStringNotContainsString('3 pessoas', $paginas[3], 'o termo de evidências também ganhou tarja');
            $this->assertStringNotContainsString('Docs', $paginas[3], 'o selo de outra página caiu no termo de evidências');
        }
    }
}
