<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OficioModelo extends Model
{
    use SoftDeletes;

    protected $table = 'proc_oficio_modelos';

    protected $fillable = [
        'ug_id',
        'nome',
        'categoria',
        'descricao',
        'conteudo',
        'ativo',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
