<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Listeners\RegistrarAcesso;
use App\Models\User;
use App\Services\Auth\EntradaService;
use App\Services\CertificadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Entrada do servidor com certificado digital ICP-Brasil (e-CPF A1 instalado
 * ou A3 em token), pela extensão Web PKI — TR 4.7.3.
 *
 *  1. desafio(): o servidor gera um texto de uso único (2 min) e devolve o
 *     SHA-256 dele;
 *  2. o navegador assina esse resumo com a chave do certificado (a chave não
 *     sai da máquina/token) e manda a assinatura + o certificado;
 *  3. entrar(): confere a assinatura sobre o desafio, a cadeia até a AC Raiz
 *     com revogação (LCR) e a validade, e acha o usuário pelo CPF do
 *     certificado. Dispensa o código do aplicativo: o certificado já é o
 *     segundo fator.
 */
class CertificadoLoginController extends Controller
{
    private const VALIDADE_DESAFIO = 120; // segundos

    public function desafio(Request $request): JsonResponse
    {
        $texto = implode('|', ['GPE Docs - entrada com certificado', $request->getHost(), Str::random(40), time()]);
        $request->session()->put('login_certificado', ['texto' => $texto, 'ate' => time() + self::VALIDADE_DESAFIO]);

        return response()->json([
            'hash'      => base64_encode(hash('sha256', $texto, true)),
            'algoritmo' => 'SHA-256',
        ]);
    }

    public function entrar(Request $request, CertificadoService $certificados, EntradaService $entrada)
    {
        $request->validate([
            'cert_pem'       => ['required', 'string', 'max:20000'],
            'assinatura_b64' => ['required', 'string', 'max:5000'],
            'lembrar'        => ['nullable', 'boolean'],
        ]);

        // Desafio de uso único: sai da sessão já na primeira tentativa
        $desafio = $request->session()->pull('login_certificado');
        if (! is_array($desafio) || ($desafio['ate'] ?? 0) < time()) {
            return $this->recusar('O tempo para assinar acabou. Tente de novo.');
        }

        $certPem = CertificadoService::normalizarPem((string) $request->input('cert_pem'));
        $assinatura = base64_decode((string) $request->input('assinatura_b64'), true);
        $chave = $certPem ? @openssl_pkey_get_public($certPem) : false;
        if (! $chave || $assinatura === false
            || openssl_verify($desafio['texto'], $assinatura, $chave, OPENSSL_ALGO_SHA256) !== 1) {
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

        $cpf = preg_replace('/\D/', '', (string) ($meta['subject_cpf'] ?? ''));
        if (strlen($cpf) !== 11) {
            return $this->recusar('Este certificado não é um e-CPF (não traz o CPF do titular).');
        }

        $usuarios = User::query()
            ->whereNotNull('cpf')
            ->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [$cpf])
            ->limit(2)
            ->get();
        if ($usuarios->count() !== 1) {
            RegistrarAcesso::recusa(null, $usuarios->isEmpty() ? 'certificado sem usuário com o CPF' : 'CPF em mais de um usuário', 'certificado', $meta['subject_cn'] ?? null);

            return $this->recusar($usuarios->isEmpty()
                ? 'Nenhum usuário cadastrado com o CPF deste certificado. Procure o administrador.'
                : 'O CPF deste certificado está em mais de um usuário. Procure o administrador.', false);
        }

        $resposta = $entrada->concluir($request, $usuarios->first(), $request->boolean('lembrar'), 'certificado', dispensa2fa: true);

        // O front chama por fetch: devolve para onde ir em vez de seguir o redirect
        $errosSessao = $request->session()->get('errors');
        if ($errosSessao && $errosSessao->has('login')) {
            return response()->json(['erro' => $errosSessao->first('login')], 422);
        }

        return response()->json(['destino' => $resposta->getTargetUrl()]);
    }

    private function recusar(string $mensagem, bool $registrar = true): JsonResponse
    {
        if ($registrar) {
            RegistrarAcesso::recusa(null, $mensagem, 'certificado');
        }

        return response()->json(['erro' => $mensagem], 422);
    }
}
