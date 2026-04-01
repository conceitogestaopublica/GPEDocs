<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metadado extends Model
{
    protected $table = 'ged_metadados';

    protected $fillable = [
        'documento_id',
        'chave',
        'valor',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }
}
