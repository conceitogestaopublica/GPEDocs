<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registra cada tentativa de entrada (ged_login_logs) e o último acesso do usuário.
 *
 * Vale para o login por senha e para a entrada pelo SSO (os dois disparam o evento Login).
 * Falha ao registrar não impede o acesso: o log é auditoria, não condição de entrada.
 */
class RegistrarAcesso
{
    public function login(Login $evento): void
    {
        if ($evento->guard !== 'web') {
            return;
        }

        $origem = request()->attributes->get('origem_login')
            ?? (request()->is('sso*', '*/sso*') ? 'sso' : 'senha');

        // Senha certa, mas acesso suspenso: o login é desfeito logo em seguida (LoginController e
        // middleware BloquearUsuarioSuspenso). Fica registrado como recusado, sem contar como acesso.
        if ($evento->user->suspenso_em ?? null) {
            $this->gravar(['user_id' => $evento->user->getAuthIdentifier(), 'login' => $evento->user->email ?? null,
                'sucesso' => false, 'motivo' => 'acesso suspenso', 'origem' => $origem]);

            return;
        }

        $this->gravar([
            'user_id' => $evento->user->getAuthIdentifier(),
            'login'   => $evento->user->email ?? null,
            'sucesso' => true,
            'origem'  => $origem,
        ]);

        DB::table('users')->where('id', $evento->user->getAuthIdentifier())
            ->update(['ultimo_acesso_em' => now(), 'ultima_atividade_em' => now()]);
    }

    public function falha(Failed $evento): void
    {
        if ($evento->guard !== 'web') {
            return;
        }

        $this->gravar([
            'user_id' => $evento->user?->getAuthIdentifier(),
            'login'   => $evento->credentials['email'] ?? null,
            'sucesso' => false,
            'motivo'  => $evento->user ? 'senha incorreta' : 'usuário não encontrado',
        ]);
    }

    /** Entrada recusada sem chegar a abrir sessão (suspenso, código do 2FA errado…). */
    public static function recusa(?\Illuminate\Contracts\Auth\Authenticatable $user, string $motivo, string $origem, ?string $login = null): void
    {
        (new self)->gravar([
            'user_id' => $user?->getAuthIdentifier(),
            'login'   => $login ?? ($user->email ?? null),
            'sucesso' => false,
            'motivo'  => $motivo,
            'origem'  => $origem,
        ]);
    }

    private function gravar(array $dados): void
    {
        try {
            DB::table('ged_login_logs')->insert($dados + [
                'ip'         => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Registro de acesso não gravado', ['erro' => $e->getMessage()]);
        }
        // Trilha de eventos (TR 4.25.12)
        $usuario = $dados['user_id'] ? \App\Models\User::find($dados['user_id']) : null;
        \App\Support\Eventos::registrar($dados['sucesso'] ? 'ACE.ENTRADA' : 'ACE.RECUSA', 'acesso',
            $dados['sucesso'] ? 'Entrada no sistema' . (! empty($dados['origem']) ? " ({$dados['origem']})" : '') : 'Entrada recusada: ' . ($dados['motivo'] ?? '-'),
            ['usuario' => $usuario, 'login' => $dados['login'] ?? null, 'sucesso' => (bool) $dados['sucesso'], 'rota' => 'login']);
    }
}
