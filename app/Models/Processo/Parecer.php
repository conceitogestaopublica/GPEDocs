<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\Concerns\BelongsToUg;
use App\Models\Ug;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Parecer (TR 4.39): peça numerada, com conclusão, anexos e QR, vinculável a processo ou memorando. */
class Parecer extends Model
{
    use SoftDeletes, BelongsToUg, \App\Models\Concerns\TemConteudoComVariaveis;

    public const CONCLUSOES = ['favoravel' => 'Favorável', 'desfavoravel' => 'Desfavorável', 'parcial' => 'Parcialmente favorável', 'informativo' => 'Informativo'];

    public const ROTULOS_VARIAVEIS = ['numero' => 'Número do parecer', 'data' => 'Data por extenso', 'assunto' => 'Assunto',
        'setor' => 'Setor de origem', 'remetente' => 'Parecerista', 'processo' => 'Número do processo'];

    protected $table = 'proc_pareceres';

    protected $fillable = ['ug_id', 'numero', 'assunto', 'ementa', 'conteudo', 'conclusao', 'remetente_id', 'setor_origem', 'unidade_id',
        'processo_id', 'memorando_id', 'status', 'enviado_em', 'qr_code_token', 'documento_id'];

    protected function casts(): array
    {
        return ['enviado_em' => 'datetime'];
    }

    public function variaveis(): array
    {
        return [
            'numero' => (string) $this->numero, 'assunto' => (string) $this->assunto, 'setor' => (string) $this->setor_origem,
            'data' => \App\Support\Variaveis::dataPorExtenso($this->enviado_em ?? now()),
            'remetente' => (string) $this->remetente?->name, 'processo' => (string) $this->processo?->numero_protocolo,
        ];
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class)->withoutGlobalScopes();
    }

    public function memorando(): BelongsTo
    {
        return $this->belongsTo(Memorando::class)->withoutGlobalScopes();
    }

    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ParecerAnexo::class);
    }
}
