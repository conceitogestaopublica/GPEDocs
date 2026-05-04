<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que ha um cidadao autenticado no guard `cidadao`.
 * Caso contrario, redireciona para /entrar.
 */
class AutenticarCidadao
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('cidadao')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Autenticacao necessaria.'], 401);
            }
            return redirect('/entrar')->with('warning', 'Faca login para continuar.');
        }
        return $next($request);
    }
}
