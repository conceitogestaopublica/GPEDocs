<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorandoTramitacao extends Model
{
    protected $table = 'proc_memorando_tramitacoes';

    protected $fillable = [
        'memorando_id',
        'tramite_origem_id',
        'origem_usuario_id',
        'origem_unidade_id',
        'destino_usuario_id',
        'destino_unidade_id',
        'parecer',
        'em_uso',
        'finalizado',
        'despachado_em',
        'recebido_em',
    ];

    protected function casts(): array
    {
        return [
            'em_uso'         => 'boolean',
            'finalizado'     => 'boolean',
            'despachado_em'  => 'datetime',
            'recebido_em'    => 'datetime',
        ];
    }

    public function memorando(): BelongsTo
    {
        return $this->belongsTo(Memorando::class);
    }

    public function origemUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'origem_usuario_id');
    }

    public function origemUnidade(): BelongsTo
    {
        return $this->belongsTo(UgOrganograma::class, 'origem_unidade_id');
    }

    public function destinoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destino_usuario_id');
    }

    public function destinoUnidade(): BelongsTo
    {
        return $this->belongsTo(UgOrganograma::class, 'destino_unidade_id');
    }
}
