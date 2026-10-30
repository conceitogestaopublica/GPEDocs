<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resposta a uma circular (TR 4.46.6). */
class CircularResposta extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'proc_circular_respostas';

    protected $fillable = ['circular_id', 'usuario_id', 'conteudo'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Texto do editor, limpo para exibir. */
    public function getConteudoHtmlAttribute(): string
    {
        return \App\Support\HtmlSeguro::limpar($this->conteudo);
    }
}