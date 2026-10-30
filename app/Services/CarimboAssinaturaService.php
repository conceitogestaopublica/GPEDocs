<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assinatura;
use App\Models\Documento;
use App\Support\PdfCompativel;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Os dois arquivos de um documento assinado:
 *
 *  - ORIGINAL ASSINADO: o PDF da versão, intocado, recebendo cada assinatura ICP
 *    (e o selo da prefeitura sobre as assinaturas por link) de forma incremental
 *    (PadesIncremental) — todas continuam válidas no validar.iti.gov.br.
 *
 *  - CÓPIA PARA IMPRESSÃO: a versão remontada com o carimbo visual de cada
 *    assinatura (na posição escolhida na tela; ICP sem posição vai no rodapé da
 *    última página), a tarja lateral e a página de termo com as evidências,
 *    selada pela prefeitura quando há e-CNPJ. É refeita a cada assinatura, então
 *    a mais recente traz todas. Desenhar no original seria alteração depois da
 *    assinatura e invalidaria as anteriores.
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
     * Assinatura por link (já com status assinado e código): sela o original com o
     * e-CNPJ da prefeitura, se houver, e gera a cópia para impressão.
     *
     * @return array{caminho: ?string, impressao: string, selo: ?array}
     *         caminho = novo original assinado (null sem selo: o original não muda)
     */
    public function gerar(Assinatura $atual): array
    {
        $documento = $atual->documento;
        $versao = $documento?->versaoAtual;
        if (! $versao || ! str_ends_with(strtolower((string) $versao->arquivo_path), '.pdf')) {
            throw new RuntimeException('Somente documentos em PDF podem ser assinados por link.');
        }

        $disk = Storage::disk('documentos');
        $selo = $this->selos->material($documento->ug_id);

        $caminho = null;
        if ($selo) {
            $origem = $disk->path($documento->caminhoOficial() ?? $versao->arquivo_path);
            $caminho = sprintf(TenantStorage::pasta('assinaturas/externas') . '/%s_doc%d_ass%d_original.pdf',
                date('Ymd_His'), $atual->documento_id, $atual->id);
            $disk->put($caminho, $this->selar($origem, $selo, 'Selo da prefeitura sobre assinatura eletrônica por link (Lei 14.063/2020)', $documento));
        }

        return [
            'caminho'   => $caminho,
            'impressao' => $this->copiaImpressao($documento, $atual),
            'selo'      => $selo ? [
                'cn'         => $selo['meta']['subject_cn'] ?? null,
                'cnpj'       => $selo['registro']->cnpj,
                'icp_brasil' => $selo['registro']->icp_brasil,
                'thumbprint' => $selo['registro']->thumbprint_sha256,
            ] : null,
        ];
    }

    /**
     * Cópia para impressão com todas as assinaturas já gravadas do documento.
     * Devolve o caminho no disco documentos.
     */
    public function copiaImpressao(Documento $documento, Assinatura $atual): string
    {
        $versao = $documento->versaoAtual;
        if (! $versao) {
            throw new RuntimeException('Documento sem versão para carimbar.');
        }

        $disk = Storage::disk('documentos');
        $origem = $disk->path($versao->arquivo_path);
        if (! is_file($origem) || ! str_ends_with(strtolower((string) $versao->arquivo_path), '.pdf')) {
            throw new RuntimeException('Somente documentos em PDF recebem a cópia para impressão.');
        }

        $carimbos = Assinatura::with(['certificado', 'signatario:id,name'])
            ->where('documento_id', $documento->id)
            ->where('status', 'assinado')
            ->where(fn ($q) => $q->where('tipo_signatario', 'externo')->orWhere('tipo_assinatura', 'qualificada'))
            ->orderBy('assinado_em')
            ->get();

        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $selo = $this->selos->material($documento->ug_id);

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

            $semPosicao = 0;
            foreach ($carimbos as $a) {
                $this->desenhar($pdf, $a, $total, $semPosicao);
            }
            $assinadas = Assinatura::where('documento_id', $documento->id)->where('status', 'assinado')->count();
            TarjaAssinatura::desenhar($pdf, $total, $assinadas, url('/verificar/' . $documento->qr_code_token));

            $this->paginaTermo($pdf, $documento, $versao, $total, $selo);

            $bytes = $pdf->Output('', 'S');
        } finally {
            PdfCompativel::liberar($fonte, $origem);
        }

        $caminho = sprintf(TenantStorage::pasta('assinaturas/impressao') . '/%s_doc%d_ass%d.pdf',
            date('Ymd_His'), $documento->id, $atual->id);
        if ($selo) {
            $tmp = tempnam(sys_get_temp_dir(), 'ged_copia_');
            file_put_contents($tmp, $bytes);
            try {
                $bytes = $this->selar($tmp, $selo, 'Cópia para impressão selada pela prefeitura (Lei 14.063/2020)', $documento);
            } finally {
                @unlink($tmp);
            }
        }
        $disk->put($caminho, $bytes);

        return $caminho;
    }

    /** Selo da prefeitura (e-CNPJ) acrescentado ao PDF de forma incremental, no padrão AD-RB. */
    private function selar(string $caminhoAbsoluto, array $selo, string $razao, Documento $documento): string
    {
        [$pdf, $byteRange] = PadesIncremental::preparar($caminhoAbsoluto, [
            'nome'    => $selo['meta']['subject_cn'] ?? '',
            'razao'   => $razao,
            'local'   => 'Brasil',
            'contato' => url('/verificar/' . $documento->qr_code_token),
        ]);
        $atributos = CmsAdRb::atributosAssinados($selo['cert'], PadesPlaceholder::digest($pdf, $byteRange));
        $cms = CmsAdRb::envelope($selo['cert'], $selo['extracerts'] ?? [], $atributos, CmsAdRb::assinarComChave($atributos, $selo['pkey']));

        return PadesPlaceholder::embutir($pdf, $byteRange, $cms);
    }

    /**
     * Página final "Termo de assinatura eletrônica": todas as assinaturas do documento com
     * as evidências de cada uma (como se identificou, quando, de onde, código) e o QR da
     * verificação pública. Refeita a cada assinatura, então sempre traz a lista completa.
     */
    private function paginaTermo(Fpdi $pdf, Documento $documento, $versao, int $paginasDocumento, ?array $selo): void
    {
        $assinaturas = Assinatura::with(['signatario:id,name', 'certificado:id,subject_cn,issuer_cn'])
            ->where('documento_id', $documento->id)
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
            ? 'Esta cópia para impressão está <b>selada digitalmente</b> por ' . $e($selo['meta']['subject_cn'] ?? '')
              . ($selo['registro']->cnpj ? ' (CNPJ ' . $e($selo['registro']->cnpj) . ')' : '')
              . ', certificado ' . ($selo['registro']->icp_brasil ? 'ICP-Brasil' : '<b>de teste, sem validade ICP-Brasil</b>')
              . ' emitido por ' . $e($selo['meta']['issuer_cn'] ?? '') . '. Qualquer alteração no arquivo invalida o selo.'
            : 'Esta cópia para impressão não tem selo digital da prefeitura (certificado não configurado). A integridade é conferida pelo hash e pela verificação pública.';
        $seloTexto .= ' As assinaturas com certificado ICP-Brasil estão no <b>original assinado</b>, disponível no endereço de verificação,'
            . ' e podem ser conferidas em validar.iti.gov.br.';
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

    private function desenhar(Fpdi $pdf, Assinatura $a, int $totalPaginas, int &$semPosicao): void
    {
        $pos = $a->signature_position;
        if (! isset($pos['x'], $pos['y'])) {
            if ($a->tipo_assinatura !== 'qualificada') {
                return;
            }
            // ICP sem quadro escolhido: rodapé da última página, da direita para
            // a esquerda e subindo a cada linha cheia, para não sobrepor
            $pdf->setPage($totalPaginas);
            $porLinha = max(1, (int) floor(($pdf->getPageWidth() - 10) / (self::LARGURA_PADRAO + 3)));
            $coluna = $semPosicao % $porLinha;
            $linha = intdiv($semPosicao, $porLinha);
            $semPosicao++;
            $pos = [
                'page' => $totalPaginas,
                'x'    => $pdf->getPageWidth() - 8 - (self::LARGURA_PADRAO + 3) * ($coluna + 1) + 3,
                'y'    => $pdf->getPageHeight() - 8 - (self::ALTURA_PADRAO + 3) * ($linha + 1) + 3,
            ];
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
