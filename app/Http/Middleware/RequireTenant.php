<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aborta se a rota foi atingida sem um tenant configurado. Aplicar em rotas
 * que dependem do banco do tenant — protege contra erros silenciosos onde a
 * query vai para o banco default (sqlite) por falta de contexto.
 *
 * Use como alias: ->middleware('tenant.require')
 */
class RequireTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TenantContext::class)->isSet()) {
            abort(503, 'Tenant não configurado — acesso negado por segurança.');
        }
        return $next($request);
    }
}
