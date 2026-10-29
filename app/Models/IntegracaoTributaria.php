<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Configuração da integração com o sistema tributário (por UG; ug_id nulo vale para todas). */
class IntegracaoTributaria extends Model
{
    public const DRIVERS = ['gpe' => 'GPE Tributário', 'rest' => 'Outro sistema (REST configurável)', 'nenhum' => 'Sem integração (informar à mão)'];

    protected $table = 'ged_integracao_tributaria';

    protected $fillable = ['ug_id', 'driver', 'nome_sistema', 'base_url', 'auth_tipo', 'auth_header', 'credencial', 'config', 'timeout', 'ativo',
        'ultimo_teste_em', 'ultimo_teste_ok', 'ultimo_teste_msg'];

    protected $hidden = ['credencial'];

    protected function casts(): array
    {
        return ['credencial' => 'encrypted', 'config' => 'array', 'ativo' => 'boolean', 'ultimo_teste_em' => 'datetime', 'ultimo_teste_ok' => 'boolean'];
    }
}