<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bairro extends Model
{
    protected $table = 'bairro';

    protected $fillable = ['nome', 'municipio_id', 'last_user'];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    public function logradouros(): HasMany
    {
        return $this->hasMany(Logradouro::class, 'bairro_id');
    }

    public function ultimoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user');
    }
}
