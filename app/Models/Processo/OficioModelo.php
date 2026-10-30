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
        'modulo',
        'unidade_id',
    ];

    /** Onde o modelo de texto pode ser usado (TR 4.16.11). */
    public const MODULOS = ['oficio' => 'Ofício', 'memorando' => 'Memorando', 'circular' => 'Circular', 'despacho' => 'Despacho', 'todos' => 'Todos'];

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

    /** Setor dono do modelo (vazio = modelo da administração). */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UgOrganograma::class, 'unidade_id');
    }
}
