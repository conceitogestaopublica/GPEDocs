<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificadoInstitucional;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Selo da prefeitura nos PDFs assinados por link: o PDF carimbado sai assinado digitalmente
 * (PAdES) com o e-CNPJ A1 da UG. Qualquer alteração no arquivo invalida o selo — é o que
 * aproxima a assinatura por link da "avançada" da Lei 14.063/2020.
 *
 * Em produção só aceita certificado com cadeia ICP-Brasil válida; em local/testing aceita
 * um de teste (para validar o fluxo sem e-CNPJ real).
 */
class SeloInstitucionalService
{
    public function __construct(private readonly CertificadoService $certificados)
    {
    }

    /**
     * Confere e grava o .pfx da UG (substitui o anterior).
     *
     * @throws RuntimeException com mensagem para a tela
     */
    public function salvar(int $ugId, string $pfxBinary, string $senha, ?int $usuarioId): CertificadoInstitucional
    {
        $material = $this->certificados->abrirPfx($pfxBinary, $senha);
        if (! $material['cert'] || ! $material['pkey']) {
            throw new RuntimeException('O arquivo não contém certificado e chave privada.');
        }

        $meta = $this->certificados->lerMetadados($material['cert']);
        $agora = now();
        if ($agora->lt($meta['valido_de']) || $agora->gt($meta['valido_ate'])) {
            throw new RuntimeException('Certificado fora da validade (' . date('d/m/Y', strtotime($meta['valido_de']))
                . ' a ' . date('d/m/Y', strtotime($meta['valido_ate'])) . ').');
        }

        $icp = $this->certificados->validarCadeiaIcpBrasil($material['cert'], $material['extracerts']);
        if (! $icp && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('O selo exige certificado ICP-Brasil (e-CNPJ A1) com cadeia válida.');
        }

        return CertificadoInstitucional::updateOrCreate(['ug_id' => $ugId], [
            'pfx'               => base64_encode($pfxBinary),
            'senha'             => $senha,
            'subject_cn'        => mb_substr((string) $meta['subject_cn'], 0, 255),
            'cnpj'              => $meta['subject_cnpj'] ?: null,
            'issuer_cn'         => mb_substr((string) $meta['issuer_cn'], 0, 255),
            'serial_number'     => mb_substr((string) $meta['serial_number'], 0, 80),
            'thumbprint_sha256' => $meta['thumbprint_sha256'],
            'valido_de'         => $meta['valido_de'],
            'valido_ate'        => $meta['valido_ate'],
            'icp_brasil'        => $icp,
            'ativo'             => true,
            'enviado_por'       => $usuarioId,
        ]);
    }

    /**
     * Material para assinar o PDF da UG, ou null se ela não tem certificado vigente (o PDF
     * sai só com o carimbo e o termo, como antes). Nunca derruba a assinatura do externo.
     *
     * @return array{cert:string, pkey:string, extracerts:array, meta:array, registro:CertificadoInstitucional}|null
     */
    public function material(?int $ugId): ?array
    {
        if (! $ugId) {
            return null;
        }
        $registro = CertificadoInstitucional::where('ug_id', $ugId)->first();
        if (! $registro || ! $registro->vigente()) {
            return null;
        }

        try {
            $material = $this->certificados->abrirPfx(base64_decode((string) $registro->pfx), (string) $registro->senha);
        } catch (Throwable $e) {
            Log::warning('Selo da prefeitura indisponível', ['ug_id' => $ugId, 'erro' => $e->getMessage()]);

            return null;
        }

        return $material + ['meta' => $this->certificados->lerMetadados($material['cert']), 'registro' => $registro];
    }
}
