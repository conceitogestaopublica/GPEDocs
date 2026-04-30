<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\Ug;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UgController extends Controller
{
    public function index(): Response
    {
        $ugs = Ug::withCount(['organograma', 'usuarios'])
            ->orderByDesc('ativo')
            ->orderBy('codigo')
            ->get();

        return Inertia::render('Configuracao/Ugs/Index', [
            'ugs' => $ugs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Configuracao/Ugs/Form', ['ug' => null]);
    }

    public function edit($id): Response
    {
        $ug = Ug::findOrFail($id);
        return Inertia::render('Configuracao/Ugs/Form', ['ug' => $ug]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo'        => ['required', 'string', 'max:20', 'unique:ugs,codigo'],
            'nome'          => ['required', 'string', 'max:200'],
            'cnpj'          => ['nullable', 'string', 'max:18'],
            'cep'           => ['nullable', 'string', 'max:9'],
            'logradouro'    => ['nullable', 'string', 'max:200'],
            'numero'        => ['nullable', 'string', 'max:20'],
            'complemento'   => ['nullable', 'string', 'max:100'],
            'bairro'        => ['nullable', 'string', 'max:100'],
            'cidade'        => ['nullable', 'string', 'max:100'],
            'uf'            => ['nullable', 'string', 'size:2'],
            'nivel_1_label' => ['required', 'string', 'max:60'],
            'nivel_2_label' => ['required', 'string', 'max:60'],
            'nivel_3_label' => ['required', 'string', 'max:60'],
            'observacoes'   => ['nullable', 'string'],
        ]);

        Ug::create($validated + ['ativo' => true]);

        return redirect()->route('configuracoes.ugs.index')->with('success', 'UG cadastrada.');
    }

    public function update(Request $request, $id)
    {
        $ug = Ug::findOrFail($id);

        $validated = $request->validate([
            'codigo'        => ['required', 'string', 'max:20', 'unique:ugs,codigo,' . $ug->id],
            'nome'          => ['required', 'string', 'max:200'],
            'cnpj'          => ['nullable', 'string', 'max:18'],
            'cep'           => ['nullable', 'string', 'max:9'],
            'logradouro'    => ['nullable', 'string', 'max:200'],
            'numero'        => ['nullable', 'string', 'max:20'],
            'complemento'   => ['nullable', 'string', 'max:100'],
            'bairro'        => ['nullable', 'string', 'max:100'],
            'cidade'        => ['nullable', 'string', 'max:100'],
            'uf'            => ['nullable', 'string', 'size:2'],
            'nivel_1_label' => ['required', 'string', 'max:60'],
            'nivel_2_label' => ['required', 'string', 'max:60'],
            'nivel_3_label' => ['required', 'string', 'max:60'],
            'observacoes'   => ['nullable', 'string'],
        ]);

        $ug->update($validated);

        return redirect()->route('configuracoes.ugs.index')->with('success', 'UG atualizada.');
    }

    public function destroy($id)
    {
        $ug = Ug::withCount(['organograma', 'usuarios'])->findOrFail($id);

        if ($ug->organograma_count > 0 || $ug->usuarios_count > 0) {
            return back()->with('error',
                "Não é possível excluir: {$ug->organograma_count} unidade(s) no organograma, "
                . "{$ug->usuarios_count} usuário(s) vinculado(s). Use \"Inativar\" no lugar."
            );
        }

        $ug->delete();
        return back()->with('success', 'UG excluída.');
    }

    public function toggleAtivo($id)
    {
        $ug = Ug::findOrFail($id);
        $ug->update(['ativo' => ! $ug->ativo]);

        return back()->with('success', 'UG ' . ($ug->ativo ? 'reativada' : 'inativada') . '.');
    }
}
