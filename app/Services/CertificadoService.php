<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificado;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use phpseclib3\File\X509;
use RuntimeException;
use Throwable;

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

        // 1ª tentativa: usa apenas o que veio no PFX como intermediarios untrusted
        if ($this->tentarValidar($certPem, $extraCerts, $bundle)) {
            return true;
        }

        // 2ª tentativa: enriquece a cadeia baixando intermediarias via AIA
        // (extensao Authority Information Access do certificado) e tenta de novo
        $cadeiaEnriquecida = $this->buscarCadeiaPorAia($certPem, $extraCerts);
        if (! empty($cadeiaEnriquecida) && $this->tentarValidar($certPem, $cadeiaEnriquecida, $bundle)) {
            // Persiste as intermediarias novas no diretorio para reuso futuro
            $this->persistirIntermediarias($cadeiaEnriquecida);
            return true;
        }

        return false;
    }

    /**
     * Valida cadeia X.509 do cert usando phpseclib3 — 100% PHP, sem depender
     * do openssl CLI (que pode nao estar no PATH no Windows).
     *
     * @param  string  $bundleFile  caminho do arquivo bundle da truststore
     */
    private function tentarValidar(string $certPem, array $untrusted, string $bundleFile): bool
    {
        try {
            $x509 = new X509();

            // Carrega CAs confiaveis (truststore)
            $bundle = (string) file_get_contents($bundleFile);
            foreach ($this->splitPems($bundle) as $caPem) {
                $x509->loadCA($caPem);
            }

            // Carrega intermediarias do PFX como CAs tambem (para a cadeia
            // chegar ate uma das raizes confiaveis)
            foreach ($untrusted as $intPem) {
                $x509->loadCA($intPem);
            }

            // Carrega o cert do usuario e valida
            if (! $x509->loadX509($certPem)) {
                return false;
            }
            return (bool) $x509->validateSignature();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Quebra um bundle PEM concatenado em uma lista de certs PEM individuais.
     * @return array<int, string>
     */
    private function splitPems(string $bundle): array
    {
        if (! preg_match_all(
            '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
            $bundle,
            $matches
        )) {
            return [];
        }
        return $matches[0];
    }

    /**
     * Tenta enriquecer a cadeia baixando os certificados das ACs emissoras
     * a partir da extensao Authority Information Access (AIA) — campo
     * caIssuers — de cada cert. Para subindo a cadeia ate chegar em uma AC
     * autoassinada (raiz) ou ate nao haver mais AIA.
     *
     * Inclui os certs que ja vieram do PFX no resultado.
     *
     * @return array<int, string> certificados PEM
     */
    private function buscarCadeiaPorAia(string $certPem, array $jaConhecidos): array
    {
        $cadeia = $jaConhecidos;
        $atual = $certPem;
        $maxIteracoes = 8;

        // Tambem inclui qualquer intermediaria ja salva em disco
        foreach ($this->intermediariasInstaladas() as $pem) {
            if (! in_array($pem, $cadeia, true)) {
                $cadeia[] = $pem;
            }
        }

        while ($maxIteracoes-- > 0) {
            $url = $this->extrairUrlAia($atual);
            if (! $url) {
                break;
            }

            // Pode vir um cert unico (DER/PEM) ou um bundle .p7b com varios
            $novosPems = $this->baixarCertsDeUrl($url);
            if (empty($novosPems)) {
                break;
            }

            // Pega o primeiro novo cert (ignora se ja conhecido)
            $proximoIssuer = null;
            foreach ($novosPems as $caPem) {
                if (! in_array($caPem, $cadeia, true)) {
                    $cadeia[] = $caPem;
                    if ($proximoIssuer === null) {
                        $proximoIssuer = $caPem;
                    }
                }
            }

            if ($proximoIssuer === null) {
                break;
            }

            $infoAtual = openssl_x509_parse($proximoIssuer, true);
            $issuer = $infoAtual['issuer'] ?? [];
            $subject = $infoAtual['subject'] ?? [];
            if ($issuer === $subject) {
                break; // raiz alcancada
            }

            $atual = $proximoIssuer;
        }

        return $cadeia;
    }

    private function extrairUrlAia(string $certPem): ?string
    {
        $info = openssl_x509_parse($certPem, true);
        $aia = $info['extensions']['authorityInfoAccess'] ?? null;
        if (! is_string($aia)) {
            return null;
        }
        // Formato tipico:
        //   "CA Issuers - URI:http://acsoluti.com.br/.../ac-soluti-multiplav5.crt"
        //   "OCSP - URI:http://ocsp.acsoluti.com.br"
        if (preg_match('/CA Issuers\s*-\s*URI:(\S+)/i', $aia, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Baixa um cert de uma URL AIA. Suporta:
     *   - PEM (.pem, .crt) — cert unico
     *   - DER (.cer, .crt) — cert unico binario
     *   - PKCS#7 / .p7b — bundle de certs (extrai todos)
     *
     * @return array<int, string> PEMs encontrados
     */
    private function baixarCertsDeUrl(string $url): array
    {
        try {
            $resp = Http::timeout(15)->withOptions(['verify' => false])->get($url);
            if (! $resp->successful()) {
                return [];
            }
            $bytes = $resp->body();
            if ($bytes === '') {
                return [];
            }

            // PEM unico
            if (str_contains($bytes, '-----BEGIN CERTIFICATE-----')) {
                return $this->splitPems($bytes);
            }

            // Tenta como PKCS#7 (.p7b) — bundle de certs
            $extraidos = $this->extrairPemsDeP7b($bytes);
            if (! empty($extraidos)) {
                return $extraidos;
            }

            // Cai pra DER simples
            $base64 = chunk_split(base64_encode($bytes), 64, "\n");
            $pem = "-----BEGIN CERTIFICATE-----\n" . $base64 . "-----END CERTIFICATE-----\n";
            // Verifica se e um cert valido X.509
            if (openssl_x509_parse($pem, true) !== false) {
                return [$pem];
            }
            return [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Extrai os certs PEM de um bundle PKCS#7 (.p7b). Aceita formato DER ou PEM.
     */
    private function extrairPemsDeP7b(string $bytes): array
    {
        $tmpIn  = tempnam(sys_get_temp_dir(), 'p7b_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'pems_');
        file_put_contents($tmpIn, $bytes);

        $pems = [];

        // Tenta PEM primeiro (se input ja for PEM)
        $inputForm = str_contains($bytes, '-----BEGIN PKCS7-----') ? PKCS7_TEXT : 0;

        // Reusa openssl_pkcs7_read se possivel (PHP 8+) — extrai certs
        // do envelope para arquivos PEM
        $okPem = @openssl_pkcs7_read(
            $bytes,
            $arrayCerts,
        );

        if (! $okPem) {
            // Tenta DER → converte para PEM e tenta de novo
            if (! str_contains($bytes, 'BEGIN PKCS7')) {
                $pemP7 = "-----BEGIN PKCS7-----\n"
                       . chunk_split(base64_encode($bytes), 64, "\n")
                       . "-----END PKCS7-----\n";
                @openssl_pkcs7_read($pemP7, $arrayCerts);
            }
        }

        @unlink($tmpIn);
        @unlink($tmpOut);

        if (empty($arrayCerts)) {
            return [];
        }

        foreach ($arrayCerts as $c) {
            if (is_string($c) && str_contains($c, '-----BEGIN CERTIFICATE-----')) {
                $pems[] = $c;
            }
        }
        return $pems;
    }

    /**
     * @return array<int, string> PEMs das intermediarias instaladas em disco
     */
    private function intermediariasInstaladas(): array
    {
        $disk = Storage::disk('local');
        $dir = 'icp-brasil/intermediarias';
        if (! $disk->exists($dir)) {
            return [];
        }
        $pems = [];
        foreach ($disk->allFiles($dir) as $f) {
            if (preg_match('/\.(pem|crt|cer)$/i', $f)) {
                $conteudo = (string) $disk->get($f);
                if ($conteudo !== '') {
                    $pems[] = str_contains($conteudo, '-----BEGIN CERTIFICATE-----')
                        ? $conteudo
                        : "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($conteudo), 64, "\n") . "-----END CERTIFICATE-----\n";
                }
            }
        }
        return $pems;
    }

    /**
     * Persiste em disco as ACs intermediarias baixadas via AIA para reuso
     * futuro (assim a 2a vez nao precisa baixar de novo).
     */
    private function persistirIntermediarias(array $pems): void
    {
        $disk = Storage::disk('local');
        $dir = 'icp-brasil/intermediarias';
        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        foreach ($pems as $pem) {
            $info = @openssl_x509_parse($pem, true);
            if (! $info) {
                continue;
            }
            $cn = $info['subject']['CN'] ?? null;
            $issuerCn = $info['issuer']['CN'] ?? null;
            if (! $cn || ! $issuerCn) {
                continue;
            }
            // Nao salva o cert do proprio usuario nem a AC Raiz
            if ($cn === $issuerCn) {
                continue; // autoassinado (raiz)
            }
            $thumb = strtolower((string) openssl_x509_fingerprint($pem, 'sha1'));
            $nome = preg_replace('/[^a-z0-9-]+/i', '_', $cn) . '_' . substr($thumb, 0, 8) . '.pem';
            $caminho = $dir . '/' . $nome;
            if (! $disk->exists($caminho)) {
                $disk->put($caminho, $pem);
            }
        }
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
     * Roda todas as checagens necessarias para usar um cert em assinatura
     * qualificada: pertence a ICP-Brasil, cadeia valida, validade temporal,
     * CPF do cert bate com o do usuario (se cadastrado).
     *
     * @return array{ok: bool, erros: array<int,string>, meta: array}
     */
    public function validarParaUso(User $user, string $certPem, array $extraCerts = []): array
    {
        $erros = [];
        $meta = [];

        try {
            $meta = $this->lerMetadados($certPem);
        } catch (\Throwable $e) {
            return ['ok' => false, 'erros' => ['Não foi possível ler o certificado: ' . $e->getMessage()], 'meta' => []];
        }

        // Validade temporal
        $agora = time();
        $de  = strtotime($meta['valido_de'])  ?: 0;
        $ate = strtotime($meta['valido_ate']) ?: 0;
        if ($agora < $de) {
            $erros[] = 'Certificado ainda não está válido (valido a partir de ' . date('d/m/Y', $de) . ').';
        }
        if ($agora > $ate) {
            $erros[] = 'Certificado expirado em ' . date('d/m/Y', $ate) . '.';
        }

        // ICP-Brasil
        if (! $this->ehIcpBrasil($certPem)) {
            $erros[] = 'Certificado não pertence à cadeia ICP-Brasil.';
        }

        // Cadeia confiavel
        if (! $this->validarCadeiaIcpBrasil($certPem, $extraCerts)) {
            $erros[] = 'Cadeia ICP-Brasil não pôde ser validada — verifique a truststore (storage/app/private/icp-brasil).';
        }

        // CPF
        $cpfCert = preg_replace('/\D/', '', (string) ($meta['subject_cpf'] ?? ''));
        $cpfUser = preg_replace('/\D/', '', (string) $user->cpf);

        if (! $cpfCert) {
            $erros[] = 'CPF não encontrado no certificado (esperado no OID 2.16.76.1.3.1 — pode não ser e-CPF).';
        } elseif ($cpfUser && $cpfCert !== $cpfUser) {
            $erros[] = sprintf(
                'CPF do certificado (%s) não confere com o CPF cadastrado no seu perfil (%s).',
                $cpfCert,
                $cpfUser,
            );
        }

        return [
            'ok'    => empty($erros),
            'erros' => $erros,
            'meta'  => $meta,
        ];
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
     * Extrai o CPF do OID ICP-Brasil 2.16.76.1.3.1 (DOC-ICP-04 do ITI),
     * que reside em subjectAltName.otherName. Conteudo da string e:
     *   DDMMAAAA(8) + CPF(11) + NIS(11) + CEI(12) + TITULO(12)
     *
     * Implementacao: parseia o cert via phpseclib3 e extrai o otherName.value
     * brutamente, ignorando o openssl CLI (que mostra "<unsupported>" e nao
     * decodifica os OIDs ICP-Brasil).
     */
    public function extrairCpf(string $certPem): ?string
    {
        $valor = $this->extrairValorOtherName($certPem, '2.16.76.1.3.1');
        if ($valor === null) {
            return null;
        }
        // Aceita formato DDMMAAAA + CPF11... ou apenas CPF11 (alguns certs antigos)
        if (preg_match('/^(\d{8})(\d{11})/', $valor, $m)) {
            return $m[2];
        }
        if (preg_match('/(\d{11})/', $valor, $m)) {
            return $m[1];
        }
        return null;
    }

    public function extrairCnpj(string $certPem): ?string
    {
        $valor = $this->extrairValorOtherName($certPem, '2.16.76.1.3.3');
        if ($valor === null) {
            return null;
        }
        if (preg_match('/(\d{14})/', $valor, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Localiza um SubjectAltName.otherName com o OID indicado e devolve o
     * value como string (aceita IA5String, UTF8String, OctetString).
     *
     * Usa phpseclib3 X509 para parsear via ASN.1 — nao depende do openssl CLI.
     */
    private function extrairValorOtherName(string $certPem, string $oid): ?string
    {
        try {
            $x509 = new X509();
            if (! $x509->loadX509($certPem)) {
                return null;
            }
            $cert = $x509->getCurrentCert();
            $extensoes = $cert['tbsCertificate']['extensions'] ?? [];

            foreach ($extensoes as $ext) {
                if (($ext['extnId'] ?? '') !== 'id-ce-subjectAltName') {
                    continue;
                }
                foreach ((array) ($ext['extnValue'] ?? []) as $san) {
                    if (! isset($san['otherName'])) {
                        continue;
                    }
                    $other = $san['otherName'];
                    if (($other['type-id'] ?? '') !== $oid) {
                        continue;
                    }

                    // O value e um any (any-defined-by). Pode ser:
                    //   ['ia5String' => '08051980...']
                    //   ['utf8String' => '08051980...']
                    //   ['octetString' => '...']
                    //   ou direto a string
                    $valor = $other['value'] ?? null;
                    if (is_string($valor)) {
                        return $valor;
                    }
                    if (is_array($valor)) {
                        // Pega a primeira string que encontrar
                        foreach ($valor as $v) {
                            if (is_string($v) && $v !== '') {
                                return $v;
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // ignora — caira no return null
        }
        return null;
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
