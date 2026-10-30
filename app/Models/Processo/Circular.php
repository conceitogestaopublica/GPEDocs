<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\Concerns\BelongsToUg;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Circular extends Model
{
    use SoftDeletes, BelongsToUg, \App\Models\Concerns\Classificavel, \App\Models\Concerns\TemConteudoComVariaveis;

    /** Variáveis do editor (TR 4.16.10). */
    public const ROTULOS_VARIAVEIS = ['numero' => 'Número da circular', 'data' => 'Data por extenso', 'assunto' => 'Assunto',
        'setor' => 'Setor de origem', 'remetente' => 'Remetente'];

    public const MODULO_CLASSIFICACAO = 'circular';

    protected $table = 'proc_circulares';

    protected $fillable = [
        'ug_id',
        'numero',
        'assunto',
        'conteudo',
        'remetente_id',
        'digitador_id',
        'contrassenha_id',
        'setor_origem',
        'destino_tipo',
        'destino_setores',
        'destino_unidades',
        'urgente',
        'respostas',
        'status',
        'enviado_em',
        'arquivado_em',
        'data_arquivamento_auto',
        'qr_code_token',
        'documento_id',
    ];

    protected function casts(): array
    {
        return [
            'destino_setores'       => 'array',
            'destino_unidades'      => 'array',
            'urgente'               => 'boolean',
            'enviado_em'            => 'datetime',
            'arquivado_em'          => 'datetime',
            'data_arquivamento_auto'=> 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Circular $circular) {
            if (empty($circular->qr_code_token)) {
                $circular->qr_code_token = (string) Str::uuid();
            }
        });
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    /** Quem digitou, quando o documento saiu em nome do remetente por contrassenha (TR 4.18). */
    public function digitador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digitador_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(CircularDestinatario::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(CircularAnexo::class);
    }

    /** Respostas (TR 4.46.6): abertas, todos veem; restritas, só o remetente e quem respondeu. */
    public function respostasLista(): HasMany
    {
        return $this->hasMany(CircularResposta::class)->orderBy('created_at');
    }

    /** @return array<string, string> */
    public function variaveis(): array
    {
        return [
            'numero'    => (string) $this->numero,
            'data'      => \App\Support\Variaveis::dataPorExtenso($this->enviado_em ?? $this->created_at),
            'assunto'   => (string) $this->assunto,
            'setor'     => (string) $this->setor_origem,
            'remetente' => (string) $this->remetente?->name,
        ];
    }
}
