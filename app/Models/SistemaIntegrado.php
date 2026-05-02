<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sistema externo autorizado a integrar com o GPE Docs (envio de documentos
 * para assinatura + arquivamento). Cada sistema tem um API token unico que
 * e gerado no cadastro e exibido apenas UMA VEZ — depois e armazenado como
 * sha256 hash. O cliente envia em todas as requests no header:
 *
 *   Authorization: Bearer {token}
 */
class SistemaIntegrado extends Model
{
    protected $table = 'ged_sistemas_integrados';

    protected $fillable = [
        'codigo', 'nome', 'descricao',
        'api_token_hash', 'api_token_prefix',
        'webhook_secret',
        'ativo', 'ultimo_uso_em',
    ];

    protected $hidden = ['api_token_hash', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'ativo'         => 'boolean',
            'ultimo_uso_em' => 'datetime',
        ];
    }

    /**
     * Gera token aleatorio de 64 chars. Retorna o token PURO (mostre uma vez)
     * e ja armazena hash + prefix neste model. Nao salva — caller que faz save.
     */
    public function gerarToken(): string
    {
        $tokenPuro = Str::random(64);
        $this->api_token_hash = hash('sha256', $tokenPuro);
        $this->api_token_prefix = substr($tokenPuro, 0, 8);
        // Gera tambem secret HMAC (usado pra assinar payload do webhook).
        // Diferente do API token: secret e armazenado em CLARO (nao hash) porque
        // o servidor precisa dele para gerar a assinatura. Cliente armazena copia.
        if (! $this->webhook_secret) {
            $this->webhook_secret = Str::random(48);
        }
        return $tokenPuro;
    }

    /**
     * Regenera apenas o webhook_secret (independente do API token).
     */
    public function regenerarWebhookSecret(): string
    {
        $this->webhook_secret = Str::random(48);
        return $this->webhook_secret;
    }

    /**
     * Calcula assinatura HMAC-SHA256 de um payload JSON, no formato
     * "sha256=<hex>". Cliente valida fazendo o mesmo calculo.
     */
    public function assinarPayload(string $payloadJson): string
    {
        return 'sha256=' . hash_hmac('sha256', $payloadJson, $this->webhook_secret ?? '');
    }

    /**
     * Localiza o sistema pelo token enviado no header. Retorna null se nao
     * encontrado, expirado ou inativo. Atualiza `ultimo_uso_em` em caso de match.
     */
    public static function autenticar(?string $tokenPuro): ?self
    {
        if (! $tokenPuro) {
            return null;
        }

        $sistema = self::where('api_token_hash', hash('sha256', $tokenPuro))
            ->where('ativo', true)
            ->first();

        if ($sistema) {
            $sistema->update(['ultimo_uso_em' => now()]);
        }

        return $sistema;
    }

    /**
     * Mascara o token pra exibicao em UI: "abcd1234***...***" — so prefixo.
     */
    public function getTokenMascaradoAttribute(): string
    {
        return $this->api_token_prefix . '••••••••••••';
    }
}
