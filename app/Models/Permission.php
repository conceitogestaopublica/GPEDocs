<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'ged_permissions';

    protected $fillable = [
        'nome',
        'descricao',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'ged_role_permissions', 'permission_id', 'role_id');
    }
}
