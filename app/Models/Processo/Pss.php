<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\Concerns\BelongsToUg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Processo seletivo simplificado (TR 4.41): edital, período, cargos e o assunto da ficha. */
class Pss extends Model
{
    use BelongsToUg;

    protected $table = 'proc_pss';

    protected $fillable = ['ug_id', 'titulo', 'edital_numero', 'descricao', 'requisitos', 'cargos', 'tipo_processo_id',
        'inscricoes_inicio', 'inscricoes_fim', 'publicado', 'criado_por'];

    protected function casts(): array
    {
        return ['cargos' => 'array', 'inscricoes_inicio' => 'datetime', 'inscricoes_fim' => 'datetime', 'publicado' => 'boolean'];
    }

    /** rascunho | agendado | aberto | encerrado */
    public function situacao(): string
    {
        return match (true) {
            ! $this->publicado => 'rascunho',
            now()->lt($this->inscricoes_inicio) => 'agendado',
            now()->gt($this->inscricoes_fim) => 'encerrado',
            default => 'aberto',
        };
    }

    public function tipoProcesso(): BelongsTo
    {
        return $this->belongsTo(TipoProcesso::class);
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(PssInscricao::class);
    }
}