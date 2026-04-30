<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que o usuario logado tem uma UG ativa na sessao.
 *
 * - Se nao tem session('ug_id'):
 *   - Tem 0 UGs vinculadas e nao e super_admin → /sem-ug
 *   - Tem 1 UG → seta automaticamente e segue
 *   - Tem N UGs → /selecionar-ug
 *
 * Rotas isentas (definidas em $rotasLivres) nao passam por essa checagem.
 */
class EnsureUgSelected
{
    /**
     * Caminhos (prefix) que nao exigem UG selecionada.
     */
    private array $rotasLivres = [
        'logout',
        'selecionar-ug',
        'sem-ug',
        'verificar',
        'validar-assinatura',
        'oficios/rastrear',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user) {
            return $next($request);
        }

        // Se a rota atual e isenta, segue
        $path = ltrim($request->path(), '/');
        foreach ($this->rotasLivres as $livre) {
            if ($path === $livre || str_starts_with($path, $livre . '/')) {
                return $next($request);
            }
        }

        $ugAtualId = session('ug_id');
        if ($ugAtualId) {
            // Confirma que o user ainda tem acesso aquela UG (caso tenha sido removido)
            if ($user->temAcessoUg((int) $ugAtualId)) {
                return $next($request);
            }
            // Sessao desatualizada — limpa e redireciona
            session()->forget('ug_id');
        }

        // Determina UGs disponiveis: super_admin pode escolher entre todas
        $ugs = $user->super_admin
            ? \App\Models\Ug::where('ativo', true)->get(['id'])
            : $user->ugs()->where('ugs.ativo', true)->get(['ugs.id']);

        if ($ugs->count() === 0) {
            // Super_admin sem UG no sistema vai pro /modulos (pode criar UG ali)
            if ($user->super_admin) {
                return $next($request);
            }
            return redirect()->route('sem-ug');
        }

        if ($ugs->count() === 1) {
            session(['ug_id' => $ugs->first()->id]);
            return $next($request);
        }

        return redirect()->route('selecionar-ug');
    }
}
