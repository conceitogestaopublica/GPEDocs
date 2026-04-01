<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $table = 'ged_tags';

    protected $fillable = [
        'nome',
        'cor',
    ];

    public function documentos(): BelongsToMany
    {
        return $this->belongsToMany(Documento::class, 'ged_documento_tags', 'tag_id', 'documento_id');
    }
}
