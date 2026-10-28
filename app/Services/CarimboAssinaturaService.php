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

    public function __construct(private readonly SeloInstitucionalService $selos)
    {
    }

    /**
     * Gera o PDF carimbado para a assinatura (já com status assinado e código), com a página
     * de termo de evidências no fim e, se a UG tem certificado, o selo digital da prefeitura.
     *
     * @return array{caminho:string, selo:?array} selo = {cn, cnpj, icp_brasil, thumbprint}
     */
    public function gerar(Assinatura $atual): array
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

        $selo = $this->selos->material($documento->ug_id);
        $cadeiaArquivo = null;

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
            $assinadas = Assinatura::where('documento_id', $atual->documento_id)->where('status', 'assinado')->count();
            TarjaAssinatura::desenhar($pdf, $total, $assinadas, url('/verificar/' . $documento->qr_code_token));

            $this->paginaTermo($pdf, $atual, $versao, $total, $selo);

            if ($selo) {
                // extracerts do TCPDF exige caminho de arquivo (ver AssinaturaIcpService).
                if ($selo['extracerts']) {
                    $cadeiaArquivo = tempnam(sys_get_temp_dir(), 'ged_selo_');
                    file_put_contents($cadeiaArquivo, implode("\n", $selo['extracerts']));
                }
                $pdf->setSignature($selo['cert'], $selo['pkey'], '', $cadeiaArquivo ?: '', 2, [
                    'Name'        => $selo['meta']['subject_cn'] ?? '',
                    'Location'    => 'Brasil',
                    'Reason'      => 'Selo da prefeitura sobre assinaturas eletrônicas (Lei 14.063/2020)',
                    'ContactInfo' => url('/verificar/' . $documento->qr_code_token),
                ]);
                // Área do selo no rodapé do termo (última página).
                $pdf->setSignatureAppearance(15, 262, 120, 18);
            }

            $bytes = $pdf->Output('', 'S');
        } finally {
            PdfCompativel::liberar($fonte, $origem);
            if ($cadeiaArquivo && is_file($cadeiaArquivo)) {
                @unlink($cadeiaArquivo);
            }
        }
        $caminho = sprintf(TenantStorage::pasta('assinaturas/externas') . '/%s_doc%d_ass%d.pdf',
            date('Ymd_His'), $atual->documento_id, $atual->id);
        $disk->put($caminho, $bytes);

        return [
            'caminho' => $caminho,
            'selo'    => $selo ? [
                'cn'         => $selo['meta']['subject_cn'] ?? null,
                'cnpj'       => $selo['registro']->cnpj,
                'icp_brasil' => $selo['registro']->icp_brasil,
                'thumbprint' => $selo['registro']->thumbprint_sha256,
            ] : null,
        ];
    }

    /**
     * Página final "Termo de assinatura eletrônica": todas as assinaturas do documento com
     * as evidências de cada uma (como se identificou, quando, de onde, código) e o QR da
     * verificação pública. Refeita a cada assinatura, então sempre traz a lista completa.
     */
    private function paginaTermo(Fpdi $pdf, Assinatura $atual, $versao, int $paginasDocumento, ?array $selo): void
    {
        $documento = $atual->documento;
        $assinaturas = Assinatura::with(['signatario:id,name', 'certificado:id,subject_cn,issuer_cn'])
            ->where('documento_id', $atual->documento_id)
            ->where('status', 'assinado')
            ->orderBy('assinado_em')
            ->get();
        $orgao = \App\Models\Ug::withoutGlobalScopes()->find($documento->ug_id)?->nome ?? '';
        $urlVerificar = url('/verificar/' . $documento->qr_code_token);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $pdf->AddPage('P', 'A4');
        $pdf->SetMargins(15, 15, 15);

        $pdf->SetFillColor(8, 145, 178);
        $pdf->Rect(0, 0, 210, 26, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(15, 6);
        $pdf->Cell(150, 7, 'TERMO DE ASSINATURA ELETRÔNICA', 0, 1);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetX(15);
        $pdf->Cell(150, 5, mb_strimwidth($orgao, 0, 90, '...'), 0, 1);
        $pdf->write2DBarcode($urlVerificar, 'QRCODE,M', 178, 3, 20, 20, ['fgcolor' => [255, 255, 255], 'bgcolor' => false]);

        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetXY(15, 32);
        $html = '<table cellpadding="2" style="font-size:8.5pt">'
            . '<tr><td width="28%"><b>Documento</b></td><td width="72%">' . $e($documento->nome) . '</td></tr>'
            . '<tr><td><b>Páginas</b></td><td>' . $paginasDocumento . ' (este termo é a página ' . ($paginasDocumento + 1) . ')</td></tr>'
            . '<tr><td><b>Hash SHA-256 do original</b></td><td style="font-family:courier;font-size:7.5pt">' . $e($versao->hash_sha256) . '</td></tr>'
            . '<tr><td><b>Verificação</b></td><td>' . $e($urlVerificar) . '</td></tr>'
            . '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(2);

        $linhas = '';
        foreach ($assinaturas as $a) {
            $forma = match (true) {
                $a->externo                          => 'Por link — cadastro com CPF, e-mail confirmado e senha',
                $a->tipo_assinatura === 'qualificada' => 'Certificado ICP-Brasil' . ($a->certificado?->issuer_cn ? ' (' . $a->certificado->issuer_cn . ')' : ''),
                default                              => 'Servidor — login no sistema e CPF',
            };
            $linhas .= '<tr>'
                . '<td width="27%"><b>' . $e($a->nome_exibicao) . '</b><br/>CPF ' . $e(ConviteAssinaturaService::mascararCpf($a->cpf_signatario)) . '</td>'
                . '<td width="29%">' . $e($forma) . ($a->email_signatario ? '<br/>' . $e($a->email_signatario) : '') . '</td>'
                . '<td width="17%">' . $e(self::horaBrasilia($a->assinado_em)) . '<br/>IP ' . $e($a->ip ?: '—') . '</td>'
                . '<td width="27%" style="font-family:courier">' . $e($a->codigo_verificacao ?: '—') . '</td>'
                . '</tr>';
        }
        $pdf->writeHTML('<table border="0.3" cellpadding="3" style="font-size:7.5pt;border-color:#cbd5e1">'
            . '<tr style="background-color:#ecfeff;font-weight:bold"><td width="27%">Signatário</td><td width="29%">Forma de identificação</td>'
            . '<td width="17%">Data/hora (Brasília)</td><td width="27%">Código de verificação</td></tr>'
            . $linhas . '</table>', true, false, true, false, '');
        $pdf->Ln(3);

        $seloTexto = $selo
            ? 'Este arquivo está <b>selado digitalmente</b> por ' . $e($selo['meta']['subject_cn'] ?? '')
              . ($selo['registro']->cnpj ? ' (CNPJ ' . $e($selo['registro']->cnpj) . ')' : '')
              . ', certificado ' . ($selo['registro']->icp_brasil ? 'ICP-Brasil' : '<b>de teste, sem validade ICP-Brasil</b>')
              . ' emitido por ' . $e($selo['meta']['issuer_cn'] ?? '') . '. Qualquer alteração no arquivo invalida o selo.'
            : 'Este arquivo não tem selo digital da prefeitura (certificado não configurado). A integridade é conferida pelo hash e pela verificação pública.';
        $pdf->writeHTML('<p style="font-size:8pt">' . $seloTexto . '</p>'
            . '<p style="font-size:7.5pt;color:#555">Assinaturas eletrônicas nos termos da Lei nº 14.063/2020 e do art. 10, § 2º, da MP nº 2.200-2/2001. '
            . 'Para conferir, acesse o endereço de verificação acima ou leia o QR code; cada assinatura também pode ser consultada pelo seu código.</p>',
            true, false, true, false, '');
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
