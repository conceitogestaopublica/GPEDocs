<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Listeners\RegistrarAcesso;
use App\Models\User;
use App\Services\Auth\EntradaService;
use App\Support\Totp;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Segunda etapa da entrada: o código de 6 dígitos do aplicativo autenticador
 * (ou um código de recuperação), depois da senha/gov.br/Google.
 */
class DoisFatoresController extends Controller
{
    /** Tentativas erradas antes de voltar para a senha. */
    private const MAX_TENTATIVAS = 5;

    public function formulario(Request $request)
    {
        if (! $this->pendente($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/DoisFatores', [
            'minutos' => EntradaService::MINUTOS_2FA,
        ]);
    }

    public function confirmar(Request $request, EntradaService $entrada)
    {
        $request->validate(['codigo' => ['required', 'string', 'max:20']]);

        $pendente = $this->pendente($request);
        $user = $pendente ? User::find($pendente['user_id']) : null;
        if (! $user || ! $user->temDoisFatores()) {
            $request->session()->forget('entrada_2fa');

            return redirect()->route('login')->withErrors(['login' => 'O tempo para informar o código acabou. Entre de novo.']);
        }

        $codigo = trim((string) $request->input('codigo'));
        if (! $this->confere($user, $codigo)) {
            $tentativas = ($pendente['tentativas'] ?? 0) + 1;
            RegistrarAcesso::recusa($user, 'código da verificação em duas etapas incorreto', $pendente['origem']);
            if ($tentativas >= self::MAX_TENTATIVAS) {
                $request->session()->forget('entrada_2fa');

                return redirect()->route('login')->withErrors(['login' => 'Muitos códigos incorretos. Entre de novo.']);
            }
            $request->session()->put('entrada_2fa.tentativas', $tentativas);

            return back()->withErrors(['codigo' => 'Código incorreto ou expirado.']);
        }

        return $entrada->abrirSessao($request, $user, (bool) $pendente['lembrar'], $pendente['origem']);
    }

    public function cancelar(Request $request)
    {
        $request->session()->forget('entrada_2fa');

        return redirect()->route('login');
    }

    /** Código do aplicativo (6 dígitos) ou de recuperação (consumido ao usar). */
    private function confere(User $user, string $codigo): bool
    {
        if (preg_match('/^\d{6}$/', preg_replace('/\s/', '', $codigo))) {
            $passo = Totp::verificar((string) $user->dois_fatores_segredo, $codigo, $user->dois_fatores_ultimo_passo);
            if ($passo === null) {
                return false;
            }
            $user->forceFill(['dois_fatores_ultimo_passo' => $passo])->save();

            return true;
        }

        $informado = strtolower(preg_replace('/[^a-z0-9]/i', '', $codigo));
        $restantes = (array) $user->dois_fatores_recuperacao;
        foreach ($restantes as $i => $rec) {
            if ($informado !== '' && hash_equals(strtolower(str_replace('-', '', $rec)), $informado)) {
                unset($restantes[$i]);
                $user->forceFill(['dois_fatores_recuperacao' => array_values($restantes)])->save();

                return true;
            }
        }

        return false;
    }

    private function pendente(Request $request): ?array
    {
        $p = $request->session()->get('entrada_2fa');
        if (! is_array($p) || ($p['ate'] ?? 0) < time()) {
            return null;
        }

        return $p;
    }
}
