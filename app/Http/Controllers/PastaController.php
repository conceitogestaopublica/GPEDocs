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
        $pastas = Pasta::where('ativo', true)
            ->withCount(['documentos' => fn ($q) => $q->whereNull('deleted_at')])
            ->orderBy('nome')
            ->get();

        $documentosSemPasta = Documento::whereNull('pasta_id')
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Repositorio/Index', [
            'pastas'               => $pastas,
            'documentos_sem_pasta' => $documentosSemPasta,
        ]);
    }

    public function tree()
    {
        $pastas = Pasta::where('ativo', true)
            ->with('children')
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

            $pasta->update([
                'nome'      => $request->input('nome'),
                'descricao' => $request->input('descricao'),
            ]);

            $oldPath = $pasta->path;
            $newPath = $pasta->parent_id
                ? Pasta::find($pasta->parent_id)->path . '/' . $request->input('nome')
                : $request->input('nome');

            $pasta->update(['path' => $newPath]);

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
                return redirect()->back()->with('error', 'Nao e possivel excluir pasta com subpastas ou documentos. Use a opcao Inativar.');
            }

            $pasta->delete();

            return redirect()->back()->with('success', 'Pasta excluida com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao excluir pasta: ' . $e->getMessage());
        }
    }

    public function inativar($id)
    {
        try {
            $pasta = Pasta::findOrFail($id);
            $pasta->update(['ativo' => false]);

            // Inativar subpastas recursivamente
            Pasta::where('path', 'like', $pasta->path . '/%')->update(['ativo' => false]);

            return redirect()->back()->with('success', 'Pasta inativada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao inativar pasta: ' . $e->getMessage());
        }
    }

    public function reativar($id)
    {
        try {
            $pasta = Pasta::findOrFail($id);
            $pasta->update(['ativo' => true]);

            return redirect()->back()->with('success', 'Pasta reativada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao reativar pasta: ' . $e->getMessage());
        }
    }
}
