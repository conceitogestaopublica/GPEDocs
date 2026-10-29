<?php

declare(strict_types=1);

namespace App\Models\Processo;

use Illuminate\Database\Eloquent\Model;

/** Vereador que encaminha requerimentos e indicações ao Executivo (TR 4.37.1). */
class Vereador extends Model
{
    protected $table = 'proc_vereadores';

    protected $fillable = ['nome', 'partido', 'email', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}