<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UsuarioFavorito;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Rotinas favoritas por usuário × UG (Ctrl+K → estrela) — padrão do gpe2 (FavoritoController).
 *
 * Pessoais e escopadas pela UG da sessão: o mesmo usuário tem favoritos diferentes em cada
 * UG. A lista vai em todo request via HandleInertiaRequests (prop `favoritos`); estas ações
 * só adicionam/removem e voltam.
 */
class RotinaFavoritaController extends Controller
{
    /** Caminho interno do sistema ("/repositorio?filtro=..."), nunca URL externa nem "//host". */
    private const HREF = ['required', 'string', 'max:255', 'regex:#^/(?!/)#'];

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'href'  => self::HREF,
            'label' => ['required', 'string', 'max:255'],
            'icon'  => ['nullable', 'string', 'max:60'],
        ]);

        UsuarioFavorito::favoritar($request->user()->id, $request->session()->get('ug_id'), $dados['href'], $dados['label'], $dados['icon'] ?? null);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $dados = $request->validate(['href' => self::HREF]);

        UsuarioFavorito::desfavoritar($request->user()->id, $request->session()->get('ug_id'), $dados['href']);

        return back();
    }
}
