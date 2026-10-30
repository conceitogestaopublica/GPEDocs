<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Listeners\RegistrarAcesso;
use App\Models\Ug;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Último passo de qualquer forma de entrada do servidor (senha, certificado
 * ICP-Brasil, gov.br, Google): confere suspensão, pede a verificação em duas
 * etapas quando a conta tem, abre a sessão e leva à escolha da UG.
 */
final class EntradaService
{
    /** Minutos para digitar o código do aplicativo depois da senha. */
    public const MINUTOS_2FA = 5;

    /**
     * @param  string  $origem       senha | certificado | govbr | google | sso
     * @param  bool    $dispensa2fa  o meio de entrada já é forte (certificado ICP-Brasil)
     */
    public function concluir(Request $request, User $user, bool $lembrar, string $origem, bool $dispensa2fa = false): RedirectResponse
    {
        $this->bloquearSeInativo($user);

        if ($user->suspenso_em) {
            RegistrarAcesso::recusa($user, 'acesso suspenso', $origem);

            return redirect()->route('login')->withErrors([
                'login' => str_starts_with((string) $user->suspenso_motivo, self::MOTIVO_INATIVIDADE)
                    ? "Seu acesso foi bloqueado por ficar mais de {$this->dias()} dias sem entrar. Peça a reativação ao responsável pelo seu setor ou ao administrador."
                    : 'Seu acesso está suspenso. Procure o administrador do sistema.',
            ]);
        }

        if (! $dispensa2fa && $user->temDoisFatores()) {
            $request->session()->put('entrada_2fa', [
                'user_id' => $user->id,
                'lembrar' => $lembrar,
                'origem'  => $origem,
                'ate'     => now()->addMinutes(self::MINUTOS_2FA)->getTimestamp(),
            ]);

            return redirect()->route('login.dois-fatores');
        }

        return $this->abrirSessao($request, $user, $lembrar, $origem);
    }

    /** Início do suspenso_motivo de bloqueio automático — o superior só reativa estes. */
    public const MOTIVO_INATIVIDADE = 'Inatividade';

    /** Passou do prazo sem entrar (TR 4.7.29): bloqueia agora, antes de abrir a sessão. */
    private function bloquearSeInativo(User $user): void
    {
        $dias = $this->dias();
        if ($dias <= 0 || $user->suspenso_em || $user->super_admin || ! $user->ultimo_acesso_em
            || $user->ultimo_acesso_em->gt(now()->subDays($dias))) {
            return;
        }

        $ultimo = $user->ultimo_acesso_em->setTimezone('America/Sao_Paulo')->format('d/m/Y');
        $user->forceFill([
            'suspenso_em'     => now(),
            'suspenso_por'    => null,
            'suspenso_motivo' => self::MOTIVO_INATIVIDADE . ": sem entrar desde {$ultimo}",
        ])->save();
        \App\Support\HistoricoUsuario::registrar($user, 'Situação', 'Ativo', "Bloqueado por inatividade (sem entrar desde {$ultimo})");
    }

    private function dias(): int
    {
        return (int) config('auth.dias_inatividade', 90);
    }

    public function abrirSessao(Request $request, User $user, bool $lembrar, string $origem): RedirectResponse
    {
        $request->attributes->set('origem_login', $origem);
        Auth::login($user, $lembrar);
        $request->session()->forget('entrada_2fa');
        $request->session()->regenerate();

        // UGs disponíveis: super_admin enxerga todas as ativas; os demais, as do pivot.
        $ugs = $user->super_admin
            ? Ug::where('ativo', true)->get(['id'])
            : $user->ugs()->where('ugs.ativo', true)->get(['ugs.id']);

        if ($ugs->count() === 0) {
            // Super_admin sem nenhuma UG no sistema — segue para módulos (só pode ver/criar UG)
            return $user->super_admin ? redirect()->intended('/modulos') : redirect()->route('sem-ug');
        }

        if ($ugs->count() === 1) {
            session(['ug_id' => $ugs->first()->id]);

            return redirect()->intended('/modulos');
        }

        // Mais de uma UG — o usuário escolhe (inclusive super_admin)
        return redirect()->route('selecionar-ug');
    }
}
