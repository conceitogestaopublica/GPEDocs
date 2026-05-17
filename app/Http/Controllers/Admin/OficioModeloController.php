<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Processo\OficioModelo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OficioModeloController extends Controller
{
    public function index(Request $request): Response
    {
        $ugId = (int) ($request->session()->get('ug_id') ?? 0);

        $modelos = OficioModelo::with('criador:id,name')
            ->when($ugId, fn ($q) => $q->where(fn ($w) => $w->where('ug_id', $ugId)->orWhereNull('ug_id')))
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Admin/OficiosModelos/Index', [
            'modelos' => $modelos,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'descricao' => ['nullable', 'string'],
            'conteudo'  => ['required', 'string'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        OficioModelo::create([
            'ug_id'      => $request->session()->get('ug_id'),
            'nome'       => $request->input('nome'),
            'categoria'  => $request->input('categoria'),
            'descricao'  => $request->input('descricao'),
            'conteudo'   => $request->input('conteudo'),
            'ativo'      => $request->boolean('ativo', true),
            'criado_por' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Modelo criado com sucesso.');
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'descricao' => ['nullable', 'string'],
            'conteudo'  => ['required', 'string'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        $modelo = OficioModelo::findOrFail($id);
        $modelo->update($request->only(['nome', 'categoria', 'descricao', 'conteudo', 'ativo']));

        return redirect()->back()->with('success', 'Modelo atualizado.');
    }

    public function destroy(int $id)
    {
        OficioModelo::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Modelo removido.');
    }

    /**
     * Lista pública (usada no Create de oficio) com os modelos
     * disponíveis para a UG ativa do usuário.
     */
    public function disponiveis(Request $request)
    {
        $ugId = (int) ($request->session()->get('ug_id') ?? 0);

        $modelos = OficioModelo::where('ativo', true)
            ->when($ugId, fn ($q) => $q->where(fn ($w) => $w->where('ug_id', $ugId)->orWhereNull('ug_id')))
            ->orderBy('nome')
            ->get(['id', 'nome', 'categoria', 'descricao', 'conteudo']);

        return response()->json($modelos);
    }
}
