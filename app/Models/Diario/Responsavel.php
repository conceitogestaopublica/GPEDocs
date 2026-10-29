<?php

declare(strict_types=1);

namespace App\Models\Diario;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Responsável pelo Diário Oficial: monta e publica edições (TR 4.43.5, 4.43.12–13, 4.43.19). */
class Responsavel extends Model
{
    protected $table = 'dof_responsaveis';

    protected $fillable = ['user_id', 'funcao', 'telefone', 'email', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}