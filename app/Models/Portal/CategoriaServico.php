<?php

declare(strict_types=1);

namespace App\Models\Portal;

use App\Models\Concerns\BelongsToUg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaServico extends Model
{
    use BelongsToUg;

    protected $table = 'portal_categorias_servicos';

    protected $fillable = [
        'ug_id',
        'nome',
        'slug',
        'icone',
        'cor',
        'descricao',
        'area',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class, 'categoria_id');
    }

    public function servicosPublicados(): HasMany
    {
        return $this->hasMany(Servico::class, 'categoria_id')->where('publicado', true);
    }

    /**
     * Área de atendimento do Portal do Cidadão em que a categoria aparece. A
     * área "contribuinte" só é exibida no município com tributário integrado;
     * "servidor" e "transparencia", com o gpe2.
     */
    public const AREAS = [
        'contribuinte'  => 'Atendimento ao Contribuinte',
        'protocolo'     => 'Protocolo e Ouvidoria',
        'servidor'      => 'Servidor Público',
        'transparencia' => 'Transparência',
    ];
}
