<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tramitacao extends Model
{
    protected $table = 'proc_tramitacoes';

    /** Sugestões de estágio que o setor marca na parte dele (TR 4.9.4); aceita outro texto. */
    public const ESTAGIOS = ['Em análise', 'Aguardando documentos', 'Em execução', 'Aguardando terceiros', 'Em diligência'];

    protected $fillable = [
        'processo_id',
        'tipo_etapa_id',
        'ordem',
        'setor_origem',
        'setor_destino',
        'destino_unidade_id',
        'remetente_id',
        'destinatario_id',
        'recebido_por',
        'status',
        'despacho',
        'parecer',
        'sla_horas',
        'prazo',
        'recebido_em',
        'despachado_em',
        'lida_em',
        'frente',
        'modo',
        'estagio',
        'resolvido_em',
        'resolvido_por',
    ];

    protected function casts(): array
    {
        return [
            'prazo'         => 'datetime',
            'recebido_em'   => 'datetime',
            'despachado_em' => 'datetime',
            'lida_em'       => 'datetime',
            'resolvido_em'  => 'datetime',
            'frente'        => 'integer',
        ];
    }

    public function destinoUnidade(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UgOrganograma::class, 'destino_unidade_id');
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function tipoEtapa(): BelongsTo
    {
        return $this->belongsTo(TipoEtapa::class);
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    public function recebedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recebido_por');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ProcessoAnexo::class, 'tramitacao_id');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(ProcessoComentario::class, 'tramitacao_id');
    }
}
