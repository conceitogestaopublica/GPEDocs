<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoDocumental;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoDocumentalController extends Controller
{
    public function index(): Response
    {
        $tipos = TipoDocumental::withCount('documentos')
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Admin/TiposDocumentais/Index', [
            'tipos' => $tipos,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'             => ['required', 'string', 'max:100'],
            'descricao'        => ['nullable', 'string'],
            'schema_metadados' => ['nullable', 'array'],
            'schema_metadados.*.campo'       => ['required', 'string', 'max:50'],
            'schema_metadados.*.tipo'        => ['required', 'string', 'in:text,number,date,select'],
            'schema_metadados.*.label'       => ['required', 'string', 'max:100'],
            'schema_metadados.*.obrigatorio' => ['nullable', 'boolean'],
            'schema_metadados.*.opcoes'      => ['nullable', 'string'],
        ]);

        TipoDocumental::create([
            'nome'             => $request->input('nome'),
            'descricao'        => $request->input('descricao'),
            'schema_metadados' => $request->input('schema_metadados'),
            'ativo'            => true,
        ]);

        return redirect()->back()->with('success', 'Tipo documental criado com sucesso.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome'             => ['required', 'string', 'max:100'],
            'descricao'        => ['nullable', 'string'],
            'schema_metadados' => ['nullable', 'array'],
            'schema_metadados.*.campo'       => ['required', 'string', 'max:50'],
            'schema_metadados.*.tipo'        => ['required', 'string', 'in:text,number,date,select'],
            'schema_metadados.*.label'       => ['required', 'string', 'max:100'],
            'schema_metadados.*.obrigatorio' => ['nullable', 'boolean'],
            'schema_metadados.*.opcoes'      => ['nullable', 'string'],
        ]);

        $tipo = TipoDocumental::findOrFail($id);
        $tipo->update([
            'nome'             => $request->input('nome'),
            'descricao'        => $request->input('descricao'),
            'schema_metadados' => $request->input('schema_metadados'),
        ]);

        return redirect()->back()->with('success', 'Tipo documental atualizado com sucesso.');
    }

    public function destroy($id)
    {
        $tipo = TipoDocumental::withCount('documentos')->findOrFail($id);

        if ($tipo->documentos_count > 0) {
            return redirect()->back()->with('error', 'Nao e possivel excluir tipo com documentos vinculados. Use a opcao inativar.');
        }

        $tipo->delete();

        return redirect()->back()->with('success', 'Tipo documental excluido com sucesso.');
    }

    public function toggleAtivo($id)
    {
        $tipo = TipoDocumental::findOrFail($id);
        $tipo->update(['ativo' => !$tipo->ativo]);

        $status = $tipo->ativo ? 'ativado' : 'inativado';

        return redirect()->back()->with('success', "Tipo documental {$status} com sucesso.");
    }
}
