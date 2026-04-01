<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Pasta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PastaController extends Controller
{
    public function index(): Response
    {
        $pastas = Pasta::with('children')
            ->whereNull('parent_id')
            ->orderBy('nome')
            ->get();

        $documentosSemPasta = Documento::whereNull('pasta_id')
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Repositorio/Index', [
            'pastas'             => $pastas,
            'documentos_sem_pasta' => $documentosSemPasta,
        ]);
    }

    public function tree()
    {
        $pastas = Pasta::with('children')
            ->whereNull('parent_id')
            ->orderBy('nome')
            ->get();

        return response()->json($pastas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:ged_pastas,id'],
        ]);

        try {
            $parentPath = '';
            if ($request->filled('parent_id')) {
                $parent = Pasta::findOrFail($request->input('parent_id'));
                $parentPath = $parent->path;
            }

            $path = $parentPath ? $parentPath . '/' . $request->input('nome') : $request->input('nome');

            Pasta::create([
                'nome'      => $request->input('nome'),
                'descricao' => $request->input('descricao'),
                'parent_id' => $request->input('parent_id'),
                'path'      => $path,
                'criado_por'=> Auth::id(),
            ]);

            return redirect()->back()->with('success', 'Pasta criada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao criar pasta: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
        ]);

        try {
            $pasta = Pasta::findOrFail($id);
            $oldName = $pasta->nome;

            $pasta->update([
                'nome'      => $request->input('nome'),
                'descricao' => $request->input('descricao'),
            ]);

            // Update path for this folder and all descendants
            $oldPath = $pasta->path;
            $newPath = $pasta->parent_id
                ? Pasta::find($pasta->parent_id)->path . '/' . $request->input('nome')
                : $request->input('nome');

            $pasta->update(['path' => $newPath]);

            // Update descendant paths
            DB::table('ged_pastas')
                ->where('path', 'like', $oldPath . '/%')
                ->update([
                    'path' => DB::raw("REPLACE(path, '{$oldPath}/', '{$newPath}/')"),
                ]);

            return redirect()->back()->with('success', 'Pasta renomeada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao renomear pasta: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $pasta = Pasta::findOrFail($id);

            $hasChildren = Pasta::where('parent_id', $id)->exists();
            $hasDocumentos = Documento::where('pasta_id', $id)->whereNull('deleted_at')->exists();

            if ($hasChildren || $hasDocumentos) {
                return redirect()->back()->with('error', 'Não é possível excluir uma pasta que contém subpastas ou documentos.');
            }

            $pasta->delete();

            return redirect()->back()->with('success', 'Pasta excluída com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao excluir pasta: ' . $e->getMessage());
        }
    }
}
