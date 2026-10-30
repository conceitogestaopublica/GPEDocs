<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ug;
use App\Models\UgOrganograma;
use App\Support\Organograma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Organograma para todos os servidores (TR 4.12): árvore dos setores com quem está em
 * cada um (online agora, cargo, último acesso), busca, lista de telefones e a versão
 * pública que a prefeitura incorpora no site. Quem administra UGs desativa e reativa.
 */
class OrganogramaController extends Controller
{
    public function index()
    {
        $ug = Ug::withoutGlobalScopes()->findOrFail(session('ug_id'));
        $gerencia = Auth::user()->can('admin.ugs');

        return Inertia::render('GED/Organograma', [
            'ug'         => $ug->only(['id', 'nome', 'nivel_1_label', 'nivel_2_label', 'nivel_3_label', 'organograma_publico']),
            'arvore'     => Organograma::arvore($ug->id, soAtivos: ! $gerencia),
            'servidores' => Organograma::servidoresPorSetor($ug->id),
            'gerencia'   => $gerencia,
            'link'       => $gerencia && $ug->organograma_token ? route('organograma.publico', $ug->organograma_token) : null,
        ]);
    }

    public function desativar(Request $request, int $id)
    {
        $no = UgOrganograma::where('ug_id', session('ug_id'))->findOrFail($id);
        abort_unless($no->ativo, 422, 'O setor já está desativado.');
        $r = Organograma::desativar($no, $request->integer('destino_id') ?: null);

        return back()->with($r['pendentes'] ? 'warning' : 'success', Organograma::mensagem($no, $r));
    }

    public function reativar(int $id)
    {
        $no = UgOrganograma::where('ug_id', session('ug_id'))->findOrFail($id);
        Organograma::reativar($no);

        return back()->with('success', "Setor \"{$no->nome}\" reativado.");
    }

    /** Liga/desliga a versão pública; "trocar" gera outra chave e invalida o link antigo. */
    public function publicar(Request $request)
    {
        $d = $request->validate(['publico' => ['required', 'boolean'], 'trocar' => ['boolean']]);
        $ug = Ug::withoutGlobalScopes()->findOrFail(session('ug_id'));
        $ug->update([
            'organograma_publico' => $d['publico'],
            'organograma_token'   => ($d['trocar'] ?? false) || ! $ug->organograma_token ? Str::random(32) : $ug->organograma_token,
        ]);

        return back()->with('success', $d['publico'] ? 'Organograma público ligado.' : 'Organograma público desligado.');
    }

    /** Versão pública, para incorporar no site (iframe): só setores ativos, responsável e contatos. */
    public function publico(string $token)
    {
        $ug = Ug::withoutGlobalScopes()->where('organograma_token', $token)->where('organograma_publico', true)->firstOrFail();

        return response()->view('organograma.publico', [
            'ug'     => $ug,
            'arvore' => Organograma::arvore($ug->id, soAtivos: true),
        ]);
    }
}
