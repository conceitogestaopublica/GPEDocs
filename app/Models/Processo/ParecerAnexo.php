<?php

declare(strict_types=1);

namespace App\Models\Processo;

use Illuminate\Database\Eloquent\Model;

class ParecerAnexo extends Model
{
    protected $table = 'proc_parecer_anexos';

    protected $fillable = ['parecer_id', 'nome', 'arquivo_path', 'tamanho', 'mime_type', 'hash_sha256', 'enviado_por'];
}