<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pasta extends Model
{
    use BelongsToUg;

    protected $table = 'ged_pastas';

    protected $fillable = [
        'ug_id',
        'nome',
        'descricao',
        'parent_id',
        'path',
        'criado_por',
        'ativo',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Pasta::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Pasta::class, 'parent_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'pasta_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
