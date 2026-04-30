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
        );

        // Persiste o resultado
        $disk = Storage::disk('local');
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
            'assinatura_pkcs7'        => $resultado['pkcs7'],
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
}
