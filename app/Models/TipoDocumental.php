<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumental extends Model
{
    protected $table = 'ged_tipos_documentais';

    protected $fillable = [
        'nome',
        'descricao',
        'schema_metadados',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'schema_metadados' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'tipo_documental_id');
    }
}
