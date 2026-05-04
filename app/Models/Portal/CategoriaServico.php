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
}
