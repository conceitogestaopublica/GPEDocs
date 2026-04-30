<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ug extends Model
{
    protected $table = 'ugs';

    protected $fillable = [
        'codigo',
        'legado_orgao_id',
        'nome',
        'cnpj',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'nivel_1_label',
        'nivel_2_label',
        'nivel_3_label',
        'ativo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function organograma(): HasMany
    {
        return $this->hasMany(UgOrganograma::class, 'ug_id');
    }

    public function organogramaRaiz(): HasMany
    {
        return $this->hasMany(UgOrganograma::class, 'ug_id')->whereNull('parent_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'ug_id');
    }

    /**
     * Devolve os labels dos 3 niveis no formato esperado pela UI.
     */
    public function labels(): array
    {
        return [
            1 => $this->nivel_1_label,
            2 => $this->nivel_2_label,
            3 => $this->nivel_3_label,
        ];
    }

    /**
     * Devolve os campos de endereco no formato compativel com herdar/proprio.
     */
    public function enderecoArray(): array
    {
        return [
            'cep'         => $this->cep,
            'logradouro'  => $this->logradouro,
            'numero'      => $this->numero,
            'complemento' => $this->complemento,
            'bairro'      => $this->bairro,
            'cidade'      => $this->cidade,
            'uf'          => $this->uf,
        ];
    }
}
