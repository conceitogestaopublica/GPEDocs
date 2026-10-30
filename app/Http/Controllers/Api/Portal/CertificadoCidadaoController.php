<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Services\CertificadoService;
use App\Services\Portal\CidadaoPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Entrada do cidadão no Portal com certificado ICP-Brasil (e-CPF ou e-CNPJ),
 * pela Web PKI no navegador do cidadão — mesmo método da entrada do servidor
 * (Auth\CertificadoLoginController), mas sem sessão:
 *
 *  1. desafio(): texto de uso único dentro de um token assinado com a APP_KEY
 *     (vale 2 min) + o SHA-256 do texto, que o navegador assina;
 *  2. autenticar(): confere o token, a assinatura sobre o texto, a validade e a
 *     cadeia até a AC Raiz com LCR, e acha o cidadão pelo CPF/CNPJ do
 *     certificado. Sem cadastro: 404 com documento e nome, para o portal
 *     já abrir o cadastro preenchido.
 */
class CertificadoCidadaoController extends PortalApiController
{
    private const VALIDADE_DESAFIO = 120; // segundos

    public function desafio(Request $request): JsonResponse
    {
        $texto = implode('|', ['GPE Docs - portal do cidadão', $request->getHost(), Str::random(40), time()]);
        $carga = rtrim(strtr(base64_encode(json_encode(['t' => $texto, 'ate' => time() + self::VALIDADE_DESAFIO])), '+/', '-_'), '=');

        return response()->json([
            'desafio'   => $carga . '.' . hash_hmac('sha256', $carga, (string) config('app.key')),
            'hash'      => base64_encode(hash('sha256', $texto, true)),
            'algoritmo' => 'SHA-256',
        ]);
    }

    public function autenticar(Request $request, CertificadoService $certificados, CidadaoPortalService $cidadaos): JsonResponse
    {
        $request->validate([
            'desafio'        => ['required', 'string', 'max:1000'],
            'cert_pem'       => ['required', 'string', 'max:20000'],
            'assinatura_b64' => ['required', 'string', 'max:5000'],
        ]);

        $texto = $this->abrirDesafio((string) $request->input('desafio'));
        if ($texto === null) {
            return $this->recusar('O tempo para assinar acabou. Tente de novo.');
        }

        $certPem = CertificadoService::normalizarPem((string) $request->input('cert_pem'));
        $assinatura = base64_decode((string) $request->input('assinatura_b64'), true);
        $chave = $certPem ? @openssl_pkey_get_public($certPem) : false;
        if (! $chave || $assinatura === false
            || openssl_verify($texto, $assinatura, $chave, OPENSSL_ALGO_SHA256) !== 1) {
            return $this->recusar('A assinatura não confere com o certificado apresentado.');
        }

        $meta = $certificados->lerMetadados($certPem);
        $agora = time();
        if ($agora < strtotime($meta['valido_de']) || $agora > strtotime($meta['valido_ate'])) {
            return $this->recusar('Certificado fora da validade.');
        }

        $cadeia = $certificados->verificarCadeia($certPem);
        if (! $cadeia['ok']) {
            return $this->recusar($cadeia['motivo']);
        }

        $documento = CidadaoPortalService::digitos($certificados->extrairCpf($certPem) ?? $certificados->extrairCnpj($certPem));
        if (! in_array(strlen($documento), [11, 14], true)) {
            return $this->recusar('Este certificado não traz o CPF nem o CNPJ do titular.');
        }

        $cidadao = $cidadaos->porIdentidadeComprovada($documento);
        if (! $cidadao) {
            return response()->json([
                'erro'      => 'Não há cadastro para este certificado. Faça o cadastro no portal.',
                'documento' => $documento,
                'nome'      => $this->nomeDoTitular((string) ($meta['subject_cn'] ?? '')),
            ], 404);
        }

        return response()->json(['cidadao' => CidadaoPortalService::paraApi($cidadao)]);
    }

    /** Texto do desafio, se o token é nosso, está no prazo e não foi usado. */
    private function abrirDesafio(string $token): ?string
    {
        [$carga, $mac] = array_pad(explode('.', $token, 2), 2, '');
        if (! hash_equals(hash_hmac('sha256', $carga, (string) config('app.key')), $mac)) {
            return null;
        }
        $dados = json_decode((string) base64_decode(strtr($carga, '-_', '+/')), true);
        if (! is_array($dados) || ($dados['ate'] ?? 0) < time()) {
            return null;
        }
        // Uso único: a primeira tentativa queima o desafio, certa ou errada.
        if (! Cache::add('portal-cert-desafio:' . hash('sha256', $carga), 1, self::VALIDADE_DESAFIO + 60)) {
            return null;
        }

        return (string) $dados['t'];
    }

    /** CN do ICP-Brasil é "NOME:CPF" (ou "RAZÃO SOCIAL:CNPJ") — fica só o nome. */
    private function nomeDoTitular(string $cn): ?string
    {
        $nome = trim((string) preg_replace('/:\d{11,14}$/', '', $cn));

        return $nome === '' ? null : $nome;
    }

    private function recusar(string $mensagem): JsonResponse
    {
        return response()->json(['erro' => $mensagem], 422);
    }
}
