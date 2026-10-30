<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Backup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ambiente somente leitura (TR 4.26.17): ligado pela prefeitura (Backup e portabilidade) ou por
 * GED_SOMENTE_LEITURA=true, toda alteração é recusada — consultar, baixar e exportar continuam. Entrar,
 * sair, trocar de UG e desligar o próprio modo seguem liberados.
 */
class SomenteLeitura
{
    private const LIBERADAS = ['login', 'logout', 'login.*', 'selecionar-ug.set', 'trocar-ug', 'configuracoes.portabilidade.*', 'two-factor.*', 'password.*'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! self::ativo() || $request->routeIs(...self::LIBERADAS)) {
            return $next($request);
        }
        $msg = 'Ambiente somente leitura: consultas e downloads continuam disponíveis, mas nada pode ser alterado.';

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['message' => $msg], 423)
            : back()->with('error', $msg);
    }

    /** @var array<string, bool> por banco do município, durante a requisição */
    private static array $memo = [];

    public static function ativo(): bool
    {
        if (config('app.somente_leitura')) {
            return true;
        }
        try {
            $banco = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();

            return self::$memo[$banco] ??= Schema::hasTable('port_config') && (bool) Backup::config()->somente_leitura;
        } catch (\Throwable) {
            return false;   // sem município no contexto (landlord, portal)
        }
    }

    public static function esquecer(): void
    {
        self::$memo = [];
    }
}
