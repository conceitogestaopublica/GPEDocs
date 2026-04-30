<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UgOrganograma extends Model
{
    protected $table = 'ug_organograma';

    protected $fillable = [
        'ug_id',
        'parent_id',
        'nivel',
        'codigo',
        'legado_id',
        'legado_tipo',
        'nome',
        'ativo',
        'dt_inicio',
        'dt_encerramento',
        'tipo_orgao',
        'tipo_fundo',
        'codigo_tce',
        'suprimir_tce',
        'responsavel_id',
        'protocolo_externo',
        'endereco_proprio',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
    ];

    protected function casts(): array
    {
        return [
            'nivel'             => 'integer',
            'ativo'             => 'boolean',
            'dt_inicio'         => 'date',
            'dt_encerramento'   => 'date',
            'suprimir_tce'      => 'boolean',
            'protocolo_externo' => 'boolean',
            'endereco_proprio'  => 'boolean',
        ];
    }

    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class, 'ug_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function filhosRecursivos(): HasMany
    {
        return $this->filhos()->with('filhosRecursivos');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'unidade_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Endereco efetivo deste no: o proprio se endereco_proprio = true,
     * caso contrario o da UG associada.
     */
    public function enderecoEfetivo(): array
    {
        if ($this->endereco_proprio) {
            return [
                'origem'      => 'proprio',
                'cep'         => $this->cep,
                'logradouro'  => $this->logradouro,
                'numero'      => $this->numero,
                'complemento' => $this->complemento,
                'bairro'      => $this->bairro,
                'cidade'      => $this->cidade,
                'uf'          => $this->uf,
            ];
        }

        return ['origem' => 'herdado'] + ($this->ug?->enderecoArray() ?? []);
    }
}
