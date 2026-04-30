<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpseclib3\File\X509;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Assinatura PAdES com chave em token (A3) — fluxo two-step usando Web PKI da Lacuna.
 *
 * O usuario nunca expoe a chave privada; ela vive no token/smartcard. O fluxo:
 *
 *  1. preparar(): gera o PDF com placeholder /Contents (zeros) usando uma
 *     chave dummy descartavel apenas para que TCPDF/FPDI montem a estrutura
 *     PDF correta (/AcroForm, /ByteRange, dicionario /Sig). Calcula o digest
 *     do conteudo coberto, monta SignedAttributes ASN.1 (RFC 5652 + 5035) e
 *     devolve o hash a ser assinado pelo token.
 *
 *  2. finalizar(): recebe os bytes RSA assinados pelo token, monta SignerInfo
 *     com o cert do usuario + signedAttrs em cache + a assinatura externa,
 *     embute em SignedData/ContentInfo e substitui o placeholder do PDF pelo
 *     PKCS#7 final.
 */
class AssinaturaIcpA3Service
{
    // OIDs CMS / PKCS#7
    private const OID_SIGNED_DATA       = '1.2.840.113549.1.7.2';
    private const OID_DATA              = '1.2.840.113549.1.7.1';
    private const OID_RSA_ENCRYPTION    = '1.2.840.113549.1.1.1';
    private const OID_SHA256            = '2.16.840.1.101.3.4.2.1';
    private const OID_RSA_SHA256        = '1.2.840.113549.1.1.11';

    // Atributos assinados
    private const OID_CONTENT_TYPE      = '1.2.840.113549.1.9.3';
    private const OID_MESSAGE_DIGEST    = '1.2.840.113549.1.9.4';
    private const OID_SIGNING_TIME      = '1.2.840.113549.1.9.5';
    private const OID_SIGNING_CERT_V2   = '1.2.840.113549.1.9.16.2.47';

    // Politica AD-RB v2
    private const POLITICA_OID  = '2.16.76.1.7.1.1.2.3';
    private const POLITICA_NOME = 'AD-RB v2 (Assinatura Digital de Referência Básica)';

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

        // 2) Calcula digest SHA-256 dos bytes cobertos pelo /ByteRange.
        $coberto = substr($pdfPlaceholder, $byteRange[0], $byteRange[1])
                 . substr($pdfPlaceholder, $byteRange[2], $byteRange[3]);
        $contentHash = hash('sha256', $coberto, true);

        // 3) Monta SignedAttributes ASN.1 e calcula hash_a_assinar.
        $signingTime = time();
        $signedAttrsDer = $this->montarSignedAttributes(
            certPem: $certPem,
            contentHash: $contentHash,
            signingTime: $signingTime,
        );

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
            'signing_time'     => $signingTime,
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

        // Monta SignerInfo + SignedData + ContentInfo
        $pkcs7 = $this->montarPkcs7(
            certPem: $certPem,
            cadeia: $cadeiaCertsPem,
            signedAttrsDer: $signedAttrsDer,
            assinatura: $assinaturaBin,
        );

        // Substitui o /Contents do placeholder pelo PKCS#7 final.
        $pdfFinal = $this->substituirContents($pdfPlaceholder, $byteRange, $pkcs7);

        // Persiste
        $disk = Storage::disk('local');
        $thumbprint = openssl_x509_fingerprint($certPem, 'sha256');
        $caminho = sprintf(
            'assinaturas/icp/%s_a3_%s.pdf',
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
                'politica_oid'  => self::POLITICA_OID,
                'politica_nome' => self::POLITICA_NOME,
                'algoritmo'     => 'SHA-256',
                'content_hash'  => $contentHashHex,
            ],
        ];
    }

    // ------------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------------

    /**
     * Gera o PDF com placeholder de assinatura. Usa um par chave/cert dummy
     * apenas para que TCPDF emita a estrutura PDF correta — todo o conteudo
     * dentro do /Contents sera substituido na finalizar().
     *
     * @return array{0: string, 1: int[]}  [pdfBytes, byteRange]
     */
    private function gerarPdfComPlaceholder(string $pdfOrigem, array $info): array
    {
        // Gera/carrega par chave/cert dummy. As funcoes openssl_pkey_new etc.
        // exigem openssl.cnf instalado no path do PHP (frequente fonte de
        // problema no Windows), entao caimos no openssl CLI que e mais
        // tolerante. Cacheamos o par para reuso entre requests.
        [$dummyCertPem, $dummyKeyPem] = $this->materialDummy();

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
            signing_cert: $dummyCertPem,
            private_key:  $dummyKeyPem,
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

        if (! preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $bytes, $m)) {
            throw new RuntimeException('PDF gerado sem /ByteRange.');
        }
        $byteRange = [(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]];

        return [$bytes, $byteRange];
    }

    /**
     * SignedAttributes DER (com tag SET, pronto para hash).
     */
    private function montarSignedAttributes(string $certPem, string $contentHash, int $signingTime): string
    {
        // Attribute 1: contentType = id-data
        $attrContentType = AsnDer::seq(
            AsnDer::oid(self::OID_CONTENT_TYPE),
            AsnDer::set(AsnDer::oid(self::OID_DATA))
        );

        // Attribute 2: messageDigest
        $attrMessageDigest = AsnDer::seq(
            AsnDer::oid(self::OID_MESSAGE_DIGEST),
            AsnDer::set(AsnDer::octet($contentHash))
        );

        // Attribute 3: signingTime
        $attrSigningTime = AsnDer::seq(
            AsnDer::oid(self::OID_SIGNING_TIME),
            AsnDer::set(AsnDer::utcTime($signingTime))
        );

        // Attribute 4: signing-certificate-v2 (RFC 5035)
        // SigningCertificateV2 ::= SEQUENCE { certs SEQUENCE OF ESSCertIDv2, ... }
        // ESSCertIDv2 ::= SEQUENCE { certHash OCTET STRING }
        // Aqui usamos SHA-256 (padrao) — algoritmo opcional pode ser omitido.
        $certHash = (string) hex2bin((string) openssl_x509_fingerprint($certPem, 'sha256'));
        $essCertIdV2 = AsnDer::seq(AsnDer::octet($certHash));
        $signingCertV2 = AsnDer::seq(AsnDer::seq($essCertIdV2));
        $attrSigningCertV2 = AsnDer::seq(
            AsnDer::oid(self::OID_SIGNING_CERT_V2),
            AsnDer::set($signingCertV2)
        );

        // SignedAttributes (SET OF Attribute) — ordenacao por DER lexical do encoded
        // (DER exige ordem); para nosso conjunto pequeno e deterministico, ordena.
        $attrs = [$attrContentType, $attrMessageDigest, $attrSigningTime, $attrSigningCertV2];
        usort($attrs, fn ($a, $b) => strcmp($a, $b));

        return AsnDer::set(...$attrs);
    }

    /**
     * Constroi o ContentInfo / SignedData / SignerInfo com a assinatura externa.
     */
    private function montarPkcs7(
        string $certPem,
        array $cadeia,
        string $signedAttrsDer,
        string $assinatura,
    ): string {
        // SignerIdentifier: IssuerAndSerialNumber a partir do cert do usuario
        $x509 = new X509();
        if (! $x509->loadX509($certPem)) {
            throw new RuntimeException('Não foi possível carregar o certificado do signatário.');
        }
        $issuerDer = $x509->getIssuerDN(X509::DN_ASN1);
        if (! is_string($issuerDer)) {
            throw new RuntimeException('Issuer DN não pôde ser extraído como DER.');
        }
        $serialBig = $x509->getCurrentCert()['tbsCertificate']['serialNumber'];
        /** @var \phpseclib3\Math\BigInteger $serialBig */
        $serialDer = AsnDer::intFromBytes($serialBig->toBytes());

        $issuerAndSerial = AsnDer::seq($issuerDer, $serialDer);

        // SignedAttributes com tag IMPLICIT [0] (substitui 0x31 → 0xA0)
        $signedAttrsImplicit = AsnDer::reTag($signedAttrsDer, 0xA0);

        // DigestAlgorithm SHA-256
        $digestAlgo = AsnDer::seq(AsnDer::oid(self::OID_SHA256), AsnDer::null());

        // SignatureAlgorithm rsaEncryption (RFC 8017 — comum em PAdES-BES)
        $sigAlgo = AsnDer::seq(AsnDer::oid(self::OID_RSA_ENCRYPTION), AsnDer::null());

        $signerInfo = AsnDer::seq(
            AsnDer::intSmall(1),               // version
            $issuerAndSerial,                  // sid
            $digestAlgo,                       // digestAlgorithm
            $signedAttrsImplicit,              // signedAttrs [0]
            $sigAlgo,                          // signatureAlgorithm
            AsnDer::octet($assinatura),        // signature
        );

        // CertificateSet [0] IMPLICIT — concatena cert do signatario + cadeia
        $certsDer = $this->pemParaCertDer($certPem);
        foreach ($cadeia as $pem) {
            $certsDer .= $this->pemParaCertDer($pem);
        }
        $certificatesField = AsnDer::reTag(AsnDer::seq($certsDer), 0xA0);

        // EncapsulatedContentInfo (detached): so o eContentType
        $encapInfo = AsnDer::seq(AsnDer::oid(self::OID_DATA));

        $signedData = AsnDer::seq(
            AsnDer::intSmall(1),               // version
            AsnDer::set($digestAlgo),          // digestAlgorithms
            $encapInfo,                        // encapContentInfo
            $certificatesField,                // certificates [0]
            AsnDer::set($signerInfo),          // signerInfos
        );

        // ContentInfo
        return AsnDer::seq(
            AsnDer::oid(self::OID_SIGNED_DATA),
            AsnDer::ctxExplicit(0, $signedData),
        );
    }

    /**
     * Gera um par chave/cert dummy via openssl CLI e cacheia. So serve para
     * o TCPDF montar a estrutura do /Sig — todo conteudo desse cert dummy e
     * substituido na finalizar().
     *
     * @return array{0: string, 1: string}  [certPem, keyPem]
     */
    private function materialDummy(): array
    {
        $cacheDir = storage_path('app/private/icp-brasil-internal');
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }
        $certFile = $cacheDir . DIRECTORY_SEPARATOR . 'placeholder.crt';
        $keyFile  = $cacheDir . DIRECTORY_SEPARATOR . 'placeholder.key';

        if (! is_file($certFile) || ! is_file($keyFile)) {
            $cmd = sprintf(
                'openssl req -x509 -newkey rsa:2048 -nodes -days 36500 -subj %s -keyout %s -out %s 2>&1',
                escapeshellarg('/CN=GED-PAdES-Placeholder/O=GED Internal'),
                escapeshellarg($keyFile),
                escapeshellarg($certFile),
            );
            shell_exec($cmd);

            if (! is_file($certFile) || ! is_file($keyFile)) {
                throw new RuntimeException('Falha ao gerar par dummy via openssl CLI.');
            }
        }

        return [
            (string) file_get_contents($certFile),
            (string) file_get_contents($keyFile),
        ];
    }

    /**
     * Extrai o DER bruto de um cert PEM (tudo entre BEGIN/END CERT decodificado base64).
     */
    private function pemParaCertDer(string $pem): string
    {
        if (! preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $pem, $m)) {
            throw new RuntimeException('PEM de certificado inválido.');
        }
        $der = base64_decode((string) preg_replace('/\s+/', '', $m[1]), true);
        if ($der === false) {
            throw new RuntimeException('PEM de certificado com base64 inválido.');
        }
        return $der;
    }

    /**
     * Substitui o conteudo do placeholder /Contents pelo PKCS#7 final.
     * O byte_range nao muda — apenas os bytes dentro do "buraco" sao reescritos.
     */
    private function substituirContents(string $pdfPlaceholder, array $byteRange, string $pkcs7Bin): string
    {
        $gapStart = $byteRange[1];
        $gapEnd   = $byteRange[2];
        $tamanhoGap = $gapEnd - $gapStart;

        // O gap inclui os delimitadores < e > do hex. Vamos reabrir a regiao
        // para colocar < + hex + > preenchido com zeros ate o tamanho original.
        $hex = bin2hex($pkcs7Bin);

        // Calcula o tamanho que o conteudo hex pode ocupar.
        // Estrutura no gap: ws + <hex...> + ws  (tipicamente "<" + hex + ">")
        // Recortamos a regiao original para manter os mesmos offsets.
        $regiaoOriginal = substr($pdfPlaceholder, $gapStart, $tamanhoGap);

        if (! preg_match('/^(\s*<)([0-9a-fA-F0\s]+)(>)/', $regiaoOriginal, $m, PREG_OFFSET_CAPTURE)) {
            throw new RuntimeException('Placeholder /Contents não encontrado na região do byte range.');
        }
        $hexOriginal = $m[2][0];

        $espacoHex = strlen($hexOriginal);
        if (strlen($hex) > $espacoHex) {
            throw new RuntimeException(sprintf(
                'PKCS#7 (%d bytes hex) maior que o placeholder reservado (%d bytes hex). Aumente a reserva no TCPDF.',
                strlen($hex),
                $espacoHex,
            ));
        }
        // Preenche com '0' ate o tamanho original
        $hexPad = str_pad($hex, $espacoHex, '0', STR_PAD_RIGHT);

        // Reconstroi a regiao mantendo qualquer whitespace antes/depois.
        // Usar ${1}/${3} (e nao $1) porque digitos hex apos $1 fariam o PHP
        // interpretar como capture group multi-digito inexistente.
        $regiaoNova = (string) preg_replace(
            '/^(\s*<)[0-9a-fA-F0\s]+(>)/',
            '${1}' . $hexPad . '${2}',
            $regiaoOriginal,
            1,
        );

        // Atualiza o PDF: bytes antes do gap + nova regiao + bytes apos o gap
        return substr($pdfPlaceholder, 0, $gapStart)
             . $regiaoNova
             . substr($pdfPlaceholder, $gapStart + $tamanhoGap);
    }
}
