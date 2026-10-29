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
    use SoftDeletes, BelongsToUg, \App\Models\Concerns\Classificavel;

    public const MODULO_CLASSIFICACAO = 'processo';

    protected static function booted(): void
    {
        // Requerente com CPF/CNPJ entra na base de contatos (TR 4.21)
        static::created(function (Processo $p) {
            if ($p->contato_id || ! $p->requerente_cpf) {
                return;
            }
            try {
                if ($id = \App\Support\Contatos::doRequerente($p->requerente_cpf, $p->requerente_nome, $p->requerente_email, $p->requerente_telefone)) {
                    $p->contato_id = $id;
                    $p->saveQuietly();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Requerente não entrou nos contatos', ['processo' => $p->id, 'erro' => $e->getMessage()]);
            }
        });
    }

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
        'decisao',
        'solicitacao_assinatura_id',
        'documento_decisao_id',
        'latitude',
        'longitude',
        'local_endereco',
        'reaberturas',
    ];

    protected function casts(): array
    {
        return [
            'dados_formulario' => 'array',
            'concluido_em' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
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
        // Na ordem que quem trata o processo definiu; os sem ordem, na ordem de chegada
        return $this->hasMany(ProcessoAnexo::class)->orderByRaw('ordem is null')->orderBy('ordem')->orderBy('id');
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

    /** Variáveis do despacho no editor (TR 4.16.10) — trocadas ao despachar: o despacho é o registro daquele momento. */
    public const ROTULOS_VARIAVEIS_DESPACHO = ['numero' => 'Número do processo', 'assunto' => 'Assunto', 'requerente' => 'Requerente',
        'setor' => 'Setor de quem despacha', 'servidor' => 'Quem despacha', 'data' => 'Data por extenso'];

    /** @return array<string, string> */
    public function variaveisDespacho(?string $setor = null): array
    {
        return [
            'numero'     => (string) $this->numero_protocolo,
            'assunto'    => (string) $this->assunto,
            'requerente' => (string) $this->requerente_nome,
            'setor'      => (string) $setor,
            'servidor'   => (string) auth()->user()?->name,
            'data'       => \App\Support\Variaveis::dataPorExtenso(now()),
        ];
    }
}