<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uf extends Model
{
    protected $table = 'ufs';

    protected $fillable = ['nome', 'last_user'];

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'uf_id');
    }

    public function ultimoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user');
    }
}
