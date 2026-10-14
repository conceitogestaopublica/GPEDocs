<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rotinas favoritas por usuário × UG (Ctrl+K → estrela) — padrão do gpe2 (FavoritoController).
 *
 * Pessoais e escopadas pela UG da sessão: o mesmo usuário tem favoritos diferentes em cada
 * UG. A lista vai em todo request via HandleInertiaRequests (prop `favoritos`); estas ações
 * só adicionam/removem e voltam.
 */
class RotinaFavoritaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'href'  => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'icon'  => ['nullable', 'string', 'max:60'],
        ]);

        DB::table('usuario_favorito')->updateOrInsert(
            ['user_id' => (int) $request->user()->id, 'ug_id' => (int) $request->session()->get('ug_id', 0), 'href' => $dados['href']],
            ['label' => $dados['label'], 'icon' => $dados['icon'] ?? null, 'updated_at' => now(), 'created_at' => now()],
        );

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $dados = $request->validate(['href' => ['required', 'string', 'max:255']]);

        DB::table('usuario_favorito')
            ->where('user_id', (int) $request->user()->id)
            ->where('ug_id', (int) $request->session()->get('ug_id', 0))
            ->where('href', $dados['href'])
            ->delete();

        return back();
    }
}
