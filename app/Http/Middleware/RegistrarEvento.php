<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Eventos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toda requisição de servidor logado que altera dados (POST/PUT/PATCH/DELETE) vira um evento da
 * trilha (TR 4.25.12): cadastro, configuração, processo, documento ou comunicação. Não grava o
 * conteúdo enviado (pode ter senha ou dado pessoal) — só a rota, os ids e os campos com erro.
 */
class RegistrarEvento
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        if (! $request->isMethodSafe() && Auth::guard('web')->check() && $request->route()?->getName()) {
            $erros = $request->hasSession() ? ($request->session()->get('errors')?->getBag('default')->keys() ?? []) : [];
            Eventos::deRota($request, $resposta->getStatusCode() < 400 && ! $erros, array_slice($erros, 0, 20));
        }

        return $resposta;
    }
}
