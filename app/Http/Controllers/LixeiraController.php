<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Pasta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lixeira: documentos excluídos (exclusão lógica) e pastas inativas, com restauração.
 * A exclusão já preservava arquivo e registro, mas não havia onde vê-los nem desfazer.
 * Os escopos de UG e de sigilo do Documento valem aqui também.
 */
class LixeiraController extends Controller
{
    public function index(): Response
    {
        $documentos = Documento::onlyTrashed()
            ->with(['tipoDocumental:id,nome', 'pasta:id,nome'])
            ->orderByDesc('deleted_at')
            ->paginate(30)
            ->through(fn (Documento $d) => [
                'id'         => $d->id,
                'nome'       => $d->nome,
                'tipo'       => $d->tipoDocumental?->nome,
                'pasta'      => $d->pasta?->nome,
                'deleted_at' => $d->deleted_at,
                'excluido_por' => AuditLog::where('documento_id', $d->id)->where('acao', 'exclusao')
                    ->latest('id')->with('usuario:id,name')->first()?->usuario?->name,
            ]);

        // Só a pasta mais alta de cada ramo inativo: reativá-la traz as subpastas junto.
        $inativas = Pasta::where('ativo', false)->get(['id', 'nome', 'path', 'parent_id', 'updated_at']);
        $idsInativos = $inativas->pluck('id')->all();
        $pastas = $inativas
            ->reject(fn ($p) => $p->parent_id && in_array($p->parent_id, $idsInativos, true))
            ->map(fn ($p) => [
                'id'          => $p->id,
                'nome'        => $p->nome,
                'subpastas'   => $inativas->filter(fn ($s) => str_starts_with((string) $s->path, $p->path . '/'))->count(),
                'updated_at'  => $p->updated_at,
            ])
            ->values();

        return Inertia::render('GED/Lixeira/Index', [
            'documentos' => $documentos,
            'pastas'     => $pastas,
        ]);
    }

    public function restaurarDocumento(Request $request, $id)
    {
        $documento = Documento::onlyTrashed()->findOrFail($id);

        if ($documento->pasta_id && ! Pasta::whereKey($documento->pasta_id)->where('ativo', true)->exists()) {
            return back()->with('error', 'A pasta do documento está inativa: reative-a primeiro.');
        }

        $documento->restore();

        AuditLog::create([
            'documento_id' => $documento->id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'restauracao',
            'detalhes'     => ['nome' => $documento->nome],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return back()->with('success', "Documento \"{$documento->nome}\" restaurado.");
    }
}
