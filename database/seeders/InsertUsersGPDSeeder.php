<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Jobs\MassInsertUserJob;
use App\Models\Tenant;
use App\Models\User;
use App\Tenant\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Cria usuarios de demonstracao alem do admin do GedSeeder.
 * Idempotente: usa firstOrCreate por email.
 */
class InsertUsersGPDSeeder extends Seeder
{
    use WithoutModelEvents;

    private $maxSizeChunk = 40;

    public function run(): void
    {
        $db = app(TenantContext::class)->get();
        $db_id = $db->id;

        // EXTRACT
        $extract = $this->extraction($db->subdomain);
        $chunk = [];
        foreach ($extract as $row) {
            $chunk[] = $row;
            //TRANSFORM
            if (count($chunk) >= $this->maxSizeChunk) {
                // LOAD
                dispatch((new MassInsertUserJob($db_id, $chunk))->onQueue('default'));
                $chunk = [];
            }
        }
        if (count($chunk)) {
            dispatch((new MassInsertUserJob($db_id, $chunk))->onQueue('default'));
        }
    }

    private function extraction(string $conn): iterable
    {
        $conn = $conn == "localhost" ? Config::get('database.connections.gpe_legado.database') : "gpd$conn";
        Config::set('database.connections.gpe_legado.database', $conn);

        DB::purge('tenant');
        DB::setDefaultConnection('gpe_legado');
        DB::reconnect('gpe_legado');
        $query = DB::cursor("
            select
                /* uf */
                uf.id               AS uf_id,
                uf.nome             AS uf_name,

                /* municipio */
                municipio.id        AS muni_id,
                municipio.nome      AS muni_nome,
                municipio.uf_id     AS muni_uf_id,
                municipio.ibge_id   AS muni_ibge_id,

                /* bairro */
                bairro.id           AS bairro_id,
                bairro.nome         AS bairro_nome,
                bairro.municipio_id AS bairro_municipio_id,

                /* logradouro */
                log.id              AS log_id,
                log.nome            AS log_nome,
                log.cep             AS log_cep,
                log.bairro_id       AS log_bairro_id,

                /* user*/
                usuario.id          AS user_id,
                pessoa.nome         AS user_name,
                IF(IFNULL(usuario.email, '') = '', pessoa.email, usuario.email) AS user_email,
                password            AS user_password
            from usuario
                     join pessoa on pessoa.id = usuario.pessoa_id
                     join logradouro as log on pessoa.logradouro_id = log.id
                     join bairro on log.bairro_id = bairro.id
                     join municipio on bairro.municipio_id = municipio.id
                     join uf on municipio.uf_id = uf.id
            GROUP BY IF(IFNULL(usuario.email, '') = '', pessoa.email, usuario.email)
            ;
        ");
        return $query;
    }

}
