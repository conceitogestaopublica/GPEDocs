<?php

declare(strict_types=1);

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Caderno (nível 1), seção (2) ou subseção (3) do Diário Oficial (TR 4.43.11). */
class Estrutura extends Model
{
    public const NIVEIS = [1 => 'Caderno', 2 => 'Seção', 3 => 'Subseção'];

    protected $table = 'dof_estrutura';

    protected $fillable = ['parent_id', 'nivel', 'nome', 'ordem', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** "Caderno do Executivo › Atos do Prefeito › Decretos" */
    public function caminho(): string
    {
        $nomes = [$this->nome];
        $no = $this->pai;
        while ($no) {
            array_unshift($nomes, $no->nome);
            $no = $no->pai;
        }

        return implode(' › ', $nomes);
    }
}