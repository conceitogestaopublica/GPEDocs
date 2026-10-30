<?php

declare(strict_types=1);

namespace App\Integracoes\Tramita;

use Illuminate\Support\Facades\DB;

/** Adaptador configurado em tramita_config. */
class Fabrica
{
    public static function config(): object
    {
        return DB::table('tramita_config')->first() ?? (object) ['ativo' => false, 'adaptador' => 'simulador', 'ambiente' => 'homologacao', 'url' => null,
            'certificado_path' => null, 'certificado_senha' => null, 'repositorio_id' => null, 'estrutura_id' => null, 'estrutura_nome' => null,
            'tipo_recebido_id' => null, 'unidade_entrada_id' => null, 'aceite_automatico' => false, 'sincronizado_em' => null];
    }

    public static function barramento(?object $config = null): Barramento
    {
        $config ??= self::config();

        return $config->adaptador === 'pen' ? new Pen($config) : new Simulador();
    }
}
