<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Integridade do acervo (TR 4.26.13): recalcula o SHA-256 de cada arquivo que tem o hash gravado e
 * compara; nos que não têm hash, confere ao menos se o arquivo existe. Divergências ficam registradas.
 */
final class IntegridadeAcervo
{
    /** tabela => [coluna do arquivo, coluna do hash|null] */
    public const FONTES = [
        'ged_versoes' => ['arquivo_path', 'hash_sha256'],
        'proc_anexos' => ['arquivo_path', 'hash_sha256'],
        'portal_solicitacao_anexos' => ['arquivo_path', 'hash_sha256'],
        'proc_parecer_anexos' => ['arquivo_path', 'hash_sha256'],
        'proc_pss_anexos' => ['arquivo_path', 'hash_sha256'],
        'dof_edicoes' => ['pdf_path', 'hash_sha256'],
        'proc_oficio_anexos' => ['arquivo_path', null],
        'proc_memorando_anexos' => ['arquivo_path', null],
        'proc_circular_anexos' => ['arquivo_path', null],
        'proc_atos' => ['pdf_path', null],
        'ged_assinaturas' => ['arquivo_assinado_path', null],
    ];

    public static function verificar(): object
    {
        $id = DB::table('port_verificacoes')->insertGetId(['inicio_em' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $disco = Storage::disk('documentos');
        $r = ['conferidos' => 0, 'ok' => 0, 'divergentes' => 0, 'ausentes' => 0];
        $problemas = [];
        foreach (self::FONTES as $tabela => [$colArq, $colHash]) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            DB::table($tabela)->whereNotNull($colArq)->where($colArq, '<>', '')->orderBy('id')
                ->select(array_values(array_filter(['id', $colArq, $colHash])))
                ->chunkById(300, function ($linhas) use ($disco, $tabela, $colArq, $colHash, &$r, &$problemas) {
                    foreach ($linhas as $l) {
                        $r['conferidos']++;
                        $path = $l->{$colArq};
                        if (! $disco->exists($path)) {
                            $r['ausentes']++;
                            $problemas[] = ['tabela' => $tabela, 'id' => $l->id, 'arquivo' => $path, 'problema' => 'arquivo não encontrado'];
                            continue;
                        }
                        $gravado = $colHash ? strtolower((string) $l->{$colHash}) : '';
                        if ($gravado === '') {
                            $r['ok']++;
                            continue;
                        }
                        $ctx = hash_init('sha256');
                        $s = $disco->readStream($path);
                        hash_update_stream($ctx, $s);
                        fclose($s);
                        if (hash_final($ctx) === $gravado) {
                            $r['ok']++;
                        } else {
                            $r['divergentes']++;
                            $problemas[] = ['tabela' => $tabela, 'id' => $l->id, 'arquivo' => $path, 'problema' => 'hash diferente do gravado (arquivo alterado ou corrompido)'];
                        }
                    }
                });
        }
        DB::table('port_verificacoes')->where('id', $id)->update($r + ['problemas' => json_encode(array_slice($problemas, 0, 500), JSON_UNESCAPED_UNICODE),
            'fim_em' => now(), 'updated_at' => now()]);

        return DB::table('port_verificacoes')->find($id);
    }
}
