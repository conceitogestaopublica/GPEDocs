<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * A cada requisição de usuário logado:
 * - suspenso: encerra a sessão e volta ao login (vale também para sessões abertas antes da suspensão);
 * - servidor só do Portal/app (somente_portal): idem, com o aviso de que o acesso dele é pelo Portal;
 * - ativo: grava a última atividade, no máximo uma vez por minuto (é o que define "online agora").
 */
class BloquearUsuarioSuspenso
{
    /** Janela de "online agora", em minutos. */
    public const MINUTOS_ONLINE = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->suspenso_em) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['login' => 'Seu acesso está suspenso. Procure o administrador do sistema.']);
        }

        // Servidor só do Portal/app: o GED é dos usuários do sistema
        if ($user && $user->somente_portal) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['login' => 'Seu acesso é pelo Portal do Cidadão (área do servidor) ou pelo aplicativo. Para usar o GPE Docs, procure o administrador do sistema.']);
        }

        if ($user && (! $user->ultima_atividade_em || $user->ultima_atividade_em->lt(now()->subMinute()))) {
            // Direto na tabela: não dispara eventos do model nem mexe no updated_at do cadastro.
            DB::table('users')->where('id', $user->id)->update(['ultima_atividade_em' => now()]);
        }

        return $next($request);
    }
}
