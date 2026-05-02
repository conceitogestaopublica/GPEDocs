<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Documento;
use App\Models\SistemaIntegrado;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centraliza envio de webhooks para sistemas externos. Sempre persiste
 * em ged_webhook_logs (sucesso ou falha), aplica HMAC-SHA256 e respeita
 * a configuracao de eventos do sistema.
 *
 * Eventos suportados:
 *   - assinatura.individual         (cada signatario individual assinou)
 *   - assinatura.recusada           (signatario recusou)
 *   - assinatura.todas_concluidas   (todas concluidas — disparado uma vez)
 *
 * Uso:
 *   app(WebhookDispatcher::class)->disparar($documento, 'assinatura.todas_concluidas', $payload);
 */
class WebhookDispatcher
{
    /**
     * Envia o webhook + persiste log. Retorna true se deu 2xx, false caso contrario.
     */
    public function disparar(Documento $documento, string $evento, array $payloadExtra = []): bool
    {
        if (! $documento->sistema_origem || ! $documento->callback_url) {
            return false; // documento nao veio de sistema externo, sem callback
        }

        $sistema = SistemaIntegrado::where('codigo', $documento->sistema_origem)->first();
        if (! $sistema || ! $sistema->ativo) {
            Log::warning('Webhook nao enviado: sistema nao encontrado/ativo', [
                'sistema'   => $documento->sistema_origem,
                'documento' => $documento->id,
                'evento'    => $evento,
            ]);
            return false;
        }

        // Filtragem por eventos: sistema escolhe o que receber
        if (! $sistema->escuta($evento)) {
            return false;
        }

        $payload = array_merge([
            'evento'         => $evento,
            'sistema_origem' => $documento->sistema_origem,
            'numero_externo' => $documento->numero_externo,
            'documento_id'   => $documento->id,
            'enviado_em'     => now()->toIso8601String(),
            'visualizacao_url' => url("/documentos/{$documento->id}"),
            'pdf_assinado_url' => url("/documentos/{$documento->id}/download"),
        ], $payloadExtra);

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = $sistema->webhook_secret ? $sistema->assinarPayload($payloadJson) : null;

        $log = WebhookLog::create([
            'sistema_origem'   => $sistema->codigo,
            'documento_id'     => $documento->id,
            'evento'           => $evento,
            'callback_url'     => $documento->callback_url,
            'payload'          => $payload,
            'signature_header' => $signature,
            'enviado_em'       => now(),
            'tentativas'       => 1,
        ]);

        $inicio = microtime(true);

        try {
            $resp = Http::timeout(10)
                ->retry(2, 500, throw: false)
                ->withHeaders(array_filter([
                    'Content-Type'        => 'application/json',
                    'X-GpeDocs-Signature' => $signature,
                    'X-GpeDocs-Sistema'   => $sistema->codigo,
                    'X-GpeDocs-Evento'    => $evento,
                ]))
                ->withBody($payloadJson, 'application/json')
                ->post($documento->callback_url);

            $duracao = (int) ((microtime(true) - $inicio) * 1000);
            $sucesso = $resp->successful();

            $log->update([
                'sucesso'       => $sucesso,
                'http_status'   => $resp->status(),
                'response_body' => mb_substr((string) $resp->body(), 0, 2000),
                'duracao_ms'    => $duracao,
            ]);

            // Marca o documento como callback_executado quando o evento e o final
            if ($sucesso && $evento === 'assinatura.todas_concluidas') {
                $documento->update([
                    'callback_executado'    => true,
                    'callback_executado_em' => now(),
                ]);
            }

            return $sucesso;
        } catch (Throwable $e) {
            $log->update([
                'sucesso'    => false,
                'erro'       => $e->getMessage(),
                'duracao_ms' => (int) ((microtime(true) - $inicio) * 1000),
            ]);
            Log::warning('Webhook falhou', [
                'sistema'   => $sistema->codigo,
                'documento' => $documento->id,
                'evento'    => $evento,
                'erro'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reenvia o webhook de um log existente (manual). Util quando o
     * cliente perdeu o callback original. Cria um novo log.
     */
    public function reenviar(WebhookLog $log): bool
    {
        $documento = $log->documento;
        if (! $documento) {
            return false;
        }

        // Remove campos auto-gerados antes de reenviar (sao recriados)
        $payloadExtra = collect($log->payload ?? [])
            ->except(['evento', 'sistema_origem', 'numero_externo', 'documento_id', 'enviado_em',
                      'visualizacao_url', 'pdf_assinado_url'])
            ->toArray();

        return $this->disparar($documento, $log->evento, $payloadExtra);
    }
}
