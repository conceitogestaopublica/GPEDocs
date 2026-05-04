<?php

declare(strict_types=1);

namespace App\Models\Portal;

use App\Models\Ug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    protected $table = 'portal_banners';

    protected $fillable = [
        'ug_id',
        'imagem_path',
        'titulo',
        'subtitulo',
        'link_url',
        'link_label',
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

    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class, 'ug_id');
    }
}
