<?php

namespace App\Tenant;


class TenantAwareDatabaseQueue extends \Illuminate\Queue\DatabaseQueue
{
    protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0)
    {
        return parent::buildDatabaseRecord($queue, $payload, $availableAt, $attempts)
            + ['tenant_id' => app(TenantContext::class)->id()];
    }
}
