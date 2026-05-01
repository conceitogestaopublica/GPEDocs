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

            // Carimbo visual NA ultima pagina do documento original (rodape)
            $pdf->setPage($totalPaginas);
            $this->desenharCarimboAssinatura($pdf, $meta);

            // Pagina extra de Termo de Assinatura (auditoria completa)
            $this->adicionarPaginaTermo($pdf, $meta, $razao, $local);

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
     * Desenha um pequeno carimbo na pagina atual com os dados essenciais da
     * assinatura — equivalente ao "Documento assinado digitalmente" que
     * sistemas como FlowDocs / SEI / GPE Cloud aplicam visualmente. Vai no
     * canto inferior direito da pagina para nao poluir o conteudo.
     */
    private function desenharCarimboAssinatura(Fpdi $pdf, array $meta): void
    {
        $cn  = $meta['subject_cn']  ?? '?';
        $cpf = $meta['subject_cpf'] ?? null;
        $cpfFmt = $cpf ? $this->formatarCpf($cpf) : '';
        $serial = isset($meta['serial_number']) ? substr($meta['serial_number'], 0, 16) . '...' : '';
        $timestamp = date('d/m/Y H:i:s');

        // Posicao no rodape (mm) — ajusta para nao bater com conteudo
        $pageHeight = $pdf->getPageHeight();
        $pageWidth  = $pdf->getPageWidth();
        $largura = 75;
        $altura  = 22;
        $x = $pageWidth - $largura - 10;
        $y = $pageHeight - $altura - 10;

        // Caixa
        $pdf->SetDrawColor(30, 64, 175);    // azul ICP-Brasil
        $pdf->SetLineWidth(0.3);
        $pdf->SetFillColor(245, 247, 252);  // azul muito claro
        $pdf->Rect($x, $y, $largura, $altura, 'DF');

        // Faixa lateral azul escura
        $pdf->SetFillColor(30, 64, 175);
        $pdf->Rect($x, $y, 4, $altura, 'F');

        // Selo "ICP" vertical na faixa
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->StartTransform();
        $pdf->Rotate(90, $x + 2, $y + $altura / 2);
        $pdf->SetXY($x - $altura / 2 + 2, $y + $altura / 2 - 1.5);
        $pdf->Cell($altura, 3, 'ICP-BRASIL', 0, 0, 'C');
        $pdf->StopTransform();

        // Conteudo
        $pdf->SetTextColor(30, 64, 175);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY($x + 6, $y + 2);
        $pdf->Cell($largura - 8, 3, 'Documento assinado digitalmente', 0, 1, 'L');

        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetXY($x + 6, $y + 6);
        $pdf->Cell($largura - 8, 3.5, mb_strimwidth($cn, 0, 38, '...'), 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(80, 80, 80);
        if ($cpfFmt) {
            $pdf->SetXY($x + 6, $y + 9.5);
            $pdf->Cell($largura - 8, 2.8, 'CPF: ' . $this->mascararCpf($cpfFmt), 0, 1, 'L');
        }
        $pdf->SetXY($x + 6, $y + 12.5);
        $pdf->Cell($largura - 8, 2.8, 'Data: ' . $timestamp, 0, 1, 'L');

        if ($serial) {
            $pdf->SetXY($x + 6, $y + 15.5);
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell($largura - 8, 2.5, 'Serial: ' . $serial, 0, 1, 'L');
        }

        $pdf->SetXY($x + 6, $y + 18);
        $pdf->SetFont('helvetica', 'I', 5.5);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell($largura - 8, 2.5, 'Verifique em /validar-assinatura', 0, 1, 'L');
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
    private function adicionarPaginaTermo(Fpdi $pdf, array $meta, string $razao, string $local): void
    {
        $pdf->AddPage('P', 'A4');

        $azul     = [30, 64, 175];   // #1e40af
        $cinzaEsc = [50, 50, 50];
        $cinzaMed = [120, 120, 120];

        // Cabecalho azul
        $pdf->SetFillColor(...$azul);
        $pdf->Rect(0, 0, 210, 28, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(15, 8);
        $pdf->Cell(0, 6, 'TERMO DE ASSINATURA ELETRONICA QUALIFICADA', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX(15);
        $pdf->Cell(0, 5, 'ICP-Brasil  -  Lei 14.063/2020 art. 4, III  -  PAdES-BES', 0, 1, 'L');

        // Selo a direita
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(160, 8);
        $pdf->Cell(35, 12, 'ICP-BRASIL', 1, 1, 'C');

        // Conteudo
        $pdf->SetTextColor(...$cinzaEsc);
        $pdf->SetY(38);
        $pdf->SetFont('helvetica', '', 10);

        $pdf->SetX(15);
        $pdf->MultiCell(180, 5,
            'Este documento foi assinado digitalmente com certificado ICP-Brasil. ' .
            'A assinatura abaixo e juridicamente equivalente a uma assinatura manuscrita ' .
            'em qualquer interacao com o poder publico brasileiro (Decreto 10.543/2020). ' .
            'Para verificar a integridade da assinatura, abra o PDF em qualquer leitor compativel ' .
            '(Adobe Reader, ITI Verificador) ou utilize o validador online do sistema.',
            0, 'J');
        $pdf->Ln(4);

        // Caixa com dados do signatario
        $cn         = $meta['subject_cn']       ?? '?';
        $cpf        = $meta['subject_cpf']      ?? null;
        $issuer     = $meta['issuer_cn']        ?? '?';
        $serial     = $meta['serial_number']    ?? '?';
        $validoDe   = $meta['valido_de']        ?? null;
        $validoAte  = $meta['valido_ate']       ?? null;
        $thumb      = $meta['thumbprint_sha256']?? '?';
        $politica   = self::POLITICA_NOME . ' (OID ' . self::POLITICA_OID . ')';
        $algoritmo  = 'SHA-256 com RSA';
        $timestamp  = date('d/m/Y H:i:s');

        $cpfFmt = $cpf ? $this->formatarCpf($cpf) : null;

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...$azul);
        $pdf->SetX(15);
        $pdf->Cell(0, 6, 'Dados do Signatario', 0, 1);
        $pdf->SetTextColor(...$cinzaEsc);

        $linhas = [
            ['Titular do certificado', $cn],
        ];
        if ($cpfFmt) {
            $linhas[] = ['CPF', $cpfFmt];
        }
        $linhas = array_merge($linhas, [
            ['Autoridade Certificadora', $issuer],
            ['Numero de serie', $this->formatarSerial($serial)],
            ['Validade do certificado',
                ($validoDe ? date('d/m/Y', strtotime($validoDe)) : '?') . ' ate ' .
                ($validoAte ? date('d/m/Y', strtotime($validoAte)) : '?')],
            ['Thumbprint SHA-256', $this->formatarThumbprint($thumb)],
        ]);

        foreach ($linhas as [$label, $valor]) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(...$cinzaMed);
            $pdf->SetX(15);
            $pdf->Cell(50, 5, mb_strtoupper($label), 0, 0);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(...$cinzaEsc);
            $pdf->MultiCell(130, 5, (string) $valor, 0, 'L');
            $pdf->Ln(0.5);
        }

        $pdf->Ln(4);

        // Caixa com dados da assinatura
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...$azul);
        $pdf->SetX(15);
        $pdf->Cell(0, 6, 'Dados da Assinatura', 0, 1);
        $pdf->SetTextColor(...$cinzaEsc);

        $linhas2 = [
            ['Razao',           $razao],
            ['Local',           $local],
            ['Politica',        $politica],
            ['Algoritmo',       $algoritmo],
            ['Carimbo de tempo', $timestamp],
        ];
        foreach ($linhas2 as [$label, $valor]) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(...$cinzaMed);
            $pdf->SetX(15);
            $pdf->Cell(50, 5, mb_strtoupper($label), 0, 0);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(...$cinzaEsc);
            $pdf->MultiCell(130, 5, (string) $valor, 0, 'L');
            $pdf->Ln(0.5);
        }

        // Rodape
        $pdf->SetY(275);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...$cinzaMed);
        $pdf->SetX(15);
        $pdf->Cell(0, 4, 'GPE Docs - Plataforma Digital Integrada - Conceito Gestao Publica', 0, 1, 'C');
        $pdf->SetX(15);
        $pdf->Cell(0, 4, 'A integridade desta assinatura pode ser verificada em /validar-assinatura ou em qualquer leitor PDF compativel.', 0, 1, 'C');
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
