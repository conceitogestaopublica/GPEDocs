<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\Concerns\BelongsToUg;
use App\Models\Ug;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ato oficial (TR 4.42): lei, decreto, portaria, edital, ata, contrato… */
class Ato extends Model
{
    use SoftDeletes, BelongsToUg;

    public const STATUS = ['rascunho' => 'Rascunho', 'assinatura' => 'Em assinatura', 'publicado' => 'Publicado', 'revogado' => 'Revogado'];

    protected $table = 'proc_atos';

    protected $fillable = ['ug_id', 'tipo_id', 'numero', 'ano', 'ementa', 'conteudo', 'campos', 'data_ato', 'status', 'autor_id', 'unidade_id',
        'processo_id', 'pdf_path', 'pdfa', 'documento_id', 'qr_code_token', 'publicado_em'];

    protected function casts(): array
    {
        return ['campos' => 'array', 'data_ato' => 'date', 'pdfa' => 'boolean', 'publicado_em' => 'datetime'];
    }

    /** "Decreto nº 12/2026" (sem número enquanto for rascunho de numeração automática). */
    public function identificacao(): string
    {
        $nome = $this->tipo?->nome ?? 'Ato';

        return $this->numero ? "{$nome} nº {$this->numero}/{$this->ano}" : "{$nome} (sem número)";
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(AtoTipo::class, 'tipo_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class)->withoutGlobalScopes();
    }

    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class);
    }
}