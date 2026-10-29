<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Support\Etiquetas;
use App\Support\Setores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Etiquetas do setor (TR 4.19): cadastro (cor da letra e do fundo, hierarquia) e
 * aplicação em lote a processos, memorandos e ofícios. Cada servidor só mexe nas
 * etiquetas dos setores que atende.
 */
class EtiquetaController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('GED/Flow/Etiquetas', [
            'etiquetas' => Etiquetas::doUsuario($user)->map(fn ($e) => (array) $e + [
                'uso' => DB::table('proc_etiquetados')->where('etiqueta_id', $e->id)->count(),
            ])->values(),
            'setores' => \App\Models\UgOrganograma::whereIn('id', Setores::doUsuario($user))->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        DB::table('proc_etiquetas')->insert($dados + ['criado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', "Etiqueta \"{$dados['nome']}\" criada.");
    }

    public function update(Request $request, int $id)
    {
        $this->doMeuSetor($id);
        $dados = $this->validar($request, $id);
        if ($dados['parent_id'] && in_array($dados['parent_id'], Etiquetas::comFilhas($id), true)) {
            throw ValidationException::withMessages(['nome' => 'Uma etiqueta não pode ficar dentro dela mesma.']);
        }
        DB::table('proc_etiquetas')->where('id', $id)->update($dados + ['updated_at' => now()]);

        return back()->with('success', 'Etiqueta alterada.');
    }

    public function destroy(int $id)
    {
        $this->doMeuSetor($id);
        // As filhas sobem um nível; os vínculos saem junto (cascade)
        $pai = DB::table('proc_etiquetas')->where('id', $id)->value('parent_id');
        DB::table('proc_etiquetas')->where('parent_id', $id)->update(['parent_id' => $pai]);
        DB::table('proc_etiquetas')->where('id', $id)->delete();

        return back()->with('success', 'Etiqueta excluída.');
    }

    /** Aplicar ou tirar uma etiqueta de vários itens de uma vez (TR 4.19.7). */
    public function aplicar(Request $request)
    {
        $dados = $request->validate([
            'etiqueta_id'     => ['required', 'integer'],
            'acao'            => ['required', 'in:aplicar,remover'],
            'itens'           => ['required', 'array', 'min:1', 'max:200'],
            'itens.*.tipo'    => ['required', Rule::in(Etiquetas::TIPOS)],
            'itens.*.item_id' => ['required', 'integer'],
        ]);
        $this->doMeuSetor((int) $dados['etiqueta_id']);

        $n = 0;
        foreach ($dados['itens'] as $it) {
            $chave = ['etiqueta_id' => $dados['etiqueta_id'], 'tipo' => $it['tipo'], 'item_id' => $it['item_id']];
            $n += $dados['acao'] === 'aplicar'
                ? DB::table('proc_etiquetados')->insertOrIgnore($chave + ['etiquetado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now()])
                : DB::table('proc_etiquetados')->where($chave)->delete();
        }

        return back()->with('success', $dados['acao'] === 'aplicar' ? "Etiqueta aplicada a {$n} item(ns)." : "Etiqueta retirada de {$n} item(ns).");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $id = null): array
    {
        $setores = Setores::doUsuario(Auth::user());
        $d = $request->validate([
            'unidade_id' => ['required', 'integer', Rule::in($setores)],
            'parent_id'  => ['nullable', 'integer'],
            'nome'       => ['required', 'string', 'max:60'],
            'cor_texto'  => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cor_fundo'  => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['unidade_id.in' => 'Escolha um setor seu.']);
        if (! empty($d['parent_id'])) {
            $pai = DB::table('proc_etiquetas')->where('id', $d['parent_id'])->first();
            if (! $pai || (int) $pai->unidade_id !== (int) $d['unidade_id']) {
                throw ValidationException::withMessages(['nome' => 'A etiqueta-mãe tem de ser do mesmo setor.']);
            }
        }
        $repetida = DB::table('proc_etiquetas')->where('unidade_id', $d['unidade_id'])
            ->where('parent_id', $d['parent_id'] ?? null)->whereRaw('lower(nome) = ?', [mb_strtolower(trim($d['nome']))])
            ->when($id, fn ($q) => $q->where('id', '<>', $id))->exists();
        if ($repetida) {
            throw ValidationException::withMessages(['nome' => 'Já existe etiqueta com esse nome nesse lugar.']);
        }

        return ['unidade_id' => (int) $d['unidade_id'], 'parent_id' => $d['parent_id'] ?? null, 'nome' => trim($d['nome']),
            'cor_texto' => strtolower($d['cor_texto']), 'cor_fundo' => strtolower($d['cor_fundo'])];
    }

    private function doMeuSetor(int $id): void
    {
        $setor = DB::table('proc_etiquetas')->where('id', $id)->value('unidade_id');
        abort_unless($setor && in_array((int) $setor, Setores::doUsuario(Auth::user()), true), 404);
    }
}
