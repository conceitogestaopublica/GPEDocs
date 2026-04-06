<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoEtapa extends Model
{
    protected $table = 'proc_tipo_etapas';

    protected $fillable = [
        'tipo_processo_id',
        'nome',
        'descricao',
        'ordem',
        'tipo',
        'setor_destino',
        'responsavel_id',
        'sla_horas',
        'template_texto',
        'obrigatorio',
    ];

    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
        ];
    }

    public function tipoProcesso(): BelongsTo
    {
        return $this->belongsTo(TipoProcesso::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }
}
