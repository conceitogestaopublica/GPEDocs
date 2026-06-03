<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Super-admin do sistema — vive no banco landlord.
 *
 * Diferente do flag `isSuperAdmin` na tabela `usuario` (legacy, por tenant),
 * esta entidade é transversal: 1 super-admin pode ter zero ou vários
 * espelhos (`usuario`) em vários tenants via `super_admin_tenant_links`.
 *
 * Não é Authenticatable de propósito — o fluxo de login do super-admin
 * grava chaves cruas na sessão (super_admin_id, super_admin_email), não
 * passa por Auth::login(). Ver docs/MULTITENANCY_AUTH_SPEC.md §4.2.
 */
class SuperAdmin extends Model
{
    protected $connection = 'landlord';
    protected $table = 'super_admins';
    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'          => 'hashed',
            'email_verified_at' => 'datetime',
            'ultimo_acesso'     => 'datetime',
            'active'            => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function tenantLinks(): HasMany
    {
        return $this->hasMany(SuperAdminTenantLink::class);
    }

    public function ssoTokens(): HasMany
    {
        return $this->hasMany(TenantSSOToken::class);
    }
}