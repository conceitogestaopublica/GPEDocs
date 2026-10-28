<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SistemaIntegrado;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Depois de `sistema.api` (token válido): a API do Portal do Cidadão
 * (`api/portal/*`) cadastra e autentica pessoas — só o sistema do portal entra,
 * e a chave de outro sistema integrado (gpe2, tributário, RH) não vale aqui.
 */
class SomenteSistemaPortalCidadao
{
    public function handle(Request $request, Closure $next): Response
    {
        $sistema = $request->attributes->get('sistema_integrado');
        if (! $sistema instanceof SistemaIntegrado || $sistema->codigo !== config('portal.api_sistema')) {
            return response()->json(['erro' => 'Sistema sem acesso à API do Portal do Cidadão.'], 403);
        }

        return $next($request);
    }
}
