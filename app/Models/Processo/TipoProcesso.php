<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoProcesso extends Model
{
    protected $table = 'proc_tipos_processo';

    protected $fillable = [
        'nome',
        'descricao',
        'sigla',
        'categoria',
        'schema_formulario',
        'templates_despacho',
        'sla_padrao_horas',
        'ativo',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'schema_formulario' => 'array',
            'templates_despacho' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(TipoEtapa::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function processos(): HasMany
    {
        return $this->hasMany(Processo::class);
    }
}
