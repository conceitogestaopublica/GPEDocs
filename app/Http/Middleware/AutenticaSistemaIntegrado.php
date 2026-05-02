<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SistemaIntegrado;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida o API token do sistema externo no header Authorization: Bearer <token>.
 * Em sucesso, injeta `sistema_integrado` no request (acessivel via $request->attributes->get).
 * Em falha, retorna 401 com JSON.
 */
class AutenticaSistemaIntegrado
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extrairToken($request);

        if (! $token) {
            return response()->json([
                'erro'    => 'Token de autenticacao ausente.',
                'detalhe' => 'Envie o header: Authorization: Bearer {seu_token}',
            ], 401);
        }

        $sistema = SistemaIntegrado::autenticar($token);

        if (! $sistema) {
            return response()->json([
                'erro'    => 'Token invalido, expirado ou sistema inativo.',
            ], 401);
        }

        $request->attributes->set('sistema_integrado', $sistema);

        return $next($request);
    }

    private function extrairToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with(strtolower($header), 'bearer ')) {
            return trim(substr($header, 7));
        }
        // Fallback: header customizado (pra clients legados)
        return $request->header('X-Api-Token');
    }
}
