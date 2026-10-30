<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Árvore do processo em PDF (TR 4.9.16, 4.38.27–4.38.42) — uma linha por geração. */
class Arvore extends Model
{
    protected $table = 'proc_arvores';

    public const CONFIGURACOES = [
        'completa'      => 'Completa (despachos, anexos, comentários e notas internas)',
        'assinaturas'   => 'Com assinaturas e verificação',
        'personalizada' => 'Personalizada',
        'comprimida'    => 'Comprimida',
        'fracionada'    => 'Fracionada (partes de até 5 MB)',
    ];

    /** O que a personalizada pode incluir ou deixar de fora. */
    public const SECOES = [
        'despachos'    => 'Despachos e tramitação',
        'manifestacoes' => 'Manifestações e pareceres dos setores',
        'comentarios'  => 'Comentários',
        'internos'     => 'Notas internas',
        'anexos'       => 'Anexos',
        'decisao'      => 'Decisão e documento da decisão',
        'assinaturas'  => 'Assinaturas e verificação',
    ];

    protected $fillable = [
        'processo_id', 'configuracao', 'opcoes', 'status', 'arquivo_path', 'arquivo_tipo', 'tamanho',
        'paginas', 'partes', 'observacao', 'erro', 'solicitado_por', 'gerada_em',
    ];

    protected function casts(): array
    {
        return ['opcoes' => 'array', 'gerada_em' => 'datetime'];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    /** Seções que entram nesta árvore. @return list<string> */
    public function secoes(): array
    {
        return match ($this->configuracao) {
            'personalizada' => array_values(array_intersect(array_keys(self::SECOES), (array) ($this->opcoes['secoes'] ?? []))),
            'assinaturas'   => array_keys(self::SECOES),
            'completa'      => array_values(array_diff(array_keys(self::SECOES), ['assinaturas'])),
            // comprimida e fracionada: o conteúdo da completa, sem as notas internas
            default         => array_values(array_diff(array_keys(self::SECOES), ['assinaturas', 'internos'])),
        };
    }
}
