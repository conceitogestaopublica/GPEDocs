<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Período de férias, afastamento ou licença do servidor (TR 4.7.15). */
class UsuarioAusencia extends Model
{
    protected $table = 'ged_usuario_ausencias';

    public const TIPOS = ['ferias' => 'Férias', 'afastamento' => 'Afastamento', 'licenca' => 'Licença'];

    protected $fillable = ['user_id', 'tipo', 'inicio', 'fim', 'observacao'];

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fim' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Períodos que incluem o dia de hoje. */
    public function scopeVigentes(Builder $q): Builder
    {
        $hoje = now('America/Sao_Paulo')->toDateString();

        return $q->whereDate('inicio', '<=', $hoje)->whereDate('fim', '>=', $hoje);
    }

    /** "Férias até 15/11" — o que aparece ao lado do nome. */
    public function rotulo(): string
    {
        return (self::TIPOS[$this->tipo] ?? 'Ausente') . ' até ' . $this->fim->format('d/m');
    }
}
