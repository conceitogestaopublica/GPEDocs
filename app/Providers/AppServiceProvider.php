<?php

namespace App\Providers;

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
        //
    }
}
