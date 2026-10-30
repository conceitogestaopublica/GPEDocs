<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

use App\Models\IntegracaoTributaria;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Base dos adaptadores que falam HTTP: endereço, autenticação (Bearer, header próprio, Basic ou
 * nenhuma), tempo limite e erros traduzidos para a tela.
 */
abstract class AdaptadorHttp implements SistemaTributario
{
    public function __construct(protected readonly IntegracaoTributaria $cfg)
    {
    }

    public function nome(): string
    {
        return $this->cfg->nome_sistema ?: 'Sistema tributário';
    }

    public function oferece(string $recurso): bool
    {
        return in_array($recurso, $this->recursos(), true);
    }

    protected function http(): PendingRequest
    {
        $h = Http::baseUrl(rtrim((string) $this->cfg->base_url, '/'))->acceptJson()->timeout(max(3, (int) $this->cfg->timeout))
            // Headers fixos do sistema (ex.: x-tenant-subdomain), em config["headers"]
            ->withHeaders(array_map('strval', (array) ($this->cfg->config['headers'] ?? [])));
        $cred = (string) $this->cfg->credencial;

        return match ($this->cfg->auth_tipo) {
            'bearer' => $cred !== '' ? $h->withToken($cred) : $h,
            'header' => $cred !== '' ? $h->withHeaders([$this->cfg->auth_header ?: 'X-Api-Key' => $cred]) : $h,
            'basic' => str_contains($cred, ':') ? $h->withBasicAuth(...explode(':', $cred, 2)) : $h,
            default => $h,
        };
    }

    /** @param array<string, mixed> $corpo */
    protected function chamar(string $metodo, string $caminho, array $corpo = [], array $query = []): Response
    {
        try {
            $r = match (strtoupper($metodo)) {
                'GET' => $this->http()->get($caminho, $query + $corpo),
                'POST' => $this->http()->withQueryParameters($query)->post($caminho, $corpo),
                'PUT' => $this->http()->withQueryParameters($query)->put($caminho, $corpo),
                'PATCH' => $this->http()->withQueryParameters($query)->patch($caminho, $corpo),
                default => throw new FalhaTributario("Método HTTP não suportado: {$metodo}"),
            };
        } catch (FalhaTributario $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FalhaTributario("{$this->nome()} não respondeu: " . mb_substr($e->getMessage(), 0, 200));
        }
        if ($r->status() === 404) {
            return $r;
        }
        if ($r->failed()) {
            $msg = $r->json('message') ?? $r->json('erro') ?? $r->json('error') ?? mb_substr($r->body(), 0, 200);
            throw new FalhaTributario("{$this->nome()} recusou ({$r->status()}): " . (is_array($msg) ? implode('; ', $msg) : $msg));
        }

        return $r;
    }

    public function baixarPdf(string $url): string
    {
        try {
            $r = str_starts_with($url, 'http') ? $this->http()->baseUrl('')->get($url) : $this->http()->get($url);
        } catch (Throwable $e) {
            throw new FalhaTributario('Não foi possível baixar o PDF: ' . mb_substr($e->getMessage(), 0, 200));
        }
        if ($r->failed() || ! str_starts_with($r->body(), '%PDF')) {
            throw new FalhaTributario("O PDF não veio do {$this->nome()} ({$r->status()}).");
        }

        return $r->body();
    }
}