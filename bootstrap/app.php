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
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsureUgSelected::class,
            \App\Http\Middleware\BloquearUsuarioSuspenso::class,
            // Trilha de eventos: alterações feitas pelos servidores (TR 4.25.12)
            \App\Http\Middleware\RegistrarEvento::class,
            // Ambiente pós-contratual somente leitura (TR 4.26.17)
            \App\Http\Middleware\SomenteLeitura::class,
            ]);

            // Aliases para uso em rotas
            $middleware->alias([
                'sistema.api' => \App\Http\Middleware\AutenticaSistemaIntegrado::class,
                'auth.cidadao' => \App\Http\Middleware\AutenticarCidadao::class,
                'tenant.require' => \App\Http\Middleware\RequireTenant::class,
                'verify-sso' => \App\Http\Middleware\VerifyActiveSuperAdminSSO::class,
            ]);

            // Atrás de proxy reverso (balanceador, Traefik): confia no X-Forwarded-For dele para
            // o IP real do usuário — é evidência no termo da assinatura por link. Desligado por
            // padrão: sem proxy na frente, confiar no cabeçalho deixaria o IP ser forjado.
            // TRUSTED_PROXIES=* (todos) ou lista de IPs/CIDRs separados por vírgula.
            if ($proxies = env('TRUSTED_PROXIES')) {
                $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
            }

            // API stateless: ignora CSRF nas rotas /api/integracoes/*
            $middleware->validateCsrfTokens(except: [
                'api/integracoes/*',
            ]);
        })->withExceptions(function (Exceptions $exceptions): void {
            //
        })->create();
