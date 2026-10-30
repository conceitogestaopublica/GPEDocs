<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Configurações › Portal do Cidadão › Textos do portal (TR 4.23.1, 4.23.5): política de
 * privacidade e informações sobre pedidos de acesso à informação, mostrados no portal e no app.
 */
class PortalTextosController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuracao/PortalTextos', [
            'textos' => DB::table('ged_portal_textos')->orderBy('id')->get(['chave', 'titulo', 'texto', 'url', 'updated_at'])
                ->map(fn ($t) => (array) $t + ['atualizado_em' => $t->updated_at]),
        ]);
    }

    public function salvar(Request $request, string $chave)
    {
        abort_unless(DB::table('ged_portal_textos')->where('chave', $chave)->exists(), 404);
        $d = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'texto'  => ['nullable', 'string', 'max:50000'],
            'url'    => ['nullable', 'url:http,https', 'max:500'],
        ]);
        DB::table('ged_portal_textos')->where('chave', $chave)->update($d + ['atualizado_por' => Auth::id(), 'updated_at' => now()]);

        return back()->with('success', "\"{$d['titulo']}\" atualizado no portal.");
    }
}
