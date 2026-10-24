<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RodapeAssinatura;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Rodapé de autenticação (padrão único dos sistemas): TODA página do documento leva a linha — uma folha
 * solta tem de continuar identificável como parte de um documento assinado. O termo de evidências, que
 * vem depois das páginas do documento, fica sem.
 *
 * Armadilha que o teste trava: o TCPDF guarda a quebra automática POR PÁGINA; com ela ligada na criação
 * da página, escrever no pé jogava o rodapé da página N para o topo da N+1 (a página 1 ficava sem).
 */
class RodapeAssinaturaTest extends TestCase
{
    /** Conteúdo (stream) de cada página, antes do Output. */
    private function paginas(Fpdi $pdf): array
    {
        $prop = new ReflectionProperty(\TCPDF::class, 'pages');

        return $prop->getValue($pdf);
    }

    public function test_toda_pagina_do_documento_leva_o_rodape_e_o_termo_nao(): void
    {
        foreach ([true, false] as $quebra) {
            $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak($quebra, 20);   // margem padrão do TCPDF (2 cm): o rodapé fica dentro dela
            foreach ([1, 2, 3] as $p) {
                $pdf->AddPage();
                $pdf->Write(0, "conteudo {$p}");
            }

            RodapeAssinatura::desenhar($pdf, 2, 3, 'https://ged.teste/verificar/abc');   // página 3 = termo
            $paginas = $this->paginas($pdf);

            $this->assertSame(3, $pdf->getNumPages(), 'o rodapé criou página nova');
            foreach ([1, 2] as $p) {
                $this->assertStringContainsString("{$p} de 2", $paginas[$p],
                    "a página {$p} saiu sem o rodapé dela (quebra automática ".($quebra ? 'ligada' : 'desligada').')');
                $this->assertStringContainsString('ged.teste/verificar/abc', $paginas[$p], 'o rodapé não diz onde conferir');
            }
            $this->assertStringNotContainsString('de 2', $paginas[3], 'o termo de evidências também ganhou rodapé');
        }
    }
}
