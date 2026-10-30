<?php

declare(strict_types=1);

namespace App\Http\Controllers\Perfil;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Meu perfil › Segurança: verificação em duas etapas por aplicativo
 * autenticador (ativar com QR code, códigos de recuperação, desativar).
 */
class SegurancaController extends Controller
{
    private const QTD_RECUPERACAO = 8;

    public function index(Request $request)
    {
        $user = Auth::user();
        $novo = $request->session()->get('dois_fatores_novo');

        return Inertia::render('GED/Perfil/Seguranca', [
            'dois_fatores' => [
                'ativo'      => $user->temDoisFatores(),
                'ativado_em' => $user->dois_fatores_ativado_em?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'restantes'  => count((array) $user->dois_fatores_recuperacao),
            ],
            // Configuração em andamento: QR e chave para digitar à mão
            'configurando' => $novo && ! $user->temDoisFatores() ? [
                'qr'    => Totp::qrSvg(Totp::uri($novo, $user->email, config('app.name', 'GPE Docs'))),
                'chave' => trim(chunk_split($novo, 4, ' ')),
            ] : null,
            // Mostrados uma única vez, logo depois de gerados
            'codigos_recuperacao' => $request->session()->get('dois_fatores_codigos'),
        ]);
    }

    public function iniciar(Request $request)
    {
        abort_if(Auth::user()->temDoisFatores(), 409);
        $request->session()->put('dois_fatores_novo', Totp::gerarSegredo());

        return redirect()->route('perfil.seguranca');
    }

    public function ativar(Request $request)
    {
        $request->validate(['codigo' => ['required', 'string', 'max:10']]);
        $segredo = $request->session()->get('dois_fatores_novo');
        $user = Auth::user();
        if (! $segredo || $user->temDoisFatores()) {
            return redirect()->route('perfil.seguranca');
        }

        $passo = Totp::verificar($segredo, (string) $request->input('codigo'));
        if ($passo === null) {
            return back()->withErrors(['codigo' => 'Código incorreto. Confira o horário do celular e tente o código atual.']);
        }

        $codigos = $this->novosCodigos();
        $user->forceFill([
            'dois_fatores_segredo'      => $segredo,
            'dois_fatores_recuperacao'  => $codigos,
            'dois_fatores_ativado_em'   => now(),
            'dois_fatores_ultimo_passo' => $passo,
        ])->save();
        $request->session()->forget('dois_fatores_novo');
        \App\Support\HistoricoUsuario::registrar($user, 'Verificação em duas etapas', 'Desativada', 'Ativada');

        return redirect()->route('perfil.seguranca')
            ->with('dois_fatores_codigos', $codigos)
            ->with('success', 'Verificação em duas etapas ativada.');
    }

    public function desativar(Request $request)
    {
        $this->conferirSenha($request);
        Auth::user()->forceFill([
            'dois_fatores_segredo' => null, 'dois_fatores_recuperacao' => null,
            'dois_fatores_ativado_em' => null, 'dois_fatores_ultimo_passo' => null,
        ])->save();
        \App\Support\HistoricoUsuario::registrar(Auth::user(), 'Verificação em duas etapas', 'Ativada', 'Desativada');

        return redirect()->route('perfil.seguranca')->with('success', 'Verificação em duas etapas desativada.');
    }

    public function regerarCodigos(Request $request)
    {
        $this->conferirSenha($request);
        $user = Auth::user();
        abort_unless($user->temDoisFatores(), 409);
        $codigos = $this->novosCodigos();
        $user->forceFill(['dois_fatores_recuperacao' => $codigos])->save();

        return redirect()->route('perfil.seguranca')
            ->with('dois_fatores_codigos', $codigos)
            ->with('success', 'Novos códigos de recuperação gerados; os anteriores deixaram de valer.');
    }

    private function conferirSenha(Request $request): void
    {
        $request->validate(['senha' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('senha'), (string) Auth::user()->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['senha' => 'Senha incorreta.']);
        }
    }

    /** @return array<int, string> códigos xxxx-xxxx */
    private function novosCodigos(): array
    {
        return array_map(
            fn () => implode('-', str_split(bin2hex(random_bytes(4)), 4)),
            range(1, self::QTD_RECUPERACAO),
        );
    }
}
