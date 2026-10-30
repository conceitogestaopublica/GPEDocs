<?php

declare(strict_types=1);

namespace App\Models\Diario;

use App\Models\Concerns\BelongsToUg;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Edição do Diário Oficial: ordinária (a primeira do dia) ou extraordinária (as demais) — TR 4.43.24. */
class Edicao extends Model
{
    use BelongsToUg;

    public const TIPOS = ['ordinaria' => 'Ordinária', 'extraordinaria' => 'Extraordinária'];

    protected $table = 'dof_edicoes';

    protected $fillable = ['ug_id', 'numero', 'data', 'tipo', 'status', 'automatica', 'pdf_path', 'hash_sha256', 'paginas', 'assinada',
        'certificado_cn', 'publicada_em', 'publicada_por', 'criada_por', 'qr_code_token'];

    protected function casts(): array
    {
        return ['data' => 'date', 'automatica' => 'boolean', 'assinada' => 'boolean', 'publicada_em' => 'datetime'];
    }

    public function titulo(): string
    {
        return 'Edição ' . ($this->numero ? "nº {$this->numero}" : '(a publicar)') . ' — ' . (self::TIPOS[$this->tipo] ?? $this->tipo)
            . ' — ' . $this->data?->format('d/m/Y');
    }

    public function materias(): HasMany
    {
        return $this->hasMany(Materia::class)->orderBy('ordem')->orderBy('id');
    }

    public function publicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicada_por');
    }
}