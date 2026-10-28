<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\FpdiAssinatura;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Assinatura PAdES com chave em token (A3) — fluxo two-step usando Web PKI da Lacuna.
 *
 * O usuario nunca expoe a chave privada; ela vive no token/smartcard. O fluxo:
 *
 *  1. preparar(): gera o PDF com o espaço da assinatura reservado
 *     (PadesPlaceholder), calcula o digest do conteudo coberto, monta os
 *     atributos assinados da politica PA_PAdES_AD_RB (CmsAdRb) e devolve o
 *     hash a ser assinado pelo token.
 *
 *  2. finalizar(): confere os bytes RSA devolvidos pelo token, monta o CMS
 *     (CmsAdRb::envelope) e grava no espaço reservado do PDF.
 */
class AssinaturaIcpA3Service
{
    public function __construct(
        private readonly CertificadoService $certificadoService,
    ) {
    }

    /**
     * 1ª etapa: monta o PDF com placeholder e devolve o hash a assinar.
     *
     * @param  string  $pdfOrigem  caminho absoluto do PDF a assinar
     * @param  string  $certPem    cert publico do signatario (vindo do Web PKI)
     * @param  array   $info       ['razao' => string, 'local' => string, 'contato' => string]
     * @return array{
     *   sessao_id: string,
     *   hash_a_assinar: string,        // SHA-256 do DER de SignedAttributes (base64)
     *   algoritmo_digest: string,
     *   tamanho_placeholder: int,
     * }
     */
    public function preparar(string $pdfOrigem, string $certPem, array $info = []): array
    {
        if (! is_file($pdfOrigem)) {
            throw new RuntimeException('Arquivo PDF não encontrado: ' . $pdfOrigem);
        }

        // 1) Gera PDF com placeholder usando key/cert dummy descartaveis. So
        //    queremos a estrutura: /AcroForm, /Sig, /ByteRange, /Contents zeros.
        [$pdfPlaceholder, $byteRange] = $this->gerarPdfComPlaceholder($pdfOrigem, $info);

        // 2) Digest SHA-256 dos bytes cobertos pelo /ByteRange.
        $contentHash = PadesPlaceholder::digest($pdfPlaceholder, $byteRange);

        // 3) Atributos assinados da política AD-RB e hash_a_assinar.
        $signedAttrsDer = CmsAdRb::atributosAssinados($certPem, $contentHash);

        // RFC 5652 §5.4: o que se assina e o DER do SET de SignedAttributes.
        // Aqui ja codificamos com tag SET (0x31) — o DER sai pronto para hash.
        $hashAAssinar = hash('sha256', $signedAttrsDer, true);

        // 4) Cacheia o estado para a finalizar() — TTL curto (10 min).
        $sessaoId = (string) Str::uuid();
        Cache::put('icp_a3_sessao:' . $sessaoId, [
            'pdf_placeholder'  => base64_encode($pdfPlaceholder),
            'byte_range'       => $byteRange,
            'cert_pem'         => $certPem,
            'signed_attrs_der' => base64_encode($signedAttrsDer),
            'content_hash'     => bin2hex($contentHash),
            'info'             => $info,
        ], now()->addMinutes(10));

        return [
            'sessao_id'           => $sessaoId,
            'hash_a_assinar'      => base64_encode($hashAAssinar),
            'algoritmo_digest'    => 'SHA-256',
            'tamanho_placeholder' => $byteRange[2] - $byteRange[1],
        ];
    }

    /**
     * 2ª etapa: substitui o placeholder pelo PKCS#7 montado com a assinatura
     * externa do token.
     *
     * @param  string  $sessaoId
     * @param  string  $assinaturaB64  bytes RSA-SHA256 assinados pelo token, em base64
     * @param  array   $cadeiaCertsPem  certificados intermediarios opcionais (PEM)
     * @return array{ caminho: string, pkcs7: string, pdf_sha256: string, meta: array }
     */
    /** RSA PKCS#1 v1.5 com SHA-256 sobre o DER dos SignedAttributes (o que o token assinou). */
    public static function assinaturaConfere(string $signedAttrsDer, string $assinatura, string $certPem): bool
    {
        $chavePublica = openssl_pkey_get_public($certPem);
        if ($chavePublica === false) {
            return false;
        }

        return openssl_verify($signedAttrsDer, $assinatura, $chavePublica, OPENSSL_ALGO_SHA256) === 1;
    }

    public function finalizar(
        string $sessaoId,
        string $assinaturaB64,
        array $cadeiaCertsPem = [],
    ): array {
        $estado = Cache::get('icp_a3_sessao:' . $sessaoId);
        if (! $estado) {
            throw new RuntimeException('Sessão de assinatura expirada ou inválida.');
        }

        $pdfPlaceholder = base64_decode($estado['pdf_placeholder']);
        $byteRange      = $estado['byte_range'];
        $certPem        = $estado['cert_pem'];
        $signedAttrsDer = base64_decode($estado['signed_attrs_der']);
        $contentHashHex = $estado['content_hash'];

        $assinaturaBin = base64_decode($assinaturaB64, true);
        if ($assinaturaBin === false || strlen($assinaturaBin) < 64) {
            throw new RuntimeException('Bytes de assinatura inválidos.');
        }

        // Antes os bytes devolvidos pelo navegador eram embutidos sem conferência: qualquer
        // valor virava "assinado". Confere que é a assinatura RSA/SHA-256 dos atributos
        // assinados, feita com a chave do certificado apresentado no preparo.
        if (! self::assinaturaConfere($signedAttrsDer, $assinaturaBin, $certPem)) {
            throw new RuntimeException('A assinatura devolvida pelo token não corresponde ao certificado apresentado.');
        }

        $pkcs7 = CmsAdRb::envelope($certPem, $cadeiaCertsPem, $signedAttrsDer, $assinaturaBin);
        $pdfFinal = PadesPlaceholder::embutir($pdfPlaceholder, $byteRange, $pkcs7);

        // Persiste
        $disk = Storage::disk('documentos');
        $thumbprint = openssl_x509_fingerprint($certPem, 'sha256');
        $caminho = sprintf(
            \App\Tenant\TenantStorage::pasta('assinaturas/icp') . '/%s_a3_%s.pdf',
            date('Ymd_His'),
            substr((string) $thumbprint, 0, 12),
        );
        $disk->put($caminho, $pdfFinal);

        Cache::forget('icp_a3_sessao:' . $sessaoId);

        $meta = $this->certificadoService->lerMetadados($certPem);

        return [
            'caminho'    => $caminho,
            'pkcs7'      => $pkcs7,
            'pdf_sha256' => hash('sha256', $pdfFinal),
            'meta'       => $meta + [
                'politica_oid'  => CmsAdRb::politica()['oid'],
                'politica_nome' => CmsAdRb::politica()['nome'],
                'algoritmo'     => 'SHA-256',
                'content_hash'  => $contentHashHex,
            ],
        ];
    }

    // ------------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------------


    /**
     * PDF com o espaço da assinatura reservado. O TCPDF assina com um par
     * descartável só para montar /Sig, /ByteRange e /Contents — o conteúdo
     * do /Contents é substituído na finalizar().
     *
     * @return array{0: string, 1: int[]}  [pdfBytes, byteRange]
     */
    private function gerarPdfComPlaceholder(string $pdfOrigem, array $info): array
    {
        [$certDescartavel, $chaveDescartavel] = PadesPlaceholder::materialDescartavel();

        $pdf = new FpdiAssinatura('P', 'mm', 'A4', true, 'UTF-8', false);
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
            signing_cert: $certDescartavel,
            private_key:  $chaveDescartavel,
            private_key_password: '',
            extracerts:   '',
            cert_type:    2,
            info: [
                'Name'        => $info['contato'] ?? '',
                'Location'    => $info['local']   ?? 'Brasil',
                'Reason'      => $info['razao']   ?? 'Assinatura Eletrônica Qualificada (Lei 14.063/2020)',
                'ContactInfo' => $info['contato'] ?? '',
            ],
            approval: ''
        );
        $pdf->setSignatureAppearance(170, 280, 35, 12);

        $tmp = tempnam(sys_get_temp_dir(), 'a3_ph_');
        if ($tmp === false) {
            throw new RuntimeException('Não foi possível alocar arquivo temporário.');
        }
        $pdf->Output($tmp, 'F');
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return [$bytes, PadesPlaceholder::byteRange($bytes)];
    }
}
