<?php

declare(strict_types=1);

namespace App\Models\Processo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tipo de infração do PAD: gravidade, base legal e o setor que recebe o processo (TR 4.40.2). */
class PadInfracao extends Model
{
    public const GRAVIDADES = ['leve' => 'Leve', 'media' => 'Média', 'grave' => 'Grave'];

    protected $table = 'proc_pad_infracoes';

    protected $fillable = ['nome', 'gravidade', 'base_legal', 'unidade_id', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UgOrganograma::class, 'unidade_id');
    }
}