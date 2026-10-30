<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\OficioModelo;
use App\Support\Setores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Modelos de texto no editor (TR 4.16.11): quem escreve escolhe entre os do seu setor e os
 * da administração para o módulo, e salva o texto atual como modelo do setor.
 */
class ModeloTextoController extends Controller
{
    private const MODULOS = ['oficio', 'memorando', 'circular', 'despacho'];

    public function index(Request $request)
    {
        $request->validate(['modulo' => ['required', Rule::in(self::MODULOS)]]);
        $setores = Setores::doUsuario(Auth::user());

        return response()->json(OficioModelo::query()->with('unidade:id,nome')
            ->where('ativo', true)
            ->where(fn ($q) => $q->where('ug_id', session('ug_id'))->orWhereNull('ug_id'))
            ->whereIn('modulo', [$request->input('modulo'), 'todos'])
            ->where(fn ($q) => $q->whereNull('unidade_id')->orWhereIn('unidade_id', $setores ?: [-1]))
            ->orderByRaw('unidade_id is null')->orderBy('nome')
            ->get(['id', 'nome', 'categoria', 'descricao', 'conteudo', 'unidade_id'])
            ->map(fn (OficioModelo $m) => [
                'id' => $m->id, 'nome' => $m->nome, 'categoria' => $m->categoria, 'descricao' => $m->descricao,
                'conteudo' => $m->conteudo, 'setor' => $m->unidade?->nome, 'do_setor' => (bool) $m->unidade_id,
            ]));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'nome'     => ['required', 'string', 'max:200'],
            'modulo'   => ['required', Rule::in(self::MODULOS)],
            'conteudo' => ['required', 'string'],
        ]);
        $user = Auth::user();
        if (! $user->unidade_id) {
            throw ValidationException::withMessages(['nome' => 'Você não está lotado em um setor; peça ao administrador para cadastrar o modelo.']);
        }
        $m = OficioModelo::create([
            'ug_id' => session('ug_id'), 'nome' => $d['nome'], 'modulo' => $d['modulo'], 'conteudo' => $d['conteudo'],
            'unidade_id' => $user->unidade_id, 'ativo' => true, 'criado_por' => $user->id,
        ]);

        return response()->json(['id' => $m->id]);
    }

    /** Do setor, só quem atende o setor exclui; os da administração ficam com o administrador. */
    public function destroy(int $id)
    {
        $m = OficioModelo::findOrFail($id);
        abort_unless($m->unidade_id && Setores::atende(Auth::user(), (int) $m->unidade_id), 403, 'Só o setor dono do modelo pode excluí-lo.');
        $m->delete();

        return response()->noContent();
    }
}
