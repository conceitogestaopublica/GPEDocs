<?php

declare(strict_types=1);

namespace App\Models\Processo;

use Illuminate\Database\Eloquent\Model;

/** Tipo de ato oficial (TR 4.42.2, 4.42.3, 4.42.7): numeração, modelo .docx, quem elabora e quem assina. */
class AtoTipo extends Model
{
    public const NUMERACOES = ['tipo' => 'Por tipo (cada tipo tem a sua sequência no ano)', 'geral' => 'Geral (uma sequência para todos os atos do ano)', 'manual' => 'Manual (informada por quem elabora)'];

    protected $table = 'proc_ato_tipos';

    protected $fillable = ['nome', 'sigla', 'numeracao', 'modelo_path', 'modelo_nome', 'setores', 'signatarios', 'publico', 'ativo'];

    protected function casts(): array
    {
        return ['setores' => 'array', 'signatarios' => 'array', 'publico' => 'boolean', 'ativo' => 'boolean'];
    }
}