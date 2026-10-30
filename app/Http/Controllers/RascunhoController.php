<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Rascunhos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Rascunhos do editor (TR 4.16.6–4.16.9): lista dos meus e dos do setor, e as chamadas do
 * salvamento automático (criar, salvar, trava, anexos) feitas pela tela de criação.
 */
class RascunhoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $filtro = $request->input('de') === 'meus' ? 'meus' : 'todos';
        $lista = Rascunhos::visiveis($user)
            ->when($filtro === 'meus', fn ($q) => $q->where('r.autor_id', $user->id))
            ->leftJoin('ug_organograma as o', 'o.id', '=', 'r.unidade_id')
            ->leftJoin('users as a', 'a.id', '=', 'r.autor_id')
            ->leftJoin('users as e', 'e.id', '=', 'r.atualizado_por')
            ->leftJoin('users as t', 't.id', '=', 'r.travado_por')
            ->orderByDesc('r.updated_at')
            ->get(['r.id', 'r.modulo', 'r.titulo', 'r.updated_at', 'r.autor_id', 'r.travado_ate', 'r.travado_por',
                'o.nome as setor', 'a.name as autor', 'e.name as editado_por', 't.name as travado_nome'])
            ->map(fn ($r) => [
                'id' => $r->id, 'modulo' => Rascunhos::MODULOS[$r->modulo] ?? $r->modulo, 'titulo' => $r->titulo,
                'setor' => $r->setor, 'autor' => $r->autor, 'meu' => (int) $r->autor_id === (int) $user->id,
                'editado_por' => $r->editado_por, 'atualizado_em' => \Illuminate\Support\Carbon::parse($r->updated_at, 'UTC')->toIso8601String(),
                'em_edicao_por' => $r->travado_por && (int) $r->travado_por !== (int) $user->id && $r->travado_ate && now()->lt($r->travado_ate) ? $r->travado_nome : null,
                'url' => (Rascunhos::CRIAR[$r->modulo] ?? '/') . '?rascunho=' . $r->id,
            ]);

        return Inertia::render('GED/Flow/Rascunhos', ['rascunhos' => $lista, 'filtro' => $filtro]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'modulo' => ['required', Rule::in(array_keys(Rascunhos::MODULOS))],
            'dados'  => ['required', 'array'],
            'titulo' => ['nullable', 'string', 'max:300'],
        ]);
        $id = Rascunhos::criar($d['modulo'], $d['dados'], $d['titulo'] ?? null, Auth::user());

        return response()->json(['id' => $id, 'salvo_em' => now()->toIso8601String()]);
    }

    public function update(Request $request, int $id)
    {
        $d = $request->validate(['dados' => ['required', 'array'], 'titulo' => ['nullable', 'string', 'max:300']]);
        $user = Auth::user();
        $r = Rascunhos::exigir($id, $user);
        if ($outro = Rascunhos::travadoPorOutro($r, $user)) {
            return response()->json(['travado_por' => $outro], 423);
        }
        Rascunhos::salvar($r, $d['dados'], $d['titulo'] ?? null, $user);

        return response()->json(['id' => $r->id, 'salvo_em' => now()->toIso8601String()]);
    }

    /** Renova a trava (a tela chama de tempos em tempos) ou tenta assumir. */
    public function travar(int $id)
    {
        $user = Auth::user();
        $r = Rascunhos::exigir($id, $user);
        $ok = Rascunhos::travar($r, $user);

        return response()->json(['meu' => $ok, 'travado_por' => $ok ? null : Rascunhos::travadoPorOutro($r, $user)], $ok ? 200 : 423);
    }

    /** Chamada ao sair da tela; rascunho já enviado ou descartado não é erro. */
    public function liberar(int $id)
    {
        $user = Auth::user();
        $r = Rascunhos::buscar($id);
        if ($r && Rascunhos::pode($r, $user)) {
            Rascunhos::liberar($r, $user);
        }

        return response()->noContent();
    }

    public function anexar(Request $request, int $id)
    {
        $request->validate(['arquivo' => ['required', 'file', 'max:51200']]);
        $user = Auth::user();
        $r = Rascunhos::exigir($id, $user);
        abort_if(Rascunhos::travadoPorOutro($r, $user), 423, 'Outra pessoa está editando este rascunho.');

        return response()->json(Rascunhos::anexar($r, $request->file('arquivo'), $user));
    }

    public function removerAnexo(int $id, int $anexo)
    {
        $user = Auth::user();
        $r = Rascunhos::exigir($id, $user);
        abort_if(Rascunhos::travadoPorOutro($r, $user), 423, 'Outra pessoa está editando este rascunho.');
        Rascunhos::removerAnexo($r, $anexo);

        return response()->noContent();
    }

    public function destroy(Request $request, int $id)
    {
        $user = Auth::user();
        $r = Rascunhos::exigir($id, $user);
        abort_if(Rascunhos::travadoPorOutro($r, $user), 423, 'Outra pessoa está editando este rascunho.');
        Rascunhos::descartar($r);

        return $request->expectsJson() ? response()->noContent() : back()->with('success', 'Rascunho descartado.');
    }
}
