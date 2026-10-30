<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\Ug;
use App\Support\IdentidadeVisual;
use App\Tenant\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Configurações › Identidade visual (TR 4.22.1): cor predominante, brasão mostrado no acesso
 * e imagem de fundo da tela de acesso — e as duas imagens servidas sem login (a tela de
 * acesso é anterior ao login).
 */
class IdentidadeVisualController extends Controller
{
    public function index()
    {
        $r = DB::table('ged_identidade_visual')->first();

        return Inertia::render('Configuracao/IdentidadeVisual', [
            'identidade' => [
                'cor'          => $r?->cor_primaria ?? IdentidadeVisual::PADRAO,
                'brasao_ug_id' => $r?->brasao_ug_id,
                'fundo_url'    => $r?->fundo_login_path ? route('identidade.fundo') . '?v=' . substr(md5((string) $r->fundo_login_path), 0, 8) : null,
            ],
            'ugs'    => Ug::withoutGlobalScopes()->where('ativo', true)->orderBy('codigo')->get(['id', 'codigo', 'nome', 'brasao_path'])
                ->map(fn ($u) => ['id' => $u->id, 'nome' => "{$u->codigo} · {$u->nome}", 'tem_brasao' => (bool) $u->brasao_path]),
            'padrao' => IdentidadeVisual::PADRAO,
        ]);
    }

    public function salvar(Request $request)
    {
        $d = $request->validate([
            'cor'           => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brasao_ug_id'  => ['nullable', 'integer', 'exists:ugs,id'],
            'fundo'         => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'remover_fundo' => ['nullable', 'boolean'],
        ]);
        $atual = DB::table('ged_identidade_visual')->first();
        $fundo = $atual?->fundo_login_path;
        if ($request->boolean('remover_fundo') || $request->hasFile('fundo')) {
            if ($fundo) {
                Storage::disk('documentos')->delete($fundo);
            }
            $fundo = $request->hasFile('fundo') ? $request->file('fundo')->store(TenantStorage::pasta('identidade'), 'documentos') : null;
        }
        $dados = ['cor_primaria' => IdentidadeVisual::corValida($d['cor']), 'brasao_ug_id' => $d['brasao_ug_id'] ?? null,
            'fundo_login_path' => $fundo, 'atualizado_por' => Auth::id(), 'updated_at' => now()];
        $atual ? DB::table('ged_identidade_visual')->where('id', $atual->id)->update($dados)
            : DB::table('ged_identidade_visual')->insert($dados + ['created_at' => now()]);
        IdentidadeVisual::esquecer();

        return back()->with('success', 'Identidade visual salva. As telas já usam a nova cor.');
    }

    /** Brasão do acesso: o da UG escolhida ou, sem escolha, o da primeira UG que tem brasão. */
    public function brasao()
    {
        // ?ug= mostra outra UG (prévia na configuração); brasão já é público no portal
        $id = request()->integer('ug') ?: IdentidadeVisual::atual()['brasao_ug_id'];
        $ug = Ug::withoutGlobalScopes()->whereNotNull('brasao_path')->when($id, fn ($q) => $q->whereKey($id))->orderBy('codigo')->first();
        abort_unless($ug && Storage::disk('documentos')->exists($ug->brasao_path), 404);

        return Storage::disk('documentos')->response($ug->brasao_path, null, ['Cache-Control' => 'public, max-age=3600']);
    }

    public function fundo()
    {
        $path = DB::table('ged_identidade_visual')->value('fundo_login_path');
        abort_unless($path && Storage::disk('documentos')->exists($path), 404);

        return Storage::disk('documentos')->response($path, null, ['Cache-Control' => 'public, max-age=3600']);
    }
}
