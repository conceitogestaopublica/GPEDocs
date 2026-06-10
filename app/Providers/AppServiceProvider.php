<?php

namespace App\Providers;

use App\Tenant\TenantAwareDatabaseQueue;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Multi-tenant: TenantContext com escopo de request/job.
        // scoped() garante 1 instância por ciclo — não vaza entre requisições.
        $this->app->scoped(\App\Tenant\TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Queue::addConnector('database', fn() => new class(app('db')) extends DatabaseConnector {
            public function connect(array $config)
            {
                return new TenantAwareDatabaseQueue(
                    $this->connections->connection($config['connection']),
                    $config['table'],
                    $config['queue'],
                    $config['retry_after'] ?? 60);
            }
        });
        $this->app->singleton(\Illuminate\Queue\Failed\FailedJobProviderInterface::class, function ($app) {
            $config = $app['config']['queue.failed'];
            return new \App\Tenant\TenantAwareFailedJobProvider(
                $app['db'], $config['database'], $config['table']
            );
        });
        Queue::after(fn ($event) => app(\App\Tenant\TenantContext::class)->clear());
        Queue::failing(fn ($event) => app(\App\Tenant\TenantContext::class)->clear());
    }
}
