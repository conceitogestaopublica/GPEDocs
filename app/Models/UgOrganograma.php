<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TextoNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UgOrganograma extends Model
{
    protected $table = 'ug_organograma';

    /**
     * Padrao de nome para setores (nos folha): sentence case.
     * Aplicado no boot via evento `saving`. Orgaos/unidades pai
     * preservam a capitalizacao original.
     */
    protected static function booted(): void
    {
        static::saving(function (self $no) {
            if (! $no->isDirty('nome') || $no->nome === null) return;

            $ehFolha = $no->exists
                ? ! static::where('parent_id', $no->id)->exists()
                : true; // criacao: assume folha (novo no nao tem filhos ainda)

            if ($ehFolha) {
                $no->nome = TextoNormalizer::sentenceCase($no->nome);
            }
        });
    }

    protected $fillable = [
        'ug_id',
        'parent_id',
        'nivel',
        'codigo',
        'legado_id',
        'legado_tipo',
        'nome',
        'ativo',
        'dt_inicio',
        'dt_encerramento',
        'tipo_orgao',
        'tipo_fundo',
        'codigo_tce',
        'suprimir_tce',
        'responsavel_id',
        'protocolo_externo',
        'endereco_proprio',
        'logradouro_id',
        'numero',
        'complemento',
    ];

    protected function casts(): array
    {
        return [
            'nivel'             => 'integer',
            'ativo'             => 'boolean',
            'dt_inicio'         => 'date',
            'dt_encerramento'   => 'date',
            'suprimir_tce'      => 'boolean',
            'protocolo_externo' => 'boolean',
            'endereco_proprio'  => 'boolean',
        ];
    }

    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class, 'ug_id');
    }

    public function logradouro(): BelongsTo
    {
        return $this->belongsTo(Logradouro::class, 'logradouro_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function filhosRecursivos(): HasMany
    {
        return $this->filhos()->with('filhosRecursivos');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'unidade_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Endereco proprio do no (sem heranca) no formato flat. Util quando
     * `endereco_proprio = true` ou quando o form precisa preencher os campos
     * mesmo que herdados (entao o caller usa enderecoEfetivo()).
     */
    public function enderecoProprioArray(): array
    {
        $logradouro = $this->logradouro;
        if ($logradouro) {
            $logradouro->loadMissing('bairro.municipio.uf');
        }

        $bairro    = $logradouro?->bairro;
        $municipio = $bairro?->municipio;

        return [
            'cep'         => $logradouro?->cep,
            'logradouro'  => $logradouro?->nome,
            'numero'      => $this->numero,
            'complemento' => $this->complemento,
            'bairro'      => $bairro?->nome,
            'cidade'      => $municipio?->nome,
            'uf'          => $municipio?->uf?->nome,
        ];
    }

    /**
     * Endereco efetivo deste no: o proprio se endereco_proprio = true,
     * caso contrario o da UG associada.
     */
    public function enderecoEfetivo(): array
    {
        if ($this->endereco_proprio) {
            return ['origem' => 'proprio'] + $this->enderecoProprioArray();
        }

        return ['origem' => 'herdado'] + ($this->ug?->enderecoArray() ?? []);
    }
}