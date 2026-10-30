<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Math\BigInteger;
use RuntimeException;

/**
 * Cliente OpenID Connect (fluxo authorization code + PKCE) para gov.br e
 * Google. Provedor configurado em config/services.php ('govbr', 'google').
 *
 * O id_token é validado aqui: assinatura RS256 pela chave publicada pelo
 * provedor (JWKS), emissor, audiência, validade e nonce.
 */
final class OidcCliente
{
    public const PROVEDORES = ['govbr', 'google'];

    public function __construct(private readonly string $provedor)
    {
        if (! in_array($provedor, self::PROVEDORES, true)) {
            throw new RuntimeException('Provedor de entrada desconhecido.');
        }
    }

    public static function ativo(string $provedor): bool
    {
        return in_array($provedor, self::PROVEDORES, true)
            && config("services.$provedor.ativo")
            && config("services.$provedor.client_id");
    }

    public function urlAutorizacao(string $retorno, string $state, string $nonce, string $verificador): string
    {
        return $this->descoberta()['authorization_endpoint'] . '?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => config("services.{$this->provedor}.client_id"),
            'redirect_uri'          => $retorno,
            'scope'                 => config("services.{$this->provedor}.escopos"),
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => self::base64url(hash('sha256', $verificador, true)),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Troca o código pelo id_token e devolve as declarações já validadas.
     *
     * @return array<string, mixed>
     */
    public function identificar(string $codigo, string $retorno, string $verificador, string $nonce): array
    {
        $resp = Http::asForm()->timeout(20)
            ->withBasicAuth((string) config("services.{$this->provedor}.client_id"), (string) config("services.{$this->provedor}.client_secret"))
            ->post($this->descoberta()['token_endpoint'], [
                'grant_type'    => 'authorization_code',
                'code'          => $codigo,
                'redirect_uri'  => $retorno,
                'code_verifier' => $verificador,
            ]);
        if (! $resp->successful() || ! $resp->json('id_token')) {
            throw new RuntimeException('O provedor recusou o código de acesso.');
        }

        return $this->validarIdToken((string) $resp->json('id_token'), $nonce);
    }

    /** @return array<string, mixed> */
    public function validarIdToken(string $jwt, string $nonce): array
    {
        $partes = explode('.', $jwt);
        if (count($partes) !== 3) {
            throw new RuntimeException('Token de identidade malformado.');
        }
        [$cab64, $corpo64, $assin64] = $partes;
        $cab = json_decode(self::deBase64url($cab64), true);
        $dados = json_decode(self::deBase64url($corpo64), true);
        if (! is_array($cab) || ! is_array($dados) || ($cab['alg'] ?? '') !== 'RS256') {
            throw new RuntimeException('Token de identidade com algoritmo não aceito.');
        }

        $chave = $this->chavePublica($cab['kid'] ?? null);
        if (openssl_verify("$cab64.$corpo64", self::deBase64url($assin64), $chave, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Assinatura do token de identidade inválida.');
        }

        $emissor = $this->descoberta()['issuer'] ?? null;
        $aud = (array) ($dados['aud'] ?? []);
        $agora = time();
        if (rtrim((string) ($dados['iss'] ?? ''), '/') !== rtrim((string) $emissor, '/')
            || ! in_array(config("services.{$this->provedor}.client_id"), $aud, true)
            || ($dados['exp'] ?? 0) < $agora - 60
            || ($dados['iat'] ?? $agora) > $agora + 300
            || ! hash_equals($nonce, (string) ($dados['nonce'] ?? ''))) {
            throw new RuntimeException('Token de identidade não vale para este acesso.');
        }

        return $dados;
    }

    /** @return array<string, mixed> */
    private function descoberta(): array
    {
        $url = (string) config("services.{$this->provedor}.discovery");

        return Cache::remember('oidc:descoberta:' . md5($url), 3600, function () use ($url) {
            $doc = Http::timeout(15)->get($url)->json();
            if (! is_array($doc) || empty($doc['authorization_endpoint']) || empty($doc['token_endpoint']) || empty($doc['jwks_uri'])) {
                throw new RuntimeException('Não foi possível ler a configuração do provedor de entrada.');
            }

            return $doc;
        });
    }

    /** Chave RSA do JWKS do provedor; recarrega uma vez se o kid não estiver no cache (rotação). */
    private function chavePublica(?string $kid): string
    {
        foreach ([false, true] as $renovar) {
            $url = $this->descoberta()['jwks_uri'];
            if ($renovar) {
                Cache::forget('oidc:jwks:' . md5($url));
            }
            $jwks = Cache::remember('oidc:jwks:' . md5($url), 3600, fn () => Http::timeout(15)->get($url)->json('keys') ?? []);
            foreach ($jwks as $jwk) {
                if (($jwk['kty'] ?? '') === 'RSA' && ($kid === null || ($jwk['kid'] ?? null) === $kid)) {
                    return (string) PublicKeyLoader::load([
                        'n' => new BigInteger(self::deBase64url($jwk['n']), 256),
                        'e' => new BigInteger(self::deBase64url($jwk['e']), 256),
                    ])->toString('PKCS8');
                }
            }
        }
        throw new RuntimeException('Chave do provedor de entrada não encontrada.');
    }

    public static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function deBase64url(string $texto): string
    {
        return (string) base64_decode(strtr($texto, '-_', '+/') . str_repeat('=', (4 - strlen($texto) % 4) % 4));
    }
}
