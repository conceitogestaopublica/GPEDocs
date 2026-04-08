<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorandoResposta extends Model
{
    protected $table = 'proc_memorando_respostas';

    protected $fillable = [
        'memorando_id',
        'usuario_id',
        'conteudo',
    ];

    public function memorando(): BelongsTo
    {
        return $this->belongsTo(Memorando::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
