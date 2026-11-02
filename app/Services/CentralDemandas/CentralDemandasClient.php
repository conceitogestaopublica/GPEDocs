<?php

declare(strict_types=1);

namespace App\Services\CentralDemandas;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cliente da API da Central de Demandas da Conceito (contrato em GpeDemandas/docs/integracao-ged.md).
 * Credenciais por tenant em sup_config (token e segredo cifrados). Só é chamado pela fila
 * (Jobs/EnviarParaCentral) e pelo botão "Testar ligação" da configuração.
 */
final class CentralDemandasClient
{
    public function __construct(private string $url, private string $token) {}

    /** Integração ligada e completa neste tenant? */
    public static function ativa(): bool
    {
        $c = DB::table('sup_config')->first(['central_ativo', 'central_url', 'central_token', 'central_segredo']);

        return (bool) ($c?->central_ativo && $c->central_url && $c->central_token && $c->central_segredo);
    }

    public static function doTenant(): self
    {
        $c = DB::table('sup_config')->first(['central_url', 'central_token']);
        if (! $c?->central_url || ! $c->central_token) {
            throw new RuntimeException('Integração com a Central de Demandas não configurada.');
        }

        return new self($c->central_url, Crypt::decryptString($c->central_token));
    }

    /** Segredo que assina os eventos vindos da Central (null = não configurado ou ilegível). */
    public static function segredo(): ?string
    {
        $s = DB::table('sup_config')->value('central_segredo');
        try {
            return $s ? Crypt::decryptString($s) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** POST na API; lança exceção em resposta fora de 2xx (o job tenta de novo). */
    public function post(string $caminho, array $corpo): array
    {
        $r = $this->requisicao()->post(rtrim($this->url, '/') . '/' . ltrim($caminho, '/'), $corpo);
        if (! $r->successful()) {
            throw new RuntimeException("Central respondeu {$r->status()} em {$caminho}: " . mb_substr($r->body(), 0, 300));
        }

        return $r->json() ?? [];
    }

    /**
     * Testa token e endereço sem criar nada: POST /demandas vazio. Token certo → 422 (campos
     * obrigatórios); token errado ou integração desativada → 401.
     */
    public function testar(): array
    {
        try {
            $r = $this->requisicao()->post(rtrim($this->url, '/') . '/demandas', []);
        } catch (Throwable $e) {
            return ['ok' => false, 'mensagem' => 'Sem resposta da Central: ' . mb_substr($e->getMessage(), 0, 200)];
        }

        return match (true) {
            $r->status() === 422 => ['ok' => true, 'mensagem' => 'Ligação com a Central funcionando: token aceito.'],
            $r->status() === 401 => ['ok' => false, 'mensagem' => 'A Central recusou o token (401): confira o token ou se a integração está ativa na Central.'],
            $r->status() === 404 => ['ok' => false, 'mensagem' => 'Endereço não encontrado (404): confira o endereço da API (termina em /api/v1).'],
            default => ['ok' => false, 'mensagem' => "Resposta inesperada da Central ({$r->status()})."],
        };
    }

    private function requisicao()
    {
        return Http::withToken($this->token)->acceptJson()->asJson()->timeout(10);
    }
}
