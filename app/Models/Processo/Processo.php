<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\Concerns\BelongsToUg;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Processo extends Model
{
    use SoftDeletes, BelongsToUg;

    protected $table = 'proc_processos';

    protected $fillable = [
        'ug_id',
        'numero_protocolo',
        'tipo_processo_id',
        'assunto',
        'descricao',
        'dados_formulario',
        'requerente_nome',
        'requerente_cpf',
        'requerente_email',
        'requerente_telefone',
        'setor_origem',
        'etapa_atual_id',
        'status',
        'prioridade',
        'aberto_por',
        'concluido_por',
        'concluido_em',
        'observacao_conclusao',
    ];

    protected function casts(): array
    {
        return [
            'dados_formulario' => 'array',
            'concluido_em' => 'datetime',
        ];
    }

    public function tipoProcesso(): BelongsTo
    {
        return $this->belongsTo(TipoProcesso::class);
    }

    public function tramitacoes(): HasMany
    {
        return $this->hasMany(Tramitacao::class)->orderBy('ordem');
    }

    public function etapaAtual(): BelongsTo
    {
        return $this->belongsTo(Tramitacao::class, 'etapa_atual_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ProcessoAnexo::class);
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(ProcessoComentario::class);
    }

    public function historico(): HasMany
    {
        return $this->hasMany(ProcessoHistorico::class)->orderBy('created_at');
    }

    public function abertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberto_por');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por');
    }
}
