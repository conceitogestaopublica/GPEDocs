<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Login independente de super-admin no painel landlord.
 *
 * Não passa por Auth::login() — autentica direto contra landlord.super_admins
 * e grava chaves cruas na sessão (super_admin_id, super_admin_email).
 *
 * Por design, esta autenticação convive em paralelo com o Auth::user() padrão:
 * a sessão do painel landlord é independente das sessões de tenants (cookie
 * host-only por subdomínio). Ver docs/MULTITENANCY_AUTH_SPEC.md §4.2.
 */
class LandlordLoginController extends Controller
{
    public function show(): View
    {
        return view('landlord.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $cred = $request->validate([
            'email' => 'required|email|max:150',
            'password' => 'required|string',
        ]);

        $admin = SuperAdmin::active()
            ->where('email', $cred['email'])
            ->first();

        if (!$admin || !Hash::check($cred['password'], $admin->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Credenciais inválidas.']);
        }

        // Defesa contra session fixation — regenera o ID antes de gravar a identidade.
        $request->session()->regenerate();

        $request->session()->put('super_admin_id', $admin->id);
        $request->session()->put('super_admin_email', $admin->email);

        $admin->forceFill(['ultimo_acesso' => now()])->save();

        return redirect()->intended(route('landlord.tenants.index'));
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['success' => true]);
    }
}
