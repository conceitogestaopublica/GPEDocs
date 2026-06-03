<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ug extends Model
{
    protected $table = 'ugs';

    protected $fillable = [
        'codigo',
        'portal_slug',
        'legado_orgao_id',
        'nome',
        'cnpj',
        'logradouro_id',
        'numero',
        'complemento',
        'nivel_1_label',
        'nivel_2_label',
        'nivel_3_label',
        'ativo',
        'observacoes',
        'brasao_path',
        'banner_path',
        'banner_titulo',
        'banner_subtitulo',
        'banner_link_url',
        'banner_link_label',
        'banner_ativo',
        'telefone',
        'email_institucional',
        'site',
    ];

    protected function casts(): array
    {
        return [
            'ativo'        => 'boolean',
            'banner_ativo' => 'boolean',
        ];
    }

    public function logradouro(): BelongsTo
    {
        return $this->belongsTo(Logradouro::class, 'logradouro_id');
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
     * Vinculo multi-tenant: usuarios que tem acesso a esta UG.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_ugs', 'ug_id', 'user_id')
            ->withPivot('principal')
            ->withTimestamps();
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
     * Devolve os campos de endereco no formato flat (compativel com EnderecoForm
     * e com os imports/exports). Carrega a cadeia logradouro->municipio->uf
     * sob demanda.
     */
    public function enderecoArray(): array
    {
        $logradouro = $this->logradouro;
        if ($logradouro) {
            $logradouro->loadMissing('bairro.municipio.uf');
        }

        $bairro    = $logradouro?->bairro;
        $municipio = $bairro?->municipio;

        return [
            'cep'         => $logradouro?->cep,
            'logradouro'  => $logradouro?->nome,
            'numero'      => $this->numero,
            'complemento' => $this->complemento,
            'bairro'      => $bairro?->nome,
            'cidade'      => $municipio?->nome,
            'uf'          => $municipio?->uf?->nome,
        ];
    }
}