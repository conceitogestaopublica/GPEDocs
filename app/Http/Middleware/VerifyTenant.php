<?php

namespace App\Http\Middleware;

use App\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class VerifyTenant
{
    const LOGIN = 'login';
    const LANDLORD_LOGIN = 'landlord-login';

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (empty(app(TenantContext::class)->get()) && !$request->is('landlord', 'landlord/*')) {
            redirect()->route(self::LOGIN);
        }

        return $next($request);
    }
}
