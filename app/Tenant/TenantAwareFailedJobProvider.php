<?php

declare(strict_types=1);

namespace App\Tenant;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Irmão de TenantAwareDatabaseQueue para o lado da falha: ao registrar um
 * failed_job, grava o tenant_id no mesmo INSERT (sem UPDATE posterior).
 *
 * Resolução do tenant_id (em ordem):
 *   1. TenantContext ativo no worker (handle() rodou set() antes do erro).
 *   2. Propriedade $tenant_id no command desserializado do payload — funciona
 *      mesmo para __PHP_Incomplete_Class (classe da prop não carregada).
 */
class TenantAwareFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    public function log($connection, $queue, $rawPayload, $exception)
    {
        $tenantId = app(TenantContext::class)->id()
            ?? $this->extractTenantFromPayload($rawPayload);

        return $this->getTable()->insertGetId([
            'connection' => $connection,
            'queue'      => $queue,
            'tenant_id'  => $tenantId,
            'uuid'       => (string) Str::uuid(),
            'payload'    => $rawPayload,
            'exception'  => (string) $exception,
            'failed_at'  => Date::now(),
        ]);
    }

    private function extractTenantFromPayload(string $rawPayload): ?int
    {
        $payload = json_decode($rawPayload, true);
        if (! isset($payload['data']['command'])) {
            return null;
        }
        // @ suprime warning quando a classe do command não existe — vira
        // __PHP_Incomplete_Class, que ainda dá pra ler via cast pra array.
        $command = @unserialize($payload['data']['command']);
        if ($command === false) {
            return null;
        }
        $arr = (array) $command;

        // Props públicas: chave nua "tenant_id".
        if (isset($arr['tenant_id']) && is_numeric($arr['tenant_id'])) {
            return (int) $arr['tenant_id'];
        }
        // Props protected/private: cast cria chave "\0*\0tenant_id" ou
        // "\0FQCN\0tenant_id". Match pelo sufixo cobre ambos.
        foreach ($arr as $key => $val) {
            if (is_string($key) && str_ends_with($key, "\0tenant_id") && is_numeric($val)) {
                return (int) $val;
            }
        }
        return null;
    }
}
