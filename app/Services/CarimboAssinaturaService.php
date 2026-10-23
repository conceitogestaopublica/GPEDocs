<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assinatura;
use App\Support\PdfCompativel;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Carimbo visual da assinatura eletrônica por link ("Assinado digitalmente por: NOME /
 * CPF mascarado / código / data"), desenhado na página e na posição que o signatário
 * escolheu na tela.
 *
 * O PDF é sempre remontado a partir da versão atual do documento, redesenhando os
 * carimbos de todas as assinaturas já feitas (externas e ICP com posição) — o mesmo
 * modelo da assinatura A1 (AssinaturaIcpService::gerarPdfAssinado). Assim a ordem em que
 * as pessoas assinam não importa e o arquivo mais recente tem todos os carimbos.
 */
class CarimboAssinaturaService
{
    /** Tamanho padrão do carimbo em mm (o signatário pode redimensionar). */
    public const LARGURA_PADRAO = 75.0;

    public const ALTURA_PADRAO = 20.0;

    /** Gera o PDF carimbado para a assinatura (já com status assinado e código) e grava no disco. */
    public function gerar(Assinatura $atual): string
    {
        $documento = $atual->documento;
        $versao = $documento?->versaoAtual;
        if (! $versao) {
            throw new RuntimeException('Documento sem versão para carimbar.');
        }

        $disk = Storage::disk('documentos');
        $origem = $disk->path($versao->arquivo_path);
        if (! is_file($origem) || ! str_ends_with(strtolower((string) $versao->arquivo_path), '.pdf')) {
            throw new RuntimeException('Somente documentos em PDF podem ser assinados por link.');
        }

        $carimbos = Assinatura::with(['certificado', 'signatario:id,name'])
            ->where('documento_id', $atual->documento_id)
            ->where('status', 'assinado')
            ->whereNotNull('signature_position')
            ->where(fn ($q) => $q->where('tipo_signatario', 'externo')->orWhere('tipo_assinatura', 'qualificada'))
            ->orderBy('assinado_em')
            ->get();

        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        // A cópia compatível (se houve) só sai depois do Output: o FPDI relê a origem ao gravar.
        $fonte = PdfCompativel::caminho($origem);
        try {
            $total = $pdf->setSourceFile($fonte);
            for ($i = 1; $i <= $total; $i++) {
                $tpl = $pdf->importPage($i);
                $tam = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($tam['orientation'], [$tam['width'], $tam['height']]);
                $pdf->useTemplate($tpl);
            }

            foreach ($carimbos as $a) {
                $this->desenhar($pdf, $a, $total);
            }

            $bytes = $pdf->Output('', 'S');
        } finally {
            PdfCompativel::liberar($fonte, $origem);
        }
        $caminho = sprintf(TenantStorage::pasta('assinaturas/externas') . '/%s_doc%d_ass%d.pdf',
            date('Ymd_His'), $atual->documento_id, $atual->id);
        $disk->put($caminho, $bytes);

        return $caminho;
    }

    /** O app grava em UTC; carimbo e telas do signatário mostram a hora de Brasília. */
    public static function horaBrasilia(?\DateTimeInterface $quando): string
    {
        return $quando ? \Illuminate\Support\Carbon::instance($quando)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') : '';
    }

    /** Posição do carimbo em mm a partir da tela (frações da página) — ver Assinar.jsx. */
    public static function posicaoDaTela(array $tela, float $larguraPagina, float $alturaPagina): array
    {
        $rotacao = (int) ($tela['rotacao'] ?? 0);

        return [
            'page'    => max(1, (int) $tela['pagina']),
            'x'       => round((float) $tela['x'] * $larguraPagina, 2),
            'y'       => round((float) $tela['y'] * $alturaPagina, 2),
            'w'       => round((float) $tela['w'] * $larguraPagina, 2),
            'h'       => round((float) $tela['h'] * $alturaPagina, 2),
            'rotacao' => in_array($rotacao, [0, 90, 180, 270], true) ? $rotacao : 0,
        ];
    }

    /** Tamanho (mm) de cada página do PDF da versão atual — a tela usa para converter a posição. */
    public function tamanhoPaginas(string $caminhoAbsoluto): array
    {
        $fonte = PdfCompativel::caminho($caminhoAbsoluto);
        try {
            $pdf = new Fpdi();
            $total = $pdf->setSourceFile($fonte);
            $paginas = [];
            for ($i = 1; $i <= $total; $i++) {
                $tam = $pdf->getTemplateSize($pdf->importPage($i));
                $paginas[] = ['w' => (float) $tam['width'], 'h' => (float) $tam['height']];
            }

            return $paginas;
        } finally {
            PdfCompativel::liberar($fonte, $caminhoAbsoluto);
        }
    }

    private function desenhar(Fpdi $pdf, Assinatura $a, int $totalPaginas): void
    {
        $pos = $a->signature_position;
        if (! isset($pos['x'], $pos['y'])) {
            return;
        }
        $pagina = (int) ($pos['page'] ?? -1);
        if ($pagina === -1 || $pagina > $totalPaginas) {
            $pagina = $totalPaginas;
        }
        $pdf->setPage(max(1, $pagina));

        $x = (float) $pos['x'];
        $y = (float) $pos['y'];
        $w = (float) ($pos['w'] ?? self::LARGURA_PADRAO);
        $h = (float) ($pos['h'] ?? self::ALTURA_PADRAO);
        $rotacao = (int) ($pos['rotacao'] ?? 0);

        $nome = $a->externo ? (string) $a->nome_signatario
            : (string) ($a->certificado?->subject_cn ?: $a->signatario?->name);
        $linhas = [
            ['Assinado digitalmente por:', '', 0.17],
            [mb_strimwidth($nome, 0, 60, '...'), 'B', 0.22],
            ['CPF: ' . ConviteAssinaturaService::mascararCpf($a->cpf_signatario)
                . '  |  ' . self::horaBrasilia($a->assinado_em), '', 0.16],
        ];
        if ($a->codigo_verificacao) {
            $linhas[] = ['Código de verificação: ' . $a->codigo_verificacao, '', 0.14];
            $linhas[] = ['Verifique em ' . preg_replace('#^https?://#', '', url('/verificar/codigo/' . $a->codigo_verificacao)), '', 0.12];
        }

        $pdf->StartTransform();
        if ($rotacao !== 0) {
            // Gira em torno do centro do quadro escolhido na tela. Em 90/270 o texto corre
            // na vertical: a caixa de texto troca largura por altura.
            $pdf->Rotate(-$rotacao, $x + $w / 2, $y + $h / 2);
            if ($rotacao === 90 || $rotacao === 270) {
                [$x, $y, $w, $h] = [$x + ($w - $h) / 2, $y + ($h - $w) / 2, $h, $w];
            }
        }

        $pdf->SetDrawColor(69, 181, 187);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($x, $y, $w, $h, 'D');

        // Fonte proporcional à altura do quadro (mm → pt: 1 mm ≈ 2,83 pt).
        $cursor = $y + $h * 0.06;
        foreach ($linhas as [$texto, $estilo, $fracao]) {
            $alturaLinha = $h * $fracao;
            $pdf->SetFont('helvetica', $estilo, max(4.0, $alturaLinha * 2.83 * 0.85));
            $pdf->SetTextColor($estilo === 'B' ? 30 : 70, $estilo === 'B' ? 30 : 70, $estilo === 'B' ? 30 : 70);
            $pdf->SetXY($x + 1.2, $cursor);
            $pdf->Cell($w - 2.4, $alturaLinha, $texto, 0, 0, 'L', false, '', 1);
            $cursor += $alturaLinha;
        }

        $pdf->StopTransform();
    }
}
