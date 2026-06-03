<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token de uso único para SSO jump landlord → tenant.
 *
 * O cru NUNCA é persistido — só o sha256 fica no banco. Quem emite recebe
 * o cru de volta apenas em memória, anexa à URL e descarta. Expira em 30s
 * por padrão e é marcado used_at na 1ª leitura (defesa contra replay).
 *
 * Ver docs/MULTITENANCY_AUTH_SPEC.md §4.3 e §4.4.
 */
class TenantSSOToken extends Model
{
    protected $connection = 'landlord';
    protected $table = 'tenant_sso_tokens';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    /**
     * Emite um token novo. Retorna [modelo persistido, token cru].
     * O cru deve ser repassado à URL e descartado em seguida — não persistir.
     */
    public static function emit(
        SuperAdmin $admin,
        Tenant     $tenant,
        string     $tenantUserEmail,
        ?string    $ip = null,
        int        $ttlSeconds = 30,
    ): array
    {
        $cru = Str::random(64);

        $token = static::create([
            'token_hash' => hash('sha256', $cru),
            'super_admin_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'tenant_user_email' => $tenantUserEmail,
            'ip_address' => $ip,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return [$token, $cru];
    }

    /** Localiza pelo token cru. Devolve null se não houver match. */
    public static function findByRaw(string $cru): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $cru))
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /** Marca o token como consumido. Idempotente: chama múltiplas vezes não muda used_at se já preenchido. */
    public function markUsed(): bool
    {
        if ($this->isUsed()) {
            return false;
        }
        return (bool) $this->update(['used_at' => now()]);
    }

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
