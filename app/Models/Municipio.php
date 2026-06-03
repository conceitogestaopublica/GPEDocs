<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipio extends Model
{
    protected $table = 'municipios';

    protected $fillable = ['nome', 'uf_id', 'ibge_id', 'last_user'];

    public function uf(): BelongsTo
    {
        return $this->belongsTo(Uf::class, 'uf_id');
    }

    public function bairros(): HasMany
    {
        return $this->hasMany(Bairro::class, 'municipio_id');
    }

    public function ultimoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user');
    }
}
