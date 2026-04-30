<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Validador independente de PDFs assinados em PAdES.
 *
 * Re-abre um PDF qualquer (assinado neste sistema ou em outro), extrai todas
 * as assinaturas presentes na estrutura PDF, e para cada uma:
 *  1) Recompoe o conteudo coberto pelo /ByteRange
 *  2) Verifica que o hash bate com o que esta no envelope PKCS#7
 *  3) Verifica a assinatura criptografica (chave publica do cert vs hash)
 *  4) Valida a cadeia de certificacao contra a truststore ICP-Brasil
 *  5) Confere a validade temporal do certificado
 *  6) Extrai metadados: signatario, CPF, AC emissora, politica, timestamp
 *
 * Observacoes:
 *  - Nao consulta OCSP/CRL (revogacao online) — fica para uma evolucao
 *  - Suporta multiplas assinaturas no mesmo PDF
 *  - Funciona offline desde que a truststore esteja instalada
 */
class AssinaturaValidadorService
{
    public function __construct(
        private readonly CertificadoService $certificadoService,
    ) {
    }

    /**
     * Valida um PDF assinado e devolve um relatorio estruturado.
     *
     * @return array{
     *   tem_assinatura: bool,
     *   total_assinaturas: int,
     *   assinaturas: array<int, array<string, mixed>>,
     *   pdf_sha256: string,
     *   tamanho_bytes: int,
     * }
     */
    public function validar(string $pdfBytes): array
    {
        $resultado = [
            'tem_assinatura'    => false,
            'total_assinaturas' => 0,
            'assinaturas'       => [],
            'pdf_sha256'        => hash('sha256', $pdfBytes),
            'tamanho_bytes'     => strlen($pdfBytes),
        ];

        $assinaturas = $this->extrairAssinaturas($pdfBytes);

        if (empty($assinaturas)) {
            return $resultado;
        }

        $resultado['tem_assinatura']    = true;
        $resultado['total_assinaturas'] = count($assinaturas);

        foreach ($assinaturas as $idx => $assinatura) {
            $resultado['assinaturas'][] = $this->validarUma($pdfBytes, $assinatura, $idx + 1);
        }

        return $resultado;
    }

    /**
     * Localiza objetos de assinatura no PDF (procura por dicionarios /Type /Sig
     * com /ByteRange e /Contents). Suporta multiplas assinaturas.
     *
     * @return array<int, array{byte_range: int[], pkcs7: string, raw_dict: string}>
     */
    private function extrairAssinaturas(string $pdfBytes): array
    {
        $assinaturas = [];

        // /ByteRange [a b c d] aparece em cada dicionario /Sig
        if (! preg_match_all(
            '/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/',
            $pdfBytes,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        $totalMatches = count($matches[0]);
        for ($i = 0; $i < $totalMatches; $i++) {
            $byteRange = [
                (int) $matches[1][$i][0],
                (int) $matches[2][$i][0],
                (int) $matches[3][$i][0],
                (int) $matches[4][$i][0],
            ];

            // O /Contents <hex...> ocupa exatamente o "buraco" descrito pelo byte
            // range: o conteudo hexadecimal vai de byteRange[1] (logo apos o '<')
            // ate byteRange[2] (onde fecha o '>'). Pegamos uns bytes a mais antes
            // e depois para incluir os delimitadores e a palavra-chave /Contents.
            $sliceStart = max(0, $byteRange[1] - 50);
            $sliceEnd   = min(strlen($pdfBytes), $byteRange[2] + 5);
            $sigSlice   = substr($pdfBytes, $sliceStart, $sliceEnd - $sliceStart);

            if (! preg_match('/\/Contents\s*<([0-9a-fA-F\s]+)>/', $sigSlice, $contentsMatch)) {
                continue;
            }

            $hex = preg_replace('/\s+/', '', $contentsMatch[1]);
            $bin = hex2bin((string) $hex);
            if ($bin === false) {
                continue;
            }
            $pkcs7 = rtrim($bin, "\0");

            // Para campos auxiliares (/Reason, /Location, /M, /SubFilter etc.)
            // unimos a janela ANTES do /Contents (cobre /SubFilter, /Filter, /Type
            // que normalmente vem antes) com a janela DEPOIS do /Contents (cobre
            // /Reason, /Location, /Name, /M que vem depois).
            $startOffset = $matches[0][$i][1];
            $antes  = substr($pdfBytes, max(0, $startOffset - 300), 800);
            $depois = substr($pdfBytes, $byteRange[2], 600);
            $rawDict = $antes . "\n" . $depois;

            $assinaturas[] = [
                'byte_range' => $byteRange,
                'pkcs7'      => $pkcs7,
                'raw_dict'   => $rawDict,
            ];
        }

        return $assinaturas;
    }

    /**
     * Valida uma assinatura especifica.
     *
     * @param  array{byte_range: int[], pkcs7: string, raw_dict: string}  $assinatura
     */
    private function validarUma(string $pdfBytes, array $assinatura, int $ordem): array
    {
        [$start1, $len1, $start2, $len2] = $assinatura['byte_range'];

        // Recompoe os bytes cobertos pela assinatura
        $coberto = substr($pdfBytes, $start1, $len1) . substr($pdfBytes, $start2, $len2);

        $relatorio = [
            'ordem'                => $ordem,
            'byte_range'           => $assinatura['byte_range'],
            'cobertura_total_bytes'=> strlen($coberto),
            'cobertura_pdf_total'  => strlen($coberto) === strlen($pdfBytes) - $len1 + ($start1 - 0) - 0
                ? true
                : ($start1 + $len1 + $len2 + ($start2 - $start1 - $len1) === strlen($pdfBytes)),
            'pkcs7_tamanho'        => strlen($assinatura['pkcs7']),
            'verificacao'          => null,
            'cadeia_valida'        => null,
            'cert_valido_no_tempo' => null,
            'signatario'           => null,
            'extras'               => [
                'reason'      => $this->extrairCampoString($assinatura['raw_dict'], 'Reason'),
                'location'    => $this->extrairCampoString($assinatura['raw_dict'], 'Location'),
                'name'        => $this->extrairCampoString($assinatura['raw_dict'], 'Name'),
                'contact_info'=> $this->extrairCampoString($assinatura['raw_dict'], 'ContactInfo'),
                'sub_filter'  => $this->extrairCampoNome($assinatura['raw_dict'], 'SubFilter'),
                'modificado'  => $this->extrairTimestamp($assinatura['raw_dict']),
            ],
            'algoritmo'            => null,
            'erros'                => [],
        ];

        // 1) Verifica a assinatura criptografica.
        // openssl_pkcs7_verify() / openssl_cms_verify() em PHP 8.3 + OpenSSL 3
        // no Windows produzem 'CMS verification failure' para PKCS7 detached
        // mesmo quando o openssl CLI verifica com sucesso. Usamos o CLI direto
        // — ele sempre esta disponivel onde o PHP openssl esta.
        $sigP7Der = tempnam(sys_get_temp_dir(), 'sigp7_');
        file_put_contents($sigP7Der, $assinatura['pkcs7']);
        $cobertoFile = tempnam(sys_get_temp_dir(), 'covered_');
        file_put_contents($cobertoFile, $coberto);
        $certsExtraidos = tempnam(sys_get_temp_dir(), 'extr_certs_');

        $cmd = sprintf(
            'openssl smime -verify -in %s -inform DER -content %s -noverify -binary -signer %s -out %s 2>&1',
            escapeshellarg($sigP7Der),
            escapeshellarg($cobertoFile),
            escapeshellarg($certsExtraidos),
            escapeshellarg(tempnam(sys_get_temp_dir(), 'discarded_')),
        );
        $saida = (string) shell_exec($cmd);
        $verificado = str_contains($saida, 'Verification successful');

        $relatorio['verificacao'] = $verificado;

        if (! $verificado) {
            $relatorio['erros'][] = 'CLI: ' . trim($saida);
        }

        // 2) Le os certs extraidos (cadeia inteira embutida no envelope)
        $signatarioCert = null;
        if (is_file($certsExtraidos)) {
            $pem = (string) file_get_contents($certsExtraidos);
            // Pega o primeiro cert (e o do signatario; os subsequentes sao a cadeia)
            if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $m)) {
                $signatarioCert = $m[0];
            }
            // Tambem coleta cadeia
            preg_match_all(
                '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
                $pem,
                $todos
            );
            $cadeia = array_slice($todos[0] ?? [], 1);
        } else {
            $cadeia = [];
        }

        if ($signatarioCert) {
            $meta = $this->certificadoService->lerMetadados($signatarioCert);
            $relatorio['signatario'] = [
                'cn'             => $meta['subject_cn'],
                'cpf'            => $meta['subject_cpf'],
                'cnpj'           => $meta['subject_cnpj'],
                'issuer_cn'      => $meta['issuer_cn'],
                'serial'         => $meta['serial_number'],
                'valido_de'      => $meta['valido_de'],
                'valido_ate'     => $meta['valido_ate'],
                'thumbprint_sha256' => $meta['thumbprint_sha256'],
                'icp_brasil'     => $this->certificadoService->ehIcpBrasil($signatarioCert),
            ];

            // 3) Cadeia ICP-Brasil
            $relatorio['cadeia_valida'] = $this->certificadoService
                ->validarCadeiaIcpBrasil($signatarioCert, $cadeia);

            // 4) Validade temporal do cert
            $agora = time();
            $de  = strtotime($meta['valido_de'])  ?: 0;
            $ate = strtotime($meta['valido_ate']) ?: 0;
            $relatorio['cert_valido_no_tempo'] = $agora >= $de && $agora <= $ate;

            // 5) Algoritmo (extraido por inspecao do PKCS7)
            $relatorio['algoritmo'] = $this->detectarAlgoritmo($assinatura['pkcs7']);
        }

        // Cleanup
        @unlink($sigP7Der);
        @unlink($cobertoFile);
        if (is_file($certsExtraidos)) {
            @unlink($certsExtraidos);
        }

        return $relatorio;
    }

    private function extrairCampoString(string $dict, string $campo): ?string
    {
        // Strings PDF podem vir em UTF-16BE (com BOM \xFE\xFF) ou ASCII
        if (! preg_match('/\/' . preg_quote($campo, '/') . '\s*\(([^)]*)\)/s', $dict, $m)) {
            return null;
        }
        $bruto = $m[1];

        // Se BOM UTF-16BE
        if (str_starts_with($bruto, "\xFE\xFF")) {
            $utf16 = substr($bruto, 2);
            $utf8  = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
            return $utf8 !== false ? $utf8 : $bruto;
        }

        return $bruto;
    }

    /**
     * Extrai um valor do tipo "name" (PDF Name objects, prefixados por /).
     * Ex: /SubFilter /adbe.pkcs7.detached  →  retorna "adbe.pkcs7.detached"
     */
    private function extrairCampoNome(string $dict, string $campo): ?string
    {
        if (! preg_match('/\/' . preg_quote($campo, '/') . '\s*\/(\S+?)(?=[\s\/<>])/', $dict, $m)) {
            return null;
        }
        return $m[1];
    }

    private function extrairTimestamp(string $dict): ?string
    {
        // /M (D:YYYYMMDDHHMMSS+00'00')
        if (! preg_match('/\/M\s*\(D:(\d{14})/', $dict, $m)) {
            return null;
        }
        $ts = $m[1];
        return sprintf(
            '%s-%s-%sT%s:%s:%s',
            substr($ts, 0, 4),
            substr($ts, 4, 2),
            substr($ts, 6, 2),
            substr($ts, 8, 2),
            substr($ts, 10, 2),
            substr($ts, 12, 2),
        );
    }

    private function detectarAlgoritmo(string $pkcs7): ?string
    {
        // Heuristica: procura OIDs comuns de digest no DER do envelope
        $hex = bin2hex($pkcs7);

        // SHA-256: OID 2.16.840.1.101.3.4.2.1 → DER 06 09 60 86 48 01 65 03 04 02 01
        if (str_contains($hex, '0609608648016503040201')) {
            return 'SHA-256';
        }
        // SHA-1: 2b 0e 03 02 1a → 06 05 2b 0e 03 02 1a
        if (str_contains($hex, '06052b0e03021a')) {
            return 'SHA-1';
        }
        // SHA-512: 2.16.840.1.101.3.4.2.3
        if (str_contains($hex, '0609608648016503040203')) {
            return 'SHA-512';
        }

        return null;
    }
}
