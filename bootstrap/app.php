<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        // Sem rotas /landlord: o painel landlord vive só no gpe2 (ResolveTenant redireciona).
    )->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\ResolveTenant::class,
            \App\Http\Middleware\VerifyTenant::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsureUgSelected::class,
            ]);

            // Aliases para uso em rotas
            $middleware->alias([
                'sistema.api' => \App\Http\Middleware\AutenticaSistemaIntegrado::class,
                'auth.cidadao' => \App\Http\Middleware\AutenticarCidadao::class,
                'tenant.require' => \App\Http\Middleware\RequireTenant::class,
                'verify-sso' => \App\Http\Middleware\VerifyActiveSuperAdminSSO::class,
            ]);

            // API stateless: ignora CSRF nas rotas /api/integracoes/*
            $middleware->validateCsrfTokens(except: [
                'api/integracoes/*',
            ]);
        })->withExceptions(function (Exceptions $exceptions): void {
            //
        })->create();
