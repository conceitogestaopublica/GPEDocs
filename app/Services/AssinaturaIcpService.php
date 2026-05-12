<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assinatura;
use App\Models\Certificado;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF;

/**
 * Serviço de assinatura digital PAdES-BES (Lei 14.063/2020 art. 4, III - Qualificada).
 *
 * Fluxo:
 *   1) Recebe um PDF de origem + PFX/senha do signatário (A1)
 *   2) Importa as páginas do PDF original via FPDI dentro de um TCPDF
 *   3) Aciona TCPDF::setSignature() — TCPDF reserva /ByteRange e /Contents,
 *      gera o hash, monta o PKCS#7 detached via openssl_pkcs7_sign e embute
 *      o envelope no PDF resultante (assinatura PAdES-BES)
 *   4) Persiste o arquivo assinado em storage/app/assinaturas/icp/
 *   5) Atualiza o registro de Assinatura com metadados criptográficos
 *
 * Política adotada: AD-RB v2 (DOC-ICP-15.03 do ITI) — OID 2.16.76.1.7.1.1.2.3
 */
class AssinaturaIcpService
{
    private const POLITICA_OID = '2.16.76.1.7.1.1.2.3';
    private const POLITICA_NOME = 'AD-RB v2 (Assinatura Digital de Referência Básica)';

    public function __construct(
        private readonly CertificadoService $certificadoService,
    ) {
    }

    /**
     * Assina um PDF (caminho absoluto) com o certificado A1 fornecido.
     *
     * @param  string  $pdfOrigem      Caminho absoluto do PDF a assinar
     * @param  string  $pfxBinary      Conteúdo binário do .pfx/.p12
     * @param  string  $senhaPfx       Senha do PFX
     * @param  array   $razao          ['razao' => string, 'local' => string, 'contato' => string]
     * @return array                   ['caminho' => string relativo no disk local,
     *                                  'pkcs7'   => bytes do envelope assinado,
     *                                  'cadeia'  => string[] PEMs,
     *                                  'cert'    => string PEM,
     *                                  'meta'    => array de metadados]
     */
    public function assinarPdf(
        string $pdfOrigem,
        string $pfxBinary,
        string $senhaPfx,
        array $razao = [],
    ): array {
        if (! is_file($pdfOrigem)) {
            throw new RuntimeException("Arquivo PDF não encontrado: {$pdfOrigem}");
        }

        $material = $this->certificadoService->abrirPfx($pfxBinary, $senhaPfx);
        $certPem  = $material['cert'];
        $pkeyPem  = $material['pkey'];
        $cadeia   = $material['extracerts'];

        $meta = $this->certificadoService->lerMetadados($certPem);

        if (! $this->certificadoService->ehIcpBrasil($certPem)) {
            throw new RuntimeException(
                'O certificado informado não pertence à cadeia ICP-Brasil — assinatura qualificada exige cert. ICP-Brasil.'
            );
        }

        // Gera o PDF assinado a partir do PDF original importado via FPDI
        $pdfAssinado = $this->gerarPdfAssinado(
            pdfOrigem: $pdfOrigem,
            certPem: $certPem,
            pkeyPem: $pkeyPem,
            extracerts: $cadeia,
            razao: $razao['razao']   ?? 'Assinatura Eletrônica Qualificada (Lei 14.063/2020)',
            local: $razao['local']   ?? 'Brasil',
            contato: $razao['contato'] ?? ($meta['subject_cn'] ?? ''),
            meta: $meta,
            position: $razao['position'] ?? null,
            previousStamps: $razao['previous_stamps'] ?? [],
        );

        // Persiste o resultado
        $disk = Storage::disk('documentos');
        $nomeArquivo = sprintf(
            'assinaturas/icp/%s_%s.pdf',
            date('Ymd_His'),
            substr($meta['thumbprint_sha256'], 0, 12),
        );
        $disk->put($nomeArquivo, $pdfAssinado);

        // Extrai o PKCS#7 embutido no PDF (entre /Contents <...>) para auditoria
        $pkcs7 = $this->extrairPkcs7($pdfAssinado);

        return [
            'caminho' => $nomeArquivo,
            'pkcs7'   => $pkcs7,
            'cadeia'  => $cadeia,
            'cert'    => $certPem,
            'meta'    => $meta + [
                'politica_oid'  => self::POLITICA_OID,
                'politica_nome' => self::POLITICA_NOME,
                'algoritmo'     => 'SHA-256',
                'hash_pdf'      => hash('sha256', $pdfAssinado),
            ],
        ];
    }

    /**
     * Persiste o resultado da assinatura no registro de Assinatura.
     */
    public function registrarAssinatura(
        Assinatura $assinatura,
        Certificado $certificado,
        array $resultado,
        string $cpf,
        string $ip,
        ?string $geolocalizacao,
        ?string $userAgent,
    ): Assinatura {
        $assinatura->update([
            'status'                  => 'assinado',
            'tipo_assinatura'         => 'qualificada',
            'certificado_id'          => $certificado->id,
            'cpf_signatario'          => $cpf,
            'ip'                      => $ip,
            'geolocalizacao'          => $geolocalizacao,
            'user_agent'              => $userAgent,
            'hash_documento'          => $resultado['meta']['hash_pdf'] ?? null,
            // assinatura_pkcs7 nao e mais persistida no banco — o envelope
            // completo ja vive embutido em arquivo_assinado_path (PDF) e o
            // hash do envelope esta em hash_assinatura_sha256. Salvar bytes
            // binarios em coluna postgres causaria erro UTF-8. O envelope
            // pode ser re-extraido do PDF quando necessario via
            // AssinaturaValidadorService.
            'cadeia_certificados'     => array_map(
                fn (string $pem) => $this->resumoCert($pem),
                $resultado['cadeia']
            ),
            'politica_assinatura'     => self::POLITICA_NOME . ' (OID ' . self::POLITICA_OID . ')',
            'algoritmo_hash'          => 'SHA-256',
            'arquivo_assinado_path'   => $resultado['caminho'],
            'hash_assinatura_sha256'  => hash('sha256', $resultado['pkcs7']),
            'timestamp_assinatura'    => now(),
            'assinado_em'             => now(),
        ]);

        return $assinatura->refresh();
    }

    private function gerarPdfAssinado(
        string $pdfOrigem,
        string $certPem,
        string $pkeyPem,
        array $extracerts,
        string $razao,
        string $local,
        string $contato,
        array $meta = [],
        ?array $position = null,
        array $previousStamps = [],
    ): string {
        // signing_cert e private_key sao passados como PEM inline porque
        // openssl_pkcs7_sign no Windows com PHP 8.3 + OpenSSL 3 falha ao
        // decodificar PKCS#8 lido por path (error:1E08010C:DECODER unsupported).
        // Já extracerts (7º arg de openssl_pkcs7_sign) exige um caminho de arquivo.
        $extraFile = null;
        $cadeiaConcatenada = implode("\n", $extracerts);
        if ($cadeiaConcatenada !== '') {
            $extraFile = tempnam(sys_get_temp_dir(), 'ged_chain_');
            if ($extraFile !== false) {
                file_put_contents($extraFile, $cadeiaConcatenada);
            }
        }

        try {
            $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);

            $totalPaginas = $pdf->setSourceFile($pdfOrigem);
            for ($i = 1; $i <= $totalPaginas; $i++) {
                $tplId = $pdf->importPage($i);
                $size  = $pdf->getTemplateSize($tplId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);
            }

            // Conta total de signatarios (anteriores + atual) para a tarja lateral
            $totalSignatarios = count($previousStamps) + 1;

            // Tarja vertical na margem direita de cada pagina (estilo 1doc)
            for ($p = 1; $p <= $totalPaginas; $p++) {
                $pdf->setPage($p);
                $this->desenharTarjaLateral($pdf, $totalSignatarios);
            }

            // Primeiro, redesenha carimbos das assinaturas ICP anteriores (visualmente
            // — não preserva criptografia, que é trabalho do PAdES Part 2; mantemos
            // o registro auditável no banco e nos PDFs anteriores). Cada uma na sua
            // própria posição.
            foreach ($previousStamps as $prev) {
                if (empty($prev['position']) || empty($prev['meta'])) continue;
                $pos = $prev['position'];
                if (!isset($pos['x'], $pos['y'], $pos['w'], $pos['h'])) continue;
                $pageAlvo = (int) ($pos['page'] ?? -1);
                if ($pageAlvo === -1 || $pageAlvo > $totalPaginas) $pageAlvo = $totalPaginas;
                if ($pageAlvo < 1) $pageAlvo = 1;
                $pdf->setPage($pageAlvo);
                // Usa data anterior em vez da data atual
                $metaPrev = $prev['meta'];
                if (!empty($prev['assinado_em'])) {
                    $metaPrev['_data_override'] = $prev['assinado_em'];
                }
                $this->desenharCarimboAssinatura($pdf, $metaPrev, [
                    'x' => (float) $pos['x'],
                    'y' => (float) $pos['y'],
                    'w' => (float) $pos['w'],
                    'h' => (float) $pos['h'],
                ]);
            }

            // Carimbo da assinatura ATUAL — usa posição customizada quando o sistema externo
            // forneceu (signature_position por role); senão cai no padrão (rodapé última pág).
            if ($position && isset($position['x'], $position['y'], $position['w'], $position['h'])) {
                $pageAlvo = (int) ($position['page'] ?? -1);
                if ($pageAlvo === -1 || $pageAlvo > $totalPaginas) {
                    $pageAlvo = $totalPaginas;
                }
                if ($pageAlvo < 1) $pageAlvo = 1;
                $pdf->setPage($pageAlvo);
                $this->desenharCarimboAssinatura($pdf, $meta, [
                    'x' => (float) $position['x'],
                    'y' => (float) $position['y'],
                    'w' => (float) $position['w'],
                    'h' => (float) $position['h'],
                ]);
            } else {
                $pdf->setPage($totalPaginas);
                $this->desenharCarimboAssinatura($pdf, $meta);
            }

            // Pagina extra de Termo de Assinatura (auditoria completa)
            // Passa TODAS as assinaturas (anteriores + atual) para gerar uma tabela
            // consolidada em vez de uma pagina por signatario.
            $this->adicionarPaginaTermo($pdf, $meta, $razao, $local, $previousStamps);

            $pdf->setSignature(
                signing_cert: $certPem,
                private_key:  $pkeyPem,
                private_key_password: '',
                extracerts:   $extraFile ?: '',
                cert_type:    2,
                info: [
                    'Name'        => $contato,
                    'Location'    => $local,
                    'Reason'      => $razao,
                    'ContactInfo' => $contato,
                ],
                approval:     ''
            );

            $pdf->setSignatureAppearance(170, 280, 35, 12);

            $saidaTmp = tempnam(sys_get_temp_dir(), 'ged_pades_');
            if ($saidaTmp === false) {
                throw new RuntimeException('Não foi possível criar arquivo temporário para o PDF assinado.');
            }
            $pdf->Output($saidaTmp, 'F');

            $bytes = file_get_contents($saidaTmp);
            @unlink($saidaTmp);

            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Falha ao gerar PDF assinado.');
            }

            return $bytes;
        } finally {
            if ($extraFile && is_file($extraFile)) {
                @unlink($extraFile);
            }
        }
    }

    /**
     * Extrai o envelope PKCS#7/CMS embutido no PDF (entre /Contents <...>).
     */
    private function extrairPkcs7(string $pdfBytes): string
    {
        if (! preg_match('/\/Contents\s*<([0-9a-fA-F\s]+)>/', $pdfBytes, $m)) {
            return '';
        }
        $hex = preg_replace('/\s+/', '', $m[1]);
        $bin = hex2bin((string) $hex);
        if ($bin === false) {
            return '';
        }
        // Remove o padding zero (TCPDF preenche o /Contents com zeros até o tamanho reservado)
        return rtrim($bin, "\0");
    }

    /**
     * Reduz o cert PEM a um resumo seguro para serializar como JSON
     * (sem expor o cert completo na resposta).
     */
    private function resumoCert(string $pem): array
    {
        $info = openssl_x509_parse($pem, true);
        if ($info === false) {
            return ['cn' => '?', 'thumbprint' => '?'];
        }
        return [
            'cn'         => $info['subject']['CN'] ?? '',
            'issuer_cn'  => $info['issuer']['CN'] ?? '',
            'serial'     => $info['serialNumberHex'] ?? (string) ($info['serialNumber'] ?? ''),
            'valido_ate' => isset($info['validTo_time_t']) ? date('c', (int) $info['validTo_time_t']) : null,
            'thumbprint' => strtolower((string) openssl_x509_fingerprint($pem, 'sha256')),
        ];
    }

    /**
     * Linha de assinatura discreta — substitui o "nome do responsavel" pela
     * indicacao de assinatura digital com codigo de verificacao.
     *
     * Formato:
     *   Assinado digitalmente por NOME
     *   Codigo: XXXXXXXX   Data: 12/05/2026 09:40
     */
    private function desenharCarimboAssinatura(Fpdi $pdf, array $meta, ?array $rect = null): void
    {
        $cn     = $meta['subject_cn']  ?? '?';
        $cpf    = $meta['subject_cpf'] ?? null;
        $serial = $meta['serial_number'] ?? '';
        $codigo = $this->gerarCodigoAssinatura($serial);
        $timestamp = $meta['_data_override'] ?? date('d/m/Y H:i:s');

        if ($rect) {
            $x = $rect['x'];
            $y = $rect['y'];
            $largura = $rect['w'];
            $altura  = $rect['h'];
        } else {
            $pageHeight = $pdf->getPageHeight();
            $pageWidth  = $pdf->getPageWidth();
            $largura = 90;
            $altura  = 14;
            $x = $pageWidth - $largura - 10;
            $y = $pageHeight - $altura - 10;
        }

        // Linha superior fina (linha de assinatura)
        $pdf->SetDrawColor(69, 181, 187);   // turquesa GPE Docs
        $pdf->SetLineWidth(0.3);
        $pdf->Line($x, $y, $x + $largura, $y);

        // Texto: "Assinado digitalmente por NOME"
        $pdf->SetTextColor(69, 181, 187);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY($x, $y + 1);
        $pdf->Cell($largura, 3, 'Assinado digitalmente por:', 0, 1, 'L');

        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($x, $y + 4);
        $pdf->Cell($largura, 3.5, mb_strimwidth($cn, 0, 50, '...'), 0, 1, 'L');

        // Codigo + data
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetXY($x, $y + 7.5);
        $pdf->Cell($largura, 2.8, 'Codigo: ' . $codigo . '   |   ' . $timestamp, 0, 1, 'L');

        // Texto pequeno de verificacao
        $pdf->SetFont('helvetica', 'I', 5.5);
        $pdf->SetTextColor(140, 140, 140);
        $pdf->SetXY($x, $y + 10.5);
        $pdf->Cell($largura, 2.5, 'Validar em ' . rtrim((string) config('app.url'), '/') . '/validar-assinatura', 0, 1, 'L');
    }

    /**
     * Tarja vertical na margem direita da pagina, estilo 1doc.
     * Texto le-se naturalmente girando a cabeca para a DIREITA (rotacao +90,
     * lendo de cima para baixo). Logo "GPE Docs" no rodape da tarja.
     */
    private function desenharTarjaLateral(Fpdi $pdf, int $totalSignatarios): void
    {
        $pageWidth  = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();

        $appUrl = rtrim((string) config('app.url'), '/');
        $hostShort = preg_replace('#^https?://#', '', $appUrl);
        $plural = $totalSignatarios === 1 ? 'pessoa' : 'pessoas';
        $texto  = sprintf(
            'Assinado digitalmente por %d %s. Para verificar a validade das assinaturas, acesse %s/validar-assinatura',
            $totalSignatarios,
            $plural,
            $hostShort
        );

        // Posicao vertical da tarja: 8mm da margem direita
        $xLinha = $pageWidth - 8;

        // Texto principal — preto puro, lendo de cima para baixo (gire a cabeca pra DIREITA)
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7.5);

        // O texto fica entre y=10 (topo) e y=pageHeight-30 (acima do logo)
        $alturaTexto = $pageHeight - 40; // espaco disponivel
        $centerY = ($pageHeight - 30) / 2 + 5;
        $pdf->StartTransform();
        $pdf->Rotate(90, $xLinha + 2, $centerY); // +90 = horario, le de cima pra baixo
        $pdf->SetXY($xLinha + 2 - ($alturaTexto / 2), $centerY - 1.2);
        $pdf->Cell($alturaTexto, 3, $texto, 0, 0, 'C');
        $pdf->StopTransform();

        // Logo "GPE Docs" no rodape da tarja (estilo "1D" do 1doc)
        $logoX = $xLinha - 1;
        $logoY = $pageHeight - 18;
        $logoW = 9;
        $logoH = 9;

        // Quadrado arredondado turquesa
        $pdf->SetFillColor(69, 181, 187);
        $pdf->RoundedRect($logoX, $logoY, $logoW, $logoH, 1.5, '1111', 'F');

        // Texto "GPE Docs" dentro do quadrado em 2 linhas
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 4.5);
        $pdf->SetXY($logoX, $logoY + 1);
        $pdf->Cell($logoW, 2.5, 'GPE', 0, 1, 'C');
        $pdf->SetTextColor(255, 200, 100); // laranja claro
        $pdf->SetFont('helvetica', 'B', 4.5);
        $pdf->SetXY($logoX, $logoY + 4.5);
        $pdf->Cell($logoW, 2.5, 'Docs', 0, 1, 'C');
    }

    /**
     * Gera um codigo curto (8 chars hex) a partir do serial do certificado.
     * Funciona como "id visivel" da assinatura para referencia rapida.
     */
    private function gerarCodigoAssinatura(string $serial): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $serial));
        if (strlen($hex) < 8) {
            $hex = strtoupper(substr(md5($serial . microtime()), 0, 8));
        }
        return substr($hex, 0, 4) . '-' . substr($hex, 4, 4);
    }

    private function mascararCpf(string $cpf): string
    {
        // Mascara o meio: 851.183.865-04 -> 851.***.***-04
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) !== 11) return $cpf;
        return sprintf('%s.***.***-%s', substr($d, 0, 3), substr($d, 9, 2));
    }

    /**
     * Anexa ao final do PDF uma pagina visivel com os dados da assinatura
     * qualificada (titular, AC, validade, hash, politica). Equivale ao
     * "termo de assinatura" exibido em sistemas como ITI Verificador / SEI.
     */
    private function adicionarPaginaTermo(Fpdi $pdf, array $meta, string $razao, string $local, array $previousStamps = []): void
    {
        $pdf->AddPage('P', 'A4');

        $azul     = [69, 181, 187];  // #45B5BB - turquesa GPE Docs
        $azulClr  = [219, 240, 242]; // tom claro complementar
        $cinzaEsc = [50, 50, 50];
        $cinzaMed = [120, 120, 120];

        // Cabecalho azul
        $pdf->SetFillColor(...$azul);
        $pdf->Rect(0, 0, 210, 24, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(15, 6);
        $pdf->Cell(0, 6, 'TERMO DE ASSINATURA ELETRONICA QUALIFICADA', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetX(15);
        $pdf->Cell(0, 4, 'ICP-Brasil - Lei 14.063/2020 art. 4, III - PAdES-BES', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(165, 6);
        $pdf->Cell(35, 12, 'ICP-BRASIL', 1, 1, 'C');

        // Texto introdutorio
        $pdf->SetTextColor(...$cinzaEsc);
        $pdf->SetY(30);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX(15);
        $pdf->MultiCell(180, 4.2,
            'Este documento foi assinado digitalmente com certificado(s) ICP-Brasil. ' .
            'As assinaturas abaixo sao juridicamente equivalentes a assinaturas manuscritas ' .
            '(Decreto 10.543/2020). A integridade pode ser verificada em qualquer leitor compativel ' .
            '(Adobe Reader, ITI Verificador) ou pela validacao online via QR Code.',
            0, 'J');
        $pdf->Ln(3);

        // Monta lista consolidada: anteriores + atual
        $signatarios = [];
        foreach ($previousStamps as $prev) {
            $pm = $prev['meta'] ?? [];
            $signatarios[] = [
                'cn'     => $pm['subject_cn']    ?? '?',
                'cpf'    => $pm['subject_cpf']   ?? null,
                'serial' => $pm['serial_number'] ?? '?',
                'data'   => $prev['assinado_em'] ?? date('d/m/Y H:i:s'),
                'issuer' => $pm['issuer_cn']     ?? '',
            ];
        }
        $signatarios[] = [
            'cn'     => $meta['subject_cn']    ?? '?',
            'cpf'    => $meta['subject_cpf']   ?? null,
            'serial' => $meta['serial_number'] ?? '?',
            'data'   => date('d/m/Y H:i:s'),
            'issuer' => $meta['issuer_cn']     ?? '',
        ];

        // Tabela compacta de signatarios
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(...$azul);
        $pdf->SetX(15);
        $pdf->Cell(0, 6, 'Signatarios (' . count($signatarios) . ')', 0, 1);

        // Cabecalho da tabela
        $pdf->SetFillColor(...$azul);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetX(15);
        $pdf->Cell(8,   6, '#',                 0, 0, 'C', true);
        $pdf->Cell(72,  6, 'Titular',           0, 0, 'L', true);
        $pdf->Cell(28,  6, 'CPF',               0, 0, 'L', true);
        $pdf->Cell(40,  6, 'AC Emissora',       0, 0, 'L', true);
        $pdf->Cell(32,  6, 'Data/Hora',         0, 1, 'L', true);

        $pdf->SetTextColor(...$cinzaEsc);
        $pdf->SetFont('helvetica', '', 8);

        foreach ($signatarios as $i => $s) {
            $cpfFmt = $s['cpf'] ? $this->mascararCpf($this->formatarCpf($s['cpf'])) : '-';
            $acCurta = $this->resumirIssuer($s['issuer']);
            $fill = ($i % 2 === 0);
            if ($fill) {
                $pdf->SetFillColor(...$azulClr);
            }
            $pdf->SetX(15);
            $pdf->Cell(8,  5.5, (string) ($i + 1),         'B', 0, 'C', $fill);
            $pdf->Cell(72, 5.5, $this->truncar($s['cn'], 45), 'B', 0, 'L', $fill);
            $pdf->Cell(28, 5.5, $cpfFmt,                   'B', 0, 'L', $fill);
            $pdf->Cell(40, 5.5, $this->truncar($acCurta, 26), 'B', 0, 'L', $fill);
            $pdf->Cell(32, 5.5, $s['data'],                'B', 1, 'L', $fill);
        }

        $pdf->Ln(4);

        // Caixa com dados gerais da assinatura
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(...$azul);
        $pdf->SetX(15);
        $pdf->Cell(0, 6, 'Politica de Assinatura', 0, 1);
        $pdf->SetTextColor(...$cinzaEsc);

        $politica  = self::POLITICA_NOME . ' (OID ' . self::POLITICA_OID . ')';
        $algoritmo = 'SHA-256 com RSA';

        $linhas = [
            ['Razao',     $razao],
            ['Local',     $local],
            ['Politica',  $politica],
            ['Algoritmo', $algoritmo],
        ];
        foreach ($linhas as [$label, $valor]) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(...$cinzaMed);
            $pdf->SetX(15);
            $pdf->Cell(35, 4.5, mb_strtoupper($label), 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(...$cinzaEsc);
            $pdf->MultiCell(145, 4.5, (string) $valor, 0, 'L');
        }

        $pdf->Ln(3);

        // QR Code para validacao online (canto inferior direito)
        $appUrl   = rtrim((string) config('app.url'), '/');
        $hashDoc  = $meta['hash_documento'] ?? null;
        $urlValid = $appUrl . '/validar-assinatura' . ($hashDoc ? '?hash=' . substr($hashDoc, 0, 16) : '');

        $qrY = 220;
        $pdf->write2DBarcode($urlValid, 'QRCODE,M', 150, $qrY, 45, 45, [
            'border'        => false,
            'padding'       => 0,
            'fgcolor'       => [0, 0, 0],
            'bgcolor'       => false,
        ], 'N');

        // Texto ao lado do QR
        $pdf->SetXY(15, $qrY);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(...$azul);
        $pdf->Cell(0, 5, 'Validacao Online', 0, 1);

        $pdf->SetX(15);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...$cinzaEsc);
        $pdf->MultiCell(130, 4.2,
            "Aponte a camera do celular para o QR Code ao lado para acessar:\n" .
            "- Detalhamento completo de cada certificado\n" .
            "- Verificacao em tempo real da integridade\n" .
            "- Cadeia de certificacao ICP-Brasil\n" .
            "- Status de revogacao\n\n" .
            "Ou acesse: " . $urlValid,
            0, 'L');

        // Rodape
        $pdf->SetY(275);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(...$cinzaMed);
        $pdf->SetX(15);
        $pdf->Cell(0, 3.5, 'GPE Docs - Plataforma Digital Integrada - Conceito Gestao Publica', 0, 1, 'C');
        $pdf->SetX(15);
        $pdf->Cell(0, 3.5, 'Documento assinado conforme Lei 14.063/2020 e Decreto 10.543/2020.', 0, 1, 'C');
    }

    /**
     * Reduz o nome da AC ao essencial. Ex.:
     *   "AC SyngularID Multipla v5" -> "AC SyngularID Multipla"
     */
    private function resumirIssuer(string $issuer): string
    {
        $issuer = preg_replace('/\s+v\d+(\.\d+)*\s*$/i', '', (string) $issuer);
        return trim($issuer);
    }

    private function truncar(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }

    private function formatarCpf(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        if (strlen($d) !== 11) return $cpf;
        return sprintf('%s.%s.%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9, 2));
    }

    private function formatarSerial(string $hex): string
    {
        $h = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $hex));
        return implode(':', str_split($h, 2));
    }

    private function formatarThumbprint(string $hex): string
    {
        return strtoupper(implode(':', str_split($hex, 2)));
    }
}
