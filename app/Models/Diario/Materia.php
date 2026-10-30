<?php

declare(strict_types=1);

namespace App\Models\Diario;

use App\Models\Concerns\BelongsToUg;
use App\Models\Processo\Ato;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ato enviado ao Diário Oficial, com ou sem edição definida (TR 4.43.7, 4.43.14). */
class Materia extends Model
{
    use BelongsToUg;

    protected $table = 'dof_materias';

    protected $fillable = ['ug_id', 'ato_id', 'edicao_id', 'estrutura_id', 'ordem', 'pagina_inicial', 'enviada_por'];

    public function ato(): BelongsTo
    {
        return $this->belongsTo(Ato::class)->withoutGlobalScopes();
    }

    public function edicao(): BelongsTo
    {
        return $this->belongsTo(Edicao::class)->withoutGlobalScopes();
    }

    public function estrutura(): BelongsTo
    {
        return $this->belongsTo(Estrutura::class);
    }

    public function enviadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviada_por');
    }
}