<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Tenant\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MassInsertUserJob implements ShouldQueue
{
    use Queueable;

    public int $tenant_id;
    private $chunk;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tenant_id, array $chunk)
    {
        $this->tenant_id = $tenant_id;
        $this->chunk = $chunk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $tenant = Tenant::active()->where('id', $this->tenant_id)->first();
            Log::info('USUARIO: UPSERT INICIADO', []);
            app(TenantContext::class)->set($tenant);

            DB::table('users')->insert(
                DB::table('users')->where(function ($q) {
                    $q->whereNotIn('email', array_column($this->chunk, 'user_email')/*['biohera@biohera.com.br']*/)
                        /*->orWhereNotIn('id', array_column($this->chunk, 'user_id'))*/;
                })->selectRaw("id, name, email, password")->get()->toArray()
            );
            foreach ($this->chunk as $item) {
                DB::table('uf')->upsert([
                    'id' => $item->uf_id,
                    'nome' => $item->uf_name
                ], 'id');

                DB::table('municipio')->upsert([
                    'id' => $item->muni_id,
                    'nome' => $item->muni_nome,
                    'uf_id' => $item->muni_uf_id,
                    'ibge_id' => $item->muni_ibge_id,
                ], 'id');

                DB::table('bairro')->upsert([
                    'id' => $item->bairro_id,
                    'nome' => $item->bairro_nome,
                    'municipio_id' => $item->bairro_municipio_id,
                ], 'id');

                DB::table('logradouro')->upsert([
                    'id' => $item->log_id,
                    'nome' => $item->log_nome,
                    'cep' => $item->log_cep,
                    'bairro_id' => $item->log_bairro_id,
                ], 'id');
            }
            Log::info('USUARIO: UPSERT FINALIZADO', []);
        } catch (\Exception $exception) {
            Log::error('USUARIO: ERROR: ' . $exception->getMessage(), []);
        }

    }
}
