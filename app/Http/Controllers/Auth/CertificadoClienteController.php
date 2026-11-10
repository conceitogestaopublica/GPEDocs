<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\EntradaService;
use App\Services\Auth\UsuarioDoCertificado;
use App\Services\CertificadoService;
use App\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Entrada com certificado digital sem plugin: o navegador apresenta o e-CPF (A1 instalado ou A3 no
 * token, pelo driver do sistema) no próprio HTTPS, como certificado cliente. A posse da chave é
 * provada no aperto de mão do TLS, então não há desafio a assinar nem extensão a instalar.
 *
 *  1. entrar(): no endereço próprio (config icp_brasil.login_tls.url), o nginx pede o certificado
 *     e o repassa; aqui se confere validade, cadeia ICP-Brasil com LCR e o CPF no município de
 *     origem, e se grava um passe de uso único (60 s);
 *  2. passe(): de volta ao endereço do município, o passe abre a sessão como as demais entradas.
 *
 * O endereço próprio existe porque o navegador só pede certificado no início da conexão: pedir no
 * endereço do município faria o navegador perguntar a todo mundo, a cada acesso.
 */
class CertificadoClienteController extends Controller
{
    private const VALIDADE_PASSE = 60; // segundos

    public function entrar(Request $request, UsuarioDoCertificado $doCertificado, TenantContext $contexto): RedirectResponse
    {
        // Só no endereço configurado: em outro host o certificado (ou o cabeçalho) não vem do aperto de mão
        $endereco = parse_url((string) config('icp_brasil.login_tls.url'));
        abort_unless(isset($endereco['host']) && strtolower($request->getHost()) === strtolower($endereco['host'])
            && (int) $request->getPort() === (int) ($endereco['port'] ?? ($endereco['scheme'] === 'http' ? 80 : 443)), 404);

        $tenant = $this->tenantDeOrigem((string) $request->query('origem', ''));
        abort_unless($tenant, 404);
        $contexto->set($tenant);

        $certPem = $this->certificadoApresentado($request);
        $passe = Str::random(48);
        $dados = ['lembrar' => $request->boolean('lembrar')];
        if (! $certPem) {
            $dados['erro'] = 'Nenhum certificado foi apresentado. Conecte o token ou instale o certificado e escolha-o quando o navegador perguntar.';
        } else {
            $conferido = $doCertificado->conferir($certPem);
            if ($conferido['user']) {
                $dados['user_id'] = $conferido['user']->id;
            } else {
                $dados['erro'] = $conferido['erro'];
            }
        }
        Cache::put($this->chave($tenant->id, $passe), $dados, self::VALIDADE_PASSE);

        return redirect()->away($tenant->url('/login/certificado/passe/'.$passe));
    }

    public function passe(Request $request, string $passe, TenantContext $contexto, EntradaService $entrada): RedirectResponse
    {
        $tenant = $contexto->get();
        $dados = $tenant ? Cache::pull($this->chave($tenant->id, $passe)) : null;
        if (! is_array($dados)) {
            return redirect()->route('login')->withErrors(['login' => 'O tempo para entrar com o certificado acabou. Tente de novo.']);
        }
        if (! empty($dados['erro'])) {
            return redirect()->route('login')->withErrors(['login' => $dados['erro']]);
        }

        $user = User::find($dados['user_id'] ?? 0);
        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => 'Usuário não encontrado.']);
        }

        // O certificado já é o segundo fator
        return $entrada->concluir($request, $user, (bool) ($dados['lembrar'] ?? false), 'certificado', dispensa2fa: true);
    }

    /**
     * Só aceita voltar para o endereço de um município cadastrado: o destino do redirecionamento
     * sai do cadastro do tenant, nunca do parâmetro.
     */
    private function tenantDeOrigem(string $origem): ?Tenant
    {
        $origem = strtolower(trim($origem));
        if ($origem === '') {
            return null;
        }

        return Tenant::active()->get()->first(function (Tenant $t) use ($origem) {
            $u = parse_url($t->url());

            return strtolower(($u['host'] ?? '').(isset($u['port']) ? ':'.$u['port'] : '')) === $origem;
        });
    }

    /**
     * O nginx do GED repassa o certificado por fastcgi_param (variável de servidor, que o cliente não
     * consegue forjar). Atrás de um proxy que termina o TLS, vem no cabeçalho configurado — aceito só
     * de proxy confiável.
     */
    private function certificadoApresentado(Request $request): ?string
    {
        $bruto = (string) $request->server('SSL_CLIENT_CERT', '');
        $cabecalho = (string) config('icp_brasil.login_tls.cabecalho');
        if ($bruto === '' && $cabecalho !== '' && $request->isFromTrustedProxy()) {
            $bruto = (string) $request->header($cabecalho, '');
        }
        if ($bruto === '' || $bruto === '(null)') {
            return null;
        }

        return CertificadoService::normalizarPem(rawurldecode($bruto)) ?: null;
    }

    private function chave(int $tenantId, string $passe): string
    {
        return "login_certificado_tls:{$tenantId}:".hash('sha256', $passe);
    }
}
