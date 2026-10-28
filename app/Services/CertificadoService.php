<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificado;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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
     * Valida o certificado contra a truststore ICP-Brasil, inclusive revogação.
     *
     * @param  array<int,string>  $extraCerts  certs intermediários vindos do PFX (anexados como untrusted)
     */
    public function validarCadeiaIcpBrasil(string $certPem, array $extraCerts = []): bool
    {
        return $this->verificarCadeia($certPem, $extraCerts)['ok'];
    }

    /**
     * Verificação completa da cadeia com `openssl verify`:
     *  - só as raízes autoassinadas de icp-brasil/raiz são âncora de confiança;
     *  - intermediárias (do PFX, da pasta intermediarias ou baixadas por AIA)
     *    entram como -untrusted: servem para montar o caminho, nunca para
     *    encerrá-lo — uma "AC" falsa dentro do PFX não valida mais nada;
     *  - todos os níveis têm assinatura e validade conferidas;
     *  - revogação pela LCR de cada nível (-crl_check_all -crl_download).
     *
     * Quando a LCR não pode ser obtida (rede, servidor da AC fora), o resultado
     * depende de icp_brasil.exigir_lcr: por padrão a cadeia é recusada.
     *
     * @param  array<int,string>  $extraCerts
     * @param  array{revogacao?: bool, momento?: int|null}  $opcoes
     * @return array{ok: bool, revogacao: string, motivo: ?string}
     *         revogacao: verificada | revogado | indisponivel | nao_verificada
     */
    public function verificarCadeia(string $certPem, array $extraCerts = [], array $opcoes = []): array
    {
        $comLcr  = (bool) ($opcoes['revogacao'] ?? config('icp_brasil.verificar_revogacao', true));
        $momento = $opcoes['momento'] ?? null;

        $raizes = $this->raizesConfiaveis();
        if (empty($raizes)) {
            return ['ok' => false, 'revogacao' => 'nao_verificada',
                'motivo' => 'Truststore ICP-Brasil não instalada (php artisan icp-brasil:install-truststore).'];
        }

        $untrusted = array_values(array_unique(array_merge($extraCerts, $this->intermediariasInstaladas())));
        // Com data de assinatura, o 1º passo é só estrutural (ver abaixo)
        $lcrPasso1 = $comLcr && ! $momento;
        $r = $this->opensslVerify($certPem, $raizes, $untrusted, $lcrPasso1, $momento);

        // O caminho não fechou com o que temos: busca as ACs emissoras por AIA
        if (! $r['ok'] && in_array($r['codigo'], [2, 20], true)) {
            $enriquecida = $this->buscarCadeiaPorAia($certPem, $untrusted);
            if (count($enriquecida) > count($untrusted)) {
                $untrusted = $enriquecida;
                $r = $this->opensslVerify($certPem, $raizes, $untrusted, $lcrPasso1, $momento);
                if ($r['ok'] || $r['codigo'] === 23) {
                    // Caminho montado: guarda as intermediárias para a próxima vez
                    // (continuam sendo só material de caminho, não âncora)
                    $this->persistirIntermediarias($untrusted);
                }
            }
        }

        // Conferência de documento já assinado: a cadeia vale na data da
        // assinatura (-attime, acima, sem LCR); a LCR atual só é aplicável sem
        // -attime (senão ela "ainda não vale"). Cert já vencido não aparece
        // mais na LCR — aí a revogação fica como não verificada.
        if ($momento && $comLcr) {
            if (! $r['ok']) {
                return ['ok' => false, 'revogacao' => 'nao_verificada', 'motivo' => $this->motivoOpenssl($r['codigo'], $r['saida'])];
            }
            $agora = $this->opensslVerify($certPem, $raizes, $untrusted, true, null);
            return match (true) {
                $agora['ok']              => ['ok' => true, 'revogacao' => 'verificada', 'motivo' => null],
                $agora['codigo'] === 23   => ['ok' => false, 'revogacao' => 'revogado', 'motivo' => 'Certificado revogado pela Autoridade Certificadora (consta na LCR).'],
                in_array($agora['codigo'], [9, 10], true) => ['ok' => true, 'revogacao' => 'nao_verificada', 'motivo' => null],
                default                   => ['ok' => true, 'revogacao' => 'indisponivel', 'motivo' => null],
            };
        }

        if ($r['ok']) {
            return ['ok' => true, 'revogacao' => $comLcr ? 'verificada' : 'nao_verificada', 'motivo' => null];
        }

        if ($r['codigo'] === 23) {
            return ['ok' => false, 'revogacao' => 'revogado', 'motivo' => 'Certificado revogado pela Autoridade Certificadora (consta na LCR).'];
        }

        // LCR indisponível/vencida: confere a cadeia sem ela para separar os casos
        if ($comLcr && in_array($r['codigo'], [3, 11, 12], true)) {
            $semLcr = $this->opensslVerify($certPem, $raizes, $untrusted, false, $momento);
            if (! $semLcr['ok']) {
                return ['ok' => false, 'revogacao' => 'nao_verificada', 'motivo' => $this->motivoOpenssl($semLcr['codigo'], $semLcr['saida'])];
            }
            $exigir = (bool) config('icp_brasil.exigir_lcr', true);
            return [
                'ok'        => ! $exigir,
                'revogacao' => 'indisponivel',
                'motivo'    => 'Não foi possível consultar a lista de revogação (LCR) da Autoridade Certificadora. Tente novamente em alguns minutos.',
            ];
        }

        return ['ok' => false, 'revogacao' => 'nao_verificada', 'motivo' => $this->motivoOpenssl($r['codigo'], $r['saida'])];
    }

    /**
     * @param  array<int,string>  $raizes
     * @param  array<int,string>  $untrusted
     * @return array{ok: bool, codigo: ?int, saida: string}
     */
    private function opensslVerify(string $certPem, array $raizes, array $untrusted, bool $comLcr, ?int $momento): array
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'icpv_' . bin2hex(random_bytes(6));
        @mkdir($dir, 0700, true);
        try {
            file_put_contents($dir . '/cert.pem', $certPem);
            file_put_contents($dir . '/raizes.pem', implode("\n", $raizes));

            $base = ['openssl', 'verify', '-CAfile', $dir . '/raizes.pem'];
            if (! empty($untrusted)) {
                file_put_contents($dir . '/untrusted.pem', implode("\n", $untrusted));
                array_push($base, '-untrusted', $dir . '/untrusted.pem');
            }
            if ($momento) {
                array_push($base, '-attime', (string) $momento);
            }
            $rodar = function (array $extras) use ($base, $dir): array {
                $p = Process::timeout((int) config('icp_brasil.timeout_lcr', 45))
                    ->run([...$base, ...$extras, $dir . '/cert.pem']);
                $saida = trim($p->output() . "\n" . $p->errorOutput());
                $ok = $p->successful() && ! str_contains($saida, 'error ');
                preg_match('/error (\d+) at (\d+) depth/', $saida, $m);
                return ['ok' => $ok, 'codigo' => $ok ? null : (isset($m[1]) ? (int) $m[1] : null),
                    'profundidade' => isset($m[2]) ? (int) $m[2] : null, 'saida' => $saida];
            };

            $r = $rodar($comLcr ? ['-crl_check_all', '-crl_download'] : []);

            // -crl_check_all pede LCR até da AC Raiz, que é a âncora e em geral
            // não aponta LCR para si. O openssl confere de baixo para cima: se
            // a falta de LCR é só no topo, todos os níveis emitidos passaram.
            if ($comLcr && $r['codigo'] === 3) {
                $estrutura = $rodar(['-show_chain']);
                $niveis = preg_match_all('/^depth=\d+/m', $estrutura['saida']);
                if ($estrutura['ok'] && $niveis > 0 && $r['profundidade'] === $niveis - 1) {
                    return ['ok' => true, 'codigo' => null, 'saida' => $r['saida']];
                }
            }
            return ['ok' => $r['ok'], 'codigo' => $r['codigo'], 'saida' => $r['saida']];
        } catch (Throwable $e) {
            // Timeout baixando a LCR cai aqui: trata como LCR indisponível
            return ['ok' => false, 'codigo' => $comLcr ? 3 : null, 'saida' => $e->getMessage()];
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private function motivoOpenssl(?int $codigo, string $saida): string
    {
        return match ($codigo) {
            2, 20   => 'Certificado não encadeia até a AC Raiz da ICP-Brasil (emissor desconhecido ou não confiável).',
            7, 4    => 'Assinatura de um dos certificados da cadeia não confere.',
            9       => 'Certificado (ou AC da cadeia) ainda não é válido.',
            10      => 'Certificado (ou AC da cadeia) expirado.',
            18, 19  => 'Certificado autoassinado ou raiz fora da ICP-Brasil.',
            24      => 'Um certificado da cadeia não é de Autoridade Certificadora.',
            23      => 'Certificado revogado.',
            default => 'Cadeia ICP-Brasil não validada' . ($codigo ? " (código {$codigo})" : '') . '.',
        };
    }

    /**
     * Raízes autoassinadas instaladas em icp-brasil/raiz cuja SHA-256 está
     * fixada em config/icp_brasil.php — as únicas âncoras.
     *
     * @return array<int,string>
     */
    private function raizesConfiaveis(): array
    {
        $fixadas = array_map('strtolower', array_merge(
            array_column((array) config('icp_brasil.raiz', []), 'sha256'),
            (array) config('icp_brasil.raizes_extras_sha256', []),
        ));
        $disk = Storage::disk('local');
        if (! $disk->exists('icp-brasil/raiz')) {
            return [];
        }
        $raizes = [];
        foreach ($disk->allFiles('icp-brasil/raiz') as $f) {
            if (! preg_match('/\.(pem|crt|cer)$/i', $f)) {
                continue;
            }
            $conteudo = (string) $disk->get($f);
            $pems = str_contains($conteudo, '-----BEGIN CERTIFICATE-----')
                ? $this->splitPems($conteudo)
                : ["-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($conteudo), 64, "\n") . "-----END CERTIFICATE-----\n"];
            foreach ($pems as $pem) {
                $info = @openssl_x509_parse($pem, true);
                $pub  = @openssl_pkey_get_public($pem);
                if ($info && $pub && ($info['subject'] ?? null) == ($info['issuer'] ?? null)
                    && openssl_x509_verify($pem, $pub) === 1
                    && in_array(strtolower((string) openssl_x509_fingerprint($pem, 'sha256')), $fixadas, true)) {
                    $raizes[] = $pem;
                }
            }
        }
        return $raizes;
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
     * O cert é ICP-Brasil quando o caminho fecha numa AC Raiz da ICP-Brasil
     * instalada — nome do emissor ("AC ...") ou OID não provam nada, qualquer
     * um gera um certificado assim. Não consulta LCR (é classificação; a
     * revogação é conferida em validarCadeiaIcpBrasil).
     *
     * @param  array<int,string>  $extraCerts
     */
    public function ehIcpBrasil(string $certPem, array $extraCerts = []): bool
    {
        if (@openssl_x509_parse($certPem, true) === false) {
            return false;
        }
        return $this->verificarCadeia($certPem, $extraCerts, ['revogacao' => false])['ok'];
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

        // Cadeia até a AC Raiz da ICP-Brasil, com revogação
        $cadeia = $this->verificarCadeia($certPem, $extraCerts);
        if (! $cadeia['ok']) {
            $erros[] = $cadeia['motivo'];
        }
        $meta['revogacao'] = $cadeia['revogacao'];

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
                'icp_brasil'        => $this->ehIcpBrasil($certPem, $extraCerts),
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
}
