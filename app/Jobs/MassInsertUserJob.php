<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MassInsertUserJob extends TenantAwareJob
{
    private array $chunk;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tenant_id, array $chunk)
    {
        parent::__construct();
        $this->tenant_id = $tenant_id;
        $this->chunk = $chunk;
    }

    /**
     * Execute the job.
     *
     * Fluxo encadeado (PKs sao geradas pelo banco — autoincrement):
     *   1. UF        — id = sigla, upsert direto.
     *   2. Municipio — insertOrIgnore por (uf_id, nome); re-query devolve o id local.
     *   3. Bairro    — traduz muni_id do GPD via mapa local, insertOrIgnore por
     *                  (municipio_id, nome); re-query devolve id local.
     *   4. Logradouro — traduz bairro_id do GPD via mapa local, insertOrIgnore
     *                   por (bairro_id, nome, cep).
     *   5. Users     — insertOrIgnore por email.
     *
     * Cada nivel descarta o id externo (GPD) e usa apenas chave natural — o id
     * que vai pra FK do nivel seguinte e SEMPRE o id local, recem-lido do banco.
     */
    protected function handleTenant(): void
    {
        try {
            Log::info('USUARIO: UPSERT INICIADO', [
                'tenant_id' => $this->tenant_id,
                'chunk'     => count($this->chunk),
            ]);

            $chunk = collect($this->chunk);

            DB::transaction(function () use ($chunk) {
                // ============ 1. UFs ============
                $ufs = $chunk->unique('uf_id')
                    ->map(fn ($i) => ['id' => $i->uf_id, 'nome' => $i->uf_name])
                    ->values()->all();
                if ($ufs) {
                    DB::table('uf')->upsert($ufs, 'id', ['nome']);
                }

                // ============ 2. Municipios ============
                $municipiosUnique = $chunk->unique('muni_id');
                $municipiosInsert = $municipiosUnique
                    ->unique(fn ($i) => $i->muni_uf_id . '|' . $i->muni_nome)
                    ->map(fn ($i) => [
                        'nome'       => $i->muni_nome,
                        'uf_id'      => $i->muni_uf_id,
                        'ibge_id'    => $i->muni_ibge_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->values()->all();
                if ($municipiosInsert) {
                    DB::table('municipio')->insertOrIgnore($municipiosInsert);
                }

                // Re-query: mapa (uf_id|nome) -> id local
                $muniLocal = DB::table('municipio')
                    ->whereIn('uf_id', $municipiosUnique->pluck('muni_uf_id')->unique()->all())
                    ->whereIn('nome', $municipiosUnique->pluck('muni_nome')->unique()->all())
                    ->get(['id', 'uf_id', 'nome'])
                    ->keyBy(fn ($m) => $m->uf_id . '|' . $m->nome);

                // Mapa gpd_muni_id -> local_muni_id (usado pra traduzir FK dos bairros)
                $muniIdMap = [];
                foreach ($municipiosUnique as $i) {
                    $key = $i->muni_uf_id . '|' . $i->muni_nome;
                    if (isset($muniLocal[$key])) {
                        $muniIdMap[$i->muni_id] = $muniLocal[$key]->id;
                    }
                }

                // ============ 3. Bairros ============
                $bairrosUnique = $chunk->unique('bairro_id')
                    ->filter(fn ($i) => isset($muniIdMap[$i->bairro_municipio_id]));

                $bairrosInsert = $bairrosUnique
                    ->unique(fn ($i) => $muniIdMap[$i->bairro_municipio_id] . '|' . $i->bairro_nome)
                    ->map(fn ($i) => [
                        'nome'         => $i->bairro_nome,
                        'municipio_id' => $muniIdMap[$i->bairro_municipio_id],
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ])->values()->all();
                if ($bairrosInsert) {
                    DB::table('bairro')->insertOrIgnore($bairrosInsert);
                }

                $bairroLocal = DB::table('bairro')
                    ->whereIn('municipio_id', array_values($muniIdMap))
                    ->whereIn('nome', $bairrosUnique->pluck('bairro_nome')->unique()->all())
                    ->get(['id', 'municipio_id', 'nome'])
                    ->keyBy(fn ($b) => $b->municipio_id . '|' . $b->nome);

                $bairroIdMap = [];
                foreach ($bairrosUnique as $i) {
                    $key = $muniIdMap[$i->bairro_municipio_id] . '|' . $i->bairro_nome;
                    if (isset($bairroLocal[$key])) {
                        $bairroIdMap[$i->bairro_id] = $bairroLocal[$key]->id;
                    }
                }

                // ============ 4. Logradouros ============
                $logradourosUnique = $chunk->unique('log_id')
                    ->filter(fn ($i) => isset($bairroIdMap[$i->log_bairro_id]));

                $logradourosInsert = $logradourosUnique
                    ->unique(fn ($i) => $bairroIdMap[$i->log_bairro_id] . '|' . $i->log_nome . '|' . ($i->log_cep ?? ''))
                    ->map(fn ($i) => [
                        'nome'       => $i->log_nome,
                        'cep'        => $i->log_cep,
                        'bairro_id'  => $bairroIdMap[$i->log_bairro_id],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->values()->all();
                if ($logradourosInsert) {
                    DB::table('logradouro')->insertOrIgnore($logradourosInsert);
                }

                // ============ 5. Users ============
                $emailsChunk = $chunk->pluck('user_email')->unique()->all();
                $existentes = DB::table('users')
                    ->whereIn('email', $emailsChunk)
                    ->pluck('email')->all();

                $novos = $chunk->unique('user_email')
                    ->reject(fn ($i) => in_array($i->user_email, $existentes, true))
                    ->map(fn ($i) => [
                        'name'     => $i->user_name,
                        'email'    => $i->user_email,
                        'password' => $i->user_password,
                    ])->values()->all();

                if ($novos) {
                    DB::table('users')->insertOrIgnore($novos);
                }
            });

            Log::info('USUARIO: UPSERT FINALIZADO', ['tenant_id' => $this->tenant_id]);
        } catch (Throwable $e) {
            Log::error('USUARIO: ERROR', [
                'tenant_id' => $this->tenant_id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}