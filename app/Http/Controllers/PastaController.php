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
    public function index(Request $request): Response
    {
        $pastaId = $request->input('pasta_id');
        $busca   = trim((string) $request->input('busca', ''));
        $tipoDocId = $request->input('tipo_documental_id');
        $status    = $request->input('status');
        $dataDe    = $request->input('data_de');
        $dataAte   = $request->input('data_ate');

        $pastas = Pasta::where('ativo', true)
            ->withCount(['documentos' => fn ($q) => $q->whereNull('deleted_at')])
            ->orderBy('nome')
            ->get();

        // Subpastas filhas para uso do withCount('children') no front
        $childrenCount = Pasta::where('ativo', true)
            ->selectRaw('parent_id, count(*) as total')
            ->groupBy('parent_id')
            ->pluck('total', 'parent_id');
        $pastas = $pastas->map(function ($p) use ($childrenCount) {
            $p->children_count = (int) ($childrenCount[$p->id] ?? 0);
            return $p;
        });

        $pastaAtual = $pastaId ? Pasta::find($pastaId) : null;
        $breadcrumb = [];
        if ($pastaAtual) {
            $atual = $pastaAtual;
            while ($atual) {
                array_unshift($breadcrumb, ['id' => $atual->id, 'nome' => $atual->nome]);
                $atual = $atual->parent_id ? Pasta::find($atual->parent_id) : null;
            }
        }

        // Documentos da pasta atual (ou raiz = sem pasta) — quando ha busca/filtros,
        // pesquisa em TODAS as pastas, nao so na atual.
        $temFiltroAvancado = $busca !== '' || $tipoDocId || $status || $dataDe || $dataAte;

        $docsQuery = Documento::whereNull('deleted_at')
            ->with(['tipoDocumental:id,nome', 'autor:id,name'])
            ->when($temFiltroAvancado, function ($q) use ($pastaAtual) {
                // Com filtros: busca em todo lugar (mas se ha pasta selecionada, restringe a ela e descendentes)
                if ($pastaAtual) {
                    $q->where('pasta_id', $pastaAtual->id);
                }
            }, function ($q) use ($pastaAtual) {
                // Sem filtros: lista apenas a pasta atual (ou raiz)
                $pastaAtual
                    ? $q->where('pasta_id', $pastaAtual->id)
                    : $q->whereNull('pasta_id');
            })
            ->when($busca !== '', function ($q) use ($busca) {
                $termo = "%{$busca}%";
                $q->where(function ($q2) use ($termo) {
                    $q2->where('nome', 'ilike', $termo)
                       ->orWhere('descricao', 'ilike', $termo);
                });
            })
            ->when($tipoDocId, fn ($q) => $q->where('tipo_documental_id', $tipoDocId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dataDe, fn ($q) => $q->where('created_at', '>=', $dataDe . ' 00:00:00'))
            ->when($dataAte, fn ($q) => $q->where('created_at', '<=', $dataAte . ' 23:59:59'))
            ->orderByDesc('updated_at');

        $documentos = $docsQuery->paginate(30)->withQueryString();
        $documentos->setCollection($documentos->getCollection()->map(function ($d) {
            $d->tipo_nome = $d->tipoDocumental?->nome;
            $d->autor_nome = $d->autor?->name;
            unset($d->tipoDocumental, $d->autor);
            return $d;
        }));

        $tiposDocumentais = DB::table('ged_tipos_documentais')
            ->where('ativo', true)->orderBy('nome')->get(['id','nome']);

        return Inertia::render('GED/Repositorio/Index', [
            'pastas'           => $pastas,
            'documentos'       => $documentos,
            'pasta_atual'      => $pastaAtual,
            'breadcrumb'       => $breadcrumb,
            'tipos_documentais'=> $tiposDocumentais,
            'filtros'          => [
                'busca'              => $busca,
                'tipo_documental_id' => $tipoDocId,
                'status'             => $status,
                'data_de'            => $dataDe,
                'data_ate'           => $dataAte,
            ],
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
