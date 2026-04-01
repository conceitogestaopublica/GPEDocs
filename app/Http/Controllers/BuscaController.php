<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BuscaSalva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BuscaController extends Controller
{
    public function index(Request $request): Response
    {
        $resultados = null;

        if ($request->filled('q') || $request->hasAny(['tipo_documental_id', 'status', 'data_inicio', 'data_fim', 'autor_id', 'pasta_id', 'tags'])) {
            $query = DB::table('ged_documentos')
                ->leftJoin('ged_tipos_documentais', 'ged_tipos_documentais.id', '=', 'ged_documentos.tipo_documental_id')
                ->leftJoin('users', 'users.id', '=', 'ged_documentos.autor_id')
                ->leftJoin('ged_pastas', 'ged_pastas.id', '=', 'ged_documentos.pasta_id')
                ->whereNull('ged_documentos.deleted_at')
                ->select(
                    'ged_documentos.id',
                    'ged_documentos.nome',
                    'ged_documentos.descricao',
                    'ged_documentos.status',
                    'ged_documentos.mime_type',
                    'ged_documentos.tamanho',
                    'ged_documentos.created_at',
                    'ged_documentos.updated_at',
                    'ged_tipos_documentais.nome as tipo_documental_nome',
                    'users.name as autor_nome',
                    'ged_pastas.nome as pasta_nome'
                );

            // Full-text search using PostgreSQL
            if ($request->filled('q')) {
                $q = $request->input('q');
                $query->where(function ($qb) use ($q) {
                    $qb->whereRaw("to_tsvector('portuguese', coalesce(ged_documentos.nome, '') || ' ' || coalesce(ged_documentos.descricao, '') || ' ' || coalesce(ged_documentos.ocr_texto, '')) @@ plainto_tsquery('portuguese', ?)", [$q])
                        ->orWhere('ged_documentos.nome', 'ilike', "%{$q}%");
                });
            }

            if ($request->filled('tipo_documental_id')) {
                $query->where('ged_documentos.tipo_documental_id', $request->input('tipo_documental_id'));
            }

            if ($request->filled('status')) {
                $query->where('ged_documentos.status', $request->input('status'));
            }

            if ($request->filled('data_inicio')) {
                $query->where('ged_documentos.created_at', '>=', $request->input('data_inicio'));
            }

            if ($request->filled('data_fim')) {
                $query->where('ged_documentos.created_at', '<=', $request->input('data_fim') . ' 23:59:59');
            }

            if ($request->filled('autor_id')) {
                $query->where('ged_documentos.autor_id', $request->input('autor_id'));
            }

            if ($request->filled('pasta_id')) {
                $query->where('ged_documentos.pasta_id', $request->input('pasta_id'));
            }

            if ($request->filled('tags')) {
                $tags = is_array($request->input('tags')) ? $request->input('tags') : [$request->input('tags')];
                $query->whereExists(function ($sub) use ($tags) {
                    $sub->select(DB::raw(1))
                        ->from('ged_documento_tags')
                        ->whereColumn('ged_documento_tags.documento_id', 'ged_documentos.id')
                        ->whereIn('ged_documento_tags.tag_id', $tags);
                });
            }

            $resultados = $query->orderByDesc('ged_documentos.updated_at')->paginate(20)->withQueryString();
        }

        $buscasSalvas = BuscaSalva::where('usuario_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('GED/Busca/Index', [
            'resultados'    => $resultados,
            'filters'       => $request->only(['q', 'tipo_documental_id', 'status', 'data_inicio', 'data_fim', 'autor_id', 'pasta_id', 'tags']),
            'buscas_salvas' => $buscasSalvas,
        ]);
    }

    public function salvar(Request $request)
    {
        $request->validate([
            'nome'    => ['required', 'string', 'max:255'],
            'filtros' => ['required', 'array'],
        ]);

        try {
            BuscaSalva::create([
                'usuario_id' => Auth::id(),
                'nome'       => $request->input('nome'),
                'filtros'    => $request->input('filtros'),
            ]);

            return redirect()->back()->with('success', 'Busca salva com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao salvar busca: ' . $e->getMessage());
        }
    }
}
