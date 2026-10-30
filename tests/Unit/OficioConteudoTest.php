<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Processo\Oficio;
use App\Support\HtmlSeguro;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Conteúdo do ofício na tela e no PDF.
 *
 * Antes: as variáveis dos modelos ({{destinatário}}…) saíam literais no PDF, e o HTML do editor rico
 * aparecia com as tags à mostra (`<p><strong>…`). Os dois caem numa prova de conceito na hora.
 */
class OficioConteudoTest extends TestCase
{
    private function oficio(string $conteudo): Oficio
    {
        $o = new Oficio([
            'numero'             => '012/2026',
            'assunto'            => 'Cessão de servidor',
            'conteudo'           => $conteudo,
            'destinatario_nome'  => 'Maria & Silva',
            'destinatario_cargo' => 'Secretária',
            'destinatario_orgao' => 'Câmara Municipal',
        ]);
        $o->enviado_em = Carbon::parse('2026-10-28 10:00');

        return $o;
    }

    public function test_troca_as_variaveis_com_e_sem_acento(): void
    {
        $html = $this->oficio('<p>À Sra. {{destinatário}}, {{ cargo }} da {{Órgão}} ({{orgao}}).</p><p>Ofício {{número}} — {{assunto}}, {{data}}.</p>')->conteudoFinal();

        $this->assertStringContainsString('Maria &amp; Silva', $html, 'valor escapado (o & não pode quebrar o HTML)');
        $this->assertStringContainsString('Secretária da Câmara Municipal (Câmara Municipal)', $html);
        $this->assertStringContainsString('Ofício 012/2026 — Cessão de servidor, 28 de outubro de 2026.', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_variavel_desconhecida_fica_visivel(): void
    {
        $this->assertStringContainsString('{{processo}}', $this->oficio('<p>Ref. {{processo}}</p>')->conteudoFinal());
    }

    public function test_valor_do_destinatario_nao_injeta_html(): void
    {
        $o = $this->oficio('<p>{{destinatario}}</p>');
        $o->destinatario_nome = '<script>alert(1)</script>';

        $this->assertStringNotContainsString('<script', $o->conteudoFinal());
    }

    public function test_mantem_a_formatacao_do_editor(): void
    {
        $entrada = '<p style="text-align: center; position: fixed">Texto <b>forte</b> <i>it</i> <u>sub</u></p>'
            . '<ul><li>um</li></ul><ol><li>dois</li></ol><font color="#ff0000" size="4">cor</font>'
            . '<a href="https://gov.br">link</a><hr><h2>Título</h2>';
        $html = HtmlSeguro::limpar($entrada);

        foreach (['<b>forte</b>', '<i>it</i>', '<u>sub</u>', '<ul><li>um</li></ul>', '<ol><li>dois</li></ol>', '<h2>Título</h2>', '<hr>', 'color="#ff0000"', 'href="https://gov.br"'] as $trecho) {
            $this->assertStringContainsString($trecho, $html);
        }
        $this->assertStringContainsString('style="text-align: center"', $html, 'só a propriedade permitida fica');
    }

    public function test_remove_o_que_e_perigoso(): void
    {
        $html = HtmlSeguro::limpar(
            '<p onclick="x()">a</p><script>alert(1)</script><iframe src="//x"></iframe>'
            . '<a href="javascript:alert(1)">b</a><img src=x onerror="y()"><span style="background:url(x)">c</span><!-- nota -->'
        );

        foreach (['onclick', '<script', 'alert', '<iframe', 'javascript:', '<img', 'onerror', 'url(', 'nota'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $html);
        }
        $this->assertStringContainsString('a', $html);
        $this->assertStringContainsString('c', $html, 'o texto de uma tag limpa continua');
    }

    public function test_texto_puro_antigo_vira_html_escapado_com_quebras(): void
    {
        $this->assertSame('Linha 1 &lt;x&gt;<br>' . "\n" . 'Linha 2', HtmlSeguro::limpar("Linha 1 <x>\nLinha 2"));
    }

    public function test_texto_puro_com_sinal_menor_nao_e_tratado_como_html(): void
    {
        $this->assertSame('valor &lt; 10', HtmlSeguro::limpar('valor < 10'));
    }
}
