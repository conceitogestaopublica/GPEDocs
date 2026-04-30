<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificado;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Manipulação de certificados ICP-Brasil:
 *  - Lê PFX/PKCS#12 (formato dos certificados A1)
 *  - Valida a cadeia contra a truststore do ITI em storage/app/icp-brasil
 *  - Extrai CPF do titular via OID 2.16.76.1.3.1 (DOC-ICP-04 do ITI)
 *  - Persiste o cert público no banco para uso futuro (sem chave privada)
 */
class CertificadoService
{
    private const OID_CPF_PESSOA_FISICA = '2.16.76.1.3.1';
    private const OID_CNPJ              = '2.16.76.1.3.3';

    /**
     * Abre um PFX/P12 com a senha informada. Retorna array com:
     *  - cert       (string PEM do certificado do titular)
     *  - pkey       (string PEM da chave privada — manter em memória)
     *  - extracerts (array de PEMs dos certificados da cadeia)
     *
     * @throws RuntimeException quando o PFX não pode ser aberto (senha errada, arquivo corrompido)
     */
    public function abrirPfx(string $pfxBinary, string $senha): array
    {
        $resultado = [];

        $aberto = openssl_pkcs12_read($pfxBinary, $resultado, $senha);

        if (! $aberto) {
            throw new RuntimeException(
                'Não foi possível abrir o certificado. Verifique a senha e o arquivo .pfx/.p12.'
            );
        }

        return [
            'cert'       => $resultado['cert'] ?? '',
            'pkey'       => $resultado['pkey'] ?? '',
            'extracerts' => $resultado['extracerts'] ?? [],
        ];
    }

    /**
     * Extrai metadados do certificado (subject, issuer, validade, CPF do titular, etc.).
     */
    public function lerMetadados(string $certPem): array
    {
        $info = openssl_x509_parse($certPem, true);

        if ($info === false) {
            throw new RuntimeException('Certificado inválido.');
        }

        $subject = $this->dnAsString($info['subject'] ?? []);
        $issuer  = $this->dnAsString($info['issuer'] ?? []);

        $cpf  = $this->extrairCpf($certPem);
        $cnpj = $this->extrairCnpj($certPem);

        return [
            'subject_cn'     => $info['subject']['CN'] ?? '',
            'subject_dn'     => $subject,
            'subject_cpf'    => $cpf,
            'subject_cnpj'   => $cnpj,
            'issuer_cn'      => $info['issuer']['CN'] ?? '',
            'issuer_dn'      => $issuer,
            'serial_number'  => isset($info['serialNumberHex'])
                ? strtoupper((string) $info['serialNumberHex'])
                : (string) ($info['serialNumber'] ?? ''),
            'valido_de'      => date('Y-m-d H:i:s', (int) ($info['validFrom_time_t'] ?? 0)),
            'valido_ate'     => date('Y-m-d H:i:s', (int) ($info['validTo_time_t'] ?? 0)),
            'thumbprint_sha1'   => strtolower((string) openssl_x509_fingerprint($certPem, 'sha1')),
            'thumbprint_sha256' => strtolower((string) openssl_x509_fingerprint($certPem, 'sha256')),
            'politica_oid'   => $this->extrairPoliticaOid($info),
        ];
    }

    /**
     * Valida o certificado contra a truststore ICP-Brasil em storage/app/icp-brasil.
     *
     * @param  array<int,string>  $extraCerts  certs intermediários vindos do PFX (anexados como untrusted)
     */
    public function validarCadeiaIcpBrasil(string $certPem, array $extraCerts = []): bool
    {
        $bundle = $this->montarBundleTruststore();

        if ($bundle === null) {
            // Sem truststore configurada → não há como validar; rejeita por segurança
            return false;
        }

        $untrustedFile = $this->materializarUntrustedBundle($extraCerts);

        $resultado = openssl_x509_checkpurpose(
            $certPem,
            X509_PURPOSE_ANY,
            [$bundle],
            $untrustedFile,
        );

        if ($untrustedFile !== null && is_file($untrustedFile)) {
            @unlink($untrustedFile);
        }

        return $resultado === true;
    }

    /**
     * Verifica se o cert é "ICP-Brasil" pela presença do OID de política da
     * cadeia ou pelo issuer começar com "AC " (heurística suficiente para o MVP).
     */
    public function ehIcpBrasil(string $certPem): bool
    {
        $info = openssl_x509_parse($certPem, true);
        if ($info === false) {
            return false;
        }

        $issuerCn = $info['issuer']['CN'] ?? '';
        if (str_starts_with($issuerCn, 'AC ') || str_contains($issuerCn, 'ICP-Brasil')) {
            return true;
        }

        $certText = $this->certificadoComoTexto($certPem);
        return $certText !== null && str_contains($certText, '2.16.76.1');
    }

    /**
     * Persiste/atualiza o certificado para o usuário no banco (sem chave privada).
     */
    public function registrarParaUsuario(User $user, string $certPem, array $extraCerts = []): Certificado
    {
        $meta = $this->lerMetadados($certPem);

        return Certificado::updateOrCreate(
            [
                'user_id'           => $user->id,
                'thumbprint_sha256' => $meta['thumbprint_sha256'],
            ],
            [
                'tipo'              => 'A1',
                'subject_cn'        => $meta['subject_cn'],
                'subject_cpf'       => $meta['subject_cpf'],
                'subject_dn'        => $meta['subject_dn'],
                'issuer_cn'         => $meta['issuer_cn'],
                'issuer_dn'         => $meta['issuer_dn'],
                'serial_number'     => $meta['serial_number'],
                'thumbprint_sha1'   => $meta['thumbprint_sha1'],
                'valido_de'         => $meta['valido_de'],
                'valido_ate'        => $meta['valido_ate'],
                'certificado_pem'   => $certPem,
                'cadeia_pem'        => $extraCerts,
                'politica_oid'      => $meta['politica_oid'],
                'icp_brasil'        => $this->ehIcpBrasil($certPem),
                'verificado_em'     => now(),
            ]
        );
    }

    /**
     * Extrai o CPF a partir do OID ICP-Brasil 2.16.76.1.3.1 que reside na extensão
     * subjectAltName (otherName). O conteúdo é uma string ASCII com formato:
     *   "DDMMAAAACPF11digitosNIS11digitosCEI12digitosTITULO0digitos"
     * (Conforme DOC-ICP-04 v8.0 do ITI). Retornamos apenas o CPF.
     */
    public function extrairCpf(string $certPem): ?string
    {
        $texto = $this->certificadoComoTexto($certPem);
        if ($texto === null) {
            return null;
        }

        // openssl asn1parse expõe o OID 2.16.76.1.3.1 com a string que segue;
        // o formato típico tem ".." marcadores ASN.1 antes do payload.
        if (! preg_match('/2\.16\.76\.1\.3\.1.*?:.*?(\d{8})(\d{11})/s', $texto, $m)) {
            // Tentativa secundária via dump openssl x509 -text
            if (! preg_match('/2\.16\.76\.1\.3\.1.{0,200}?(\d{8})(\d{11})/s', $texto, $m)) {
                return null;
            }
        }

        return $m[2] ?? null;
    }

    public function extrairCnpj(string $certPem): ?string
    {
        $texto = $this->certificadoComoTexto($certPem);
        if ($texto === null) {
            return null;
        }

        if (! preg_match('/2\.16\.76\.1\.3\.3.{0,200}?(\d{14})/s', $texto, $m)) {
            return null;
        }

        return $m[1];
    }

    private function dnAsString(array $dn): string
    {
        $partes = [];
        foreach ($dn as $k => $v) {
            $valor = is_array($v) ? implode(',', $v) : (string) $v;
            $partes[] = "{$k}={$valor}";
        }
        return implode(', ', $partes);
    }

    private function extrairPoliticaOid(array $info): ?string
    {
        $extensoes = $info['extensions'] ?? [];
        $cp = $extensoes['certificatePolicies'] ?? null;
        if (! is_string($cp)) {
            return null;
        }
        if (preg_match('/Policy:\s*([0-9.]+)/', $cp, $m)) {
            return $m[1];
        }
        return null;
    }

    private function certificadoComoTexto(string $certPem): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ged_cert_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $certPem);

        $cmd = sprintf(
            'openssl x509 -in %s -noout -text 2>&1',
            escapeshellarg($tmp)
        );
        $saida = shell_exec($cmd);
        @unlink($tmp);

        return is_string($saida) ? $saida : null;
    }

    private function montarBundleTruststore(): ?string
    {
        $disk = Storage::disk('local');
        $files = collect($disk->allFiles('icp-brasil'))
            ->filter(fn ($f) => str_ends_with($f, '.pem') || str_ends_with($f, '.crt'));

        if ($files->isEmpty()) {
            return null;
        }

        $bundle = '';
        foreach ($files as $f) {
            $bundle .= $disk->get($f) . "\n";
        }

        $tmp = tempnam(sys_get_temp_dir(), 'icp_bundle_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $bundle);

        return $tmp;
    }

    /**
     * Concatena PEMs intermediários em um único arquivo (formato esperado pelo
     * 4º parâmetro de openssl_x509_checkpurpose).
     *
     * @param  array<int,string>  $pems
     */
    private function materializarUntrustedBundle(array $pems): ?string
    {
        if (empty($pems)) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'icp_int_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, implode("\n", $pems));
        return $tmp;
    }
}
