<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assinatura;
use App\Models\Certificado;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Assinatura digital qualificada com certificado A1 (Lei 14.063/2020 art. 4, III).
 *
 * Fluxo:
 *   1) Recebe o PDF oficial do documento (o último original assinado ou a
 *      versão) + PFX/senha do signatário
 *   2) PadesIncremental acrescenta ao fim do PDF o espaço da assinatura — as
 *      assinaturas que ele já tem continuam válidas
 *   3) O CMS é montado no padrão da política PA_PAdES_AD_RB do ITI (CmsAdRb),
 *      assinado com a chave do PFX e gravado no espaço reservado
 *   4) Persiste o novo original assinado; carimbo, tarja e termo ficam na cópia
 *      para impressão (CarimboAssinaturaService)
 *   5) Atualiza o registro de Assinatura com metadados criptográficos
 */
class AssinaturaIcpService
{
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
        $meta['url_verificacao'] = $razao['url_verificacao'] ?? null;

        if (! $this->certificadoService->ehIcpBrasil($certPem, $cadeia)) {
            throw new RuntimeException(
                'O certificado informado não pertence à cadeia ICP-Brasil — assinatura qualificada exige cert. ICP-Brasil.'
            );
        }

        // Assinatura acrescentada ao fim do PDF oficial (incremental): as
        // anteriores continuam válidas. O visual vai na cópia para impressão.
        [$pdf, $byteRange] = PadesIncremental::preparar($pdfOrigem, [
            'nome'    => $meta['subject_cn'] ?? '',
            'razao'   => $razao['razao']   ?? 'Assinatura Eletrônica Qualificada (Lei 14.063/2020)',
            'local'   => $razao['local']   ?? 'Brasil',
            'contato' => $razao['url_verificacao'] ?? '',
        ]);
        $atributos = CmsAdRb::atributosAssinados($certPem, PadesPlaceholder::digest($pdf, $byteRange));
        $pkcs7 = CmsAdRb::envelope($certPem, $cadeia, $atributos, CmsAdRb::assinarComChave($atributos, $pkeyPem));
        $pdfAssinado = PadesPlaceholder::embutir($pdf, $byteRange, $pkcs7);

        // Persiste o resultado
        $disk = Storage::disk('documentos');
        $nomeArquivo = sprintf(
            \App\Tenant\TenantStorage::pasta('assinaturas/icp') . '/%s_%s.pdf',
            date('Ymd_His'),
            substr($meta['thumbprint_sha256'], 0, 12),
        );
        $disk->put($nomeArquivo, $pdfAssinado);

        return [
            'caminho' => $nomeArquivo,
            'pkcs7'   => $pkcs7,
            'cadeia'  => $cadeia,
            'cert'    => $certPem,
            'meta'    => $meta + [
                'politica_oid'  => CmsAdRb::politica()['oid'],
                'politica_nome' => CmsAdRb::politica()['nome'],
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
            'politica_assinatura'     => CmsAdRb::rotuloPolitica(),
            'algoritmo_hash'          => 'SHA-256',
            'arquivo_assinado_path'   => $resultado['caminho'],
            'hash_assinatura_sha256'  => hash('sha256', $resultado['pkcs7']),
            'timestamp_assinatura'    => now(),
            'assinado_em'             => now(),
        ]);

        return $assinatura->refresh();
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
