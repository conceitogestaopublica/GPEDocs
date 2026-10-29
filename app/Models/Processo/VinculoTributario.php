<?php

declare(strict_types=1);

namespace App\Models\Processo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Imóvel, guia, alvará, habite-se, certidão, fiscalização ou licença do tributário ligados ao processo. */
class VinculoTributario extends Model
{
    public const TIPOS = ['imovel' => 'Imóvel', 'guia' => 'Guia', 'alvara_construcao' => 'Alvará de construção', 'habitese' => 'Habite-se',
        'certidao' => 'Certidão', 'fiscalizacao' => 'Fiscalização', 'licenca' => 'Licença'];

    protected $table = 'proc_vinculos_tributarios';

    protected $fillable = ['processo_id', 'tipo', 'id_externo', 'numero', 'situacao', 'valor', 'vencimento', 'dados', 'anexo_id', 'manual', 'criado_por'];

    protected function casts(): array
    {
        return ['dados' => 'array', 'valor' => 'decimal:2', 'vencimento' => 'date', 'manual' => 'boolean'];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class)->withoutGlobalScopes();
    }
}