<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Envelope CMS (SignedData detached) da assinatura PAdES no padrão da
 * política PA_PAdES_AD_RB do ITI (DOC-ICP-15.03), usado pelo A1 e pelo A3.
 *
 * A política exige como atributos assinados exatamente:
 *   contentType, messageDigest, signingCertificateV2 e sigPolicyId
 * (referência só ao certificado do signatário). O signing-time fica fora:
 * no PAdES a hora da assinatura vai no /M do dicionário do PDF.
 *
 * Fluxo: atributosAssinados() → assinatura RSA-SHA256 do DER retornado (pela
 * chave do PFX ou pelo token) → envelope().
 */
final class CmsAdRb
{
    private const OID_SIGNED_DATA     = '1.2.840.113549.1.7.2';
    private const OID_DATA            = '1.2.840.113549.1.7.1';
    private const OID_RSA_ENCRYPTION  = '1.2.840.113549.1.1.1';
    private const OID_SHA256          = '2.16.840.1.101.3.4.2.1';

    private const OID_CONTENT_TYPE    = '1.2.840.113549.1.9.3';
    private const OID_MESSAGE_DIGEST  = '1.2.840.113549.1.9.4';
    private const OID_SIGNING_CERT_V2 = '1.2.840.113549.1.9.16.2.47';
    private const OID_SIG_POLICY_ID   = '1.2.840.113549.1.9.16.2.15';

    /** @return array{oid: string, hash: string, nome: string, url: string} */
    public static function politica(): array
    {
        $p = (array) config('icp_brasil.politica_pades');
        if (empty($p['oid']) || empty($p['hash'])) {
            throw new RuntimeException('Política de assinatura não configurada (icp_brasil.politica_pades).');
        }
        return $p;
    }

    /** Rótulo para registro e manifesto: "PA_PAdES_AD_RB v1.3 (OID ...)". */
    public static function rotuloPolitica(): string
    {
        $p = self::politica();
        return $p['nome'] . ' (OID ' . $p['oid'] . ')';
    }

    /**
     * SignedAttributes em DER com tag SET — é este DER que se assina.
     *
     * @param  string  $digestConteudo  SHA-256 binário dos bytes cobertos pelo /ByteRange
     */
    public static function atributosAssinados(string $certPem, string $digestConteudo): string
    {
        $politica = self::politica();

        $contentType = AsnDer::seq(
            AsnDer::oid(self::OID_CONTENT_TYPE),
            AsnDer::set(AsnDer::oid(self::OID_DATA)),
        );

        $messageDigest = AsnDer::seq(
            AsnDer::oid(self::OID_MESSAGE_DIGEST),
            AsnDer::set(AsnDer::octet($digestConteudo)),
        );

        // SigningCertificateV2 ::= SEQUENCE { certs SEQUENCE OF ESSCertIDv2 }
        // ESSCertIDv2 ::= SEQUENCE { hashAlgorithm DEFAULT sha256 (omitido),
        //                            certHash OCTET STRING, issuerSerial IssuerSerial }
        [$issuerDer, $serialDer] = self::emissorESerie($certPem);
        $issuerSerial = AsnDer::seq(
            AsnDer::seq(AsnDer::der(0xA4, $issuerDer)), // GeneralNames { [4] directoryName }
            $serialDer,
        );
        $certHash = hash('sha256', self::certDer($certPem), true);
        $signingCertV2 = AsnDer::seq(
            AsnDer::oid(self::OID_SIGNING_CERT_V2),
            AsnDer::set(AsnDer::seq(AsnDer::seq(AsnDer::seq(AsnDer::octet($certHash), $issuerSerial)))),
        );

        // SignaturePolicyId ::= SEQUENCE { sigPolicyId OID, sigPolicyHash OtherHashAlgAndValue }
        $sigPolicyId = AsnDer::seq(
            AsnDer::oid(self::OID_SIG_POLICY_ID),
            AsnDer::set(AsnDer::seq(
                AsnDer::oid($politica['oid']),
                AsnDer::seq(
                    AsnDer::seq(AsnDer::oid(self::OID_SHA256)),
                    AsnDer::octet((string) hex2bin($politica['hash'])),
                ),
            )),
        );

        // SET OF em DER: elementos em ordem crescente do encoding
        $attrs = [$contentType, $messageDigest, $signingCertV2, $sigPolicyId];
        usort($attrs, fn ($a, $b) => strcmp($a, $b));

        return AsnDer::set(...$attrs);
    }

    /** Assinatura RSA PKCS#1 v1.5 / SHA-256 dos atributos com a chave do A1. */
    public static function assinarComChave(string $atributosDer, string $chavePrivadaPem): string
    {
        $chave = openssl_pkey_get_private($chavePrivadaPem);
        if ($chave === false || ! openssl_sign($atributosDer, $assinatura, $chave, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Não foi possível assinar com a chave do certificado.');
        }
        return $assinatura;
    }

    /**
     * ContentInfo { SignedData } detached, com o certificado do signatário e as
     * ACs intermediárias (a raiz fica de fora: o verificador já a tem).
     *
     * @param  array<int,string>  $cadeia  PEMs das ACs
     */
    public static function envelope(string $certPem, array $cadeia, string $atributosDer, string $assinatura): string
    {
        [$issuerDer, $serialDer] = self::emissorESerie($certPem);
        $sha256 = AsnDer::seq(AsnDer::oid(self::OID_SHA256), AsnDer::null());

        $signerInfo = AsnDer::seq(
            AsnDer::intSmall(1),
            AsnDer::seq($issuerDer, $serialDer),             // sid: IssuerAndSerialNumber
            $sha256,
            AsnDer::reTag($atributosDer, 0xA0),              // signedAttrs [0] IMPLICIT
            AsnDer::seq(AsnDer::oid(self::OID_RSA_ENCRYPTION), AsnDer::null()),
            AsnDer::octet($assinatura),
        );

        $certs = self::certDer($certPem);
        foreach ($cadeia as $pem) {
            $info = @openssl_x509_parse($pem);
            if ($info && ($info['subject'] ?? null) == ($info['issuer'] ?? null)) {
                continue; // raiz
            }
            $certs .= self::certDer($pem);
        }

        $signedData = AsnDer::seq(
            AsnDer::intSmall(1),
            AsnDer::set($sha256),
            AsnDer::seq(AsnDer::oid(self::OID_DATA)),        // encapContentInfo sem conteúdo (detached)
            AsnDer::der(0xA0, $certs),                       // certificates [0] IMPLICIT
            AsnDer::set($signerInfo),
        );

        return AsnDer::seq(AsnDer::oid(self::OID_SIGNED_DATA), AsnDer::ctxExplicit(0, $signedData));
    }

    /**
     * Emissor e serial copiados byte a byte do certificado: recodificar o Name
     * (tipos de string, ordem) faria o verificador não achar o signatário.
     *
     * @return array{0: string, 1: string} [Name do emissor em DER, INTEGER do serial em DER]
     */
    private static function emissorESerie(string $certPem): array
    {
        $der = self::certDer($certPem);
        $p = self::proximo($der, self::proximo($der, 0)[1])[1];           // Certificate → conteúdo do tbsCertificate
        if (ord($der[$p]) === 0xA0) {                                      // [0] version
            $p = self::proximo($der, $p)[2];
        }
        [$serialInicio, , $serialFim] = self::proximo($der, $p);
        $p = self::proximo($der, $serialFim)[2];                           // signature AlgorithmIdentifier
        [$issuerInicio, , $issuerFim] = self::proximo($der, $p);

        return [
            substr($der, $issuerInicio, $issuerFim - $issuerInicio),
            substr($der, $serialInicio, $serialFim - $serialInicio),
        ];
    }

    /**
     * Lê o cabeçalho TLV em $pos.
     *
     * @return array{0: int, 1: int, 2: int} [início do TLV, início do conteúdo, fim do TLV]
     */
    private static function proximo(string $der, int $pos): array
    {
        $len = ord($der[$pos + 1] ?? "\0");
        $cab = 2;
        if ($len & 0x80) {
            $n = $len & 0x7F;
            $len = 0;
            for ($i = 0; $i < $n; $i++) {
                $len = ($len << 8) | ord($der[$pos + 2 + $i]);
            }
            $cab += $n;
        }
        if ($pos + $cab + $len > strlen($der)) {
            throw new RuntimeException('Certificado com estrutura ASN.1 inválida.');
        }
        return [$pos, $pos + $cab, $pos + $cab + $len];
    }

    public static function certDer(string $pem): string
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
}
