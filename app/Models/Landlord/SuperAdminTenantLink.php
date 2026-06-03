<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo explícito super-admin × usuário de um tenant.
 *
 * O email em `tenant_user_email` deve casar com `tenant.usuario.email` no
 * banco do tenant correspondente — a unicidade é garantida do lado tenant.
 *
 * Quando há múltiplos vínculos para o mesmo par (super_admin, tenant), o
 * que está marcado `default=true` é o utilizado pelo SSO jump quando não
 * for especificado explicitamente.
 */
class SuperAdminTenantLink extends Model
{
    protected $connection = 'landlord';
    protected $table = 'super_admin_tenant_links';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'default'        => 'boolean',
            'super_admin_id' => 'integer',
            'tenant_id'      => 'integer',
        ];
    }

    public function scopeDefault(Builder $q): Builder
    {
        return $q->where('default', true);
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