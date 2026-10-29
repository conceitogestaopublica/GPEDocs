<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\EnviarSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS (TR 4.11.1, 4.47.2): fila → gateway configurado em config/sms.php. Todo envio fica em
 * ged_sms com a situação. Número brasileiro vira +55DDDNÚMERO; inválido não sai.
 */
final class Sms
{
    public const MAX = 160;

    /** Enfileira (não segura a tela do servidor esperando o gateway). */
    public static function enfileirar(?string $numero, string $texto, ?string $refTipo = null, ?int $refId = null, ?int $usuarioId = null): bool
    {
        $numero = self::normalizar($numero);
        if (! $numero) {
            return false;
        }
        try {
            EnviarSms::dispatch($numero, self::encurtar($texto), $refTipo, $refId, $usuarioId)->afterCommit();

            return true;
        } catch (\Throwable $e) {
            Log::info('SMS não enfileirado', ['motivo' => $e->getMessage()]);

            return false;
        }
    }

    /** Envia agora (chamado pelo job). */
    public static function enviar(string $numero, string $texto, ?string $refTipo = null, ?int $refId = null, ?int $usuarioId = null): string
    {
        $status = 'simulado';
        $erro = null;
        if (config('sms.driver') === 'http' && config('sms.url')) {
            try {
                $c = config('sms.campos');
                $r = Http::timeout((int) config('sms.timeout', 10))->withToken((string) config('sms.token'))->acceptJson()
                    ->post((string) config('sms.url'), [$c['para'] => $numero, $c['texto'] => $texto, $c['remetente'] => config('sms.remetente')]);
                $status = $r->successful() ? 'enviado' : 'falhou';
                $erro = $r->successful() ? null : mb_substr("HTTP {$r->status()}: " . $r->body(), 0, 300);
            } catch (\Throwable $e) {
                [$status, $erro] = ['falhou', mb_substr($e->getMessage(), 0, 300)];
            }
        } else {
            Log::info("SMS (simulado) para {$numero}: {$texto}");
        }
        DB::table('ged_sms')->insert([
            'numero' => $numero, 'texto' => $texto, 'status' => $status, 'erro' => $erro,
            'referencia_tipo' => $refTipo, 'referencia_id' => $refId, 'usuario_id' => $usuarioId, 'created_at' => now(),
        ]);

        return $status;
    }

    /** "(31) 99888-7777" → "+5531998887777"; fixo ou incompleto → null. */
    public static function normalizar(?string $numero): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $numero);
        if (str_starts_with($d, '55') && strlen($d) >= 12) {
            $d = substr($d, 2);
        }
        $d = ltrim($d, '0');

        return preg_match('/^[1-9]{2}9\d{8}$/', $d) ? "+55{$d}" : null;
    }

    /** Cabe em um SMS; o link no fim nunca é cortado (corta-se o texto antes dele). */
    public static function encurtar(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto));
        if (mb_strlen($texto) <= self::MAX) {
            return $texto;
        }
        if (preg_match('/^(.*)\s(https?:\/\/\S+)$/u', $texto, $m) && mb_strlen($m[2]) < self::MAX - 20) {
            return rtrim(mb_substr($m[1], 0, self::MAX - mb_strlen($m[2]) - 2)) . '… ' . $m[2];
        }

        return mb_substr($texto, 0, self::MAX - 1) . '…';
    }
}
