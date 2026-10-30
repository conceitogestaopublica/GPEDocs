<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Processo\Ato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consulta pública dos atos oficiais publicados (TR 4.42.6): lista, busca e PDF assinado. */
class AtoController extends PortalApiController
{
    public function index(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $q = trim((string) $request->query('q'));
        $lista = $this->publicos($ug->id)->with('tipo:id,nome,sigla')
            ->when($request->integer('tipo'), fn ($w, $v) => $w->where('tipo_id', $v))
            ->when($request->integer('ano'), fn ($w, $v) => $w->where('ano', $v))
            ->when($request->integer('numero'), fn ($w, $v) => $w->where('numero', $v))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('ementa', 'ilike', "%{$q}%")->orWhere('conteudo', 'ilike', "%{$q}%")))
            ->orderByDesc('ano')->orderByDesc('numero')->paginate(20)->withQueryString();

        return response()->json([
            'atos' => collect($lista->items())->map(fn (Ato $a) => [
                'id' => $a->id, 'identificacao' => $a->identificacao(), 'tipo' => $a->tipo?->nome, 'numero' => $a->numero, 'ano' => $a->ano,
                'ementa' => $a->ementa, 'data' => $a->data_ato?->format('Y-m-d'), 'situacao' => $a->status, 'publicado_em' => $a->publicado_em?->toIso8601String(),
                'pdf_url' => url("/api/portal/atos/{$a->id}/pdf"), 'verificar_url' => \App\Support\Atos::urlVerificacao($a),
            ]),
            'pagina' => $lista->currentPage(), 'paginas' => $lista->lastPage(), 'total' => $lista->total(),
            'tipos' => \App\Models\Processo\AtoTipo::where('publico', true)->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        $ug = $this->ug($request);

        return \App\Http\Controllers\AtoController::respostaPdf($this->publicos($ug->id)->findOrFail($id));
    }

    private function publicos(int $ugId)
    {
        return Ato::withoutGlobalScope('ug')->where('ug_id', $ugId)->whereIn('status', ['publicado', 'revogado'])
            ->whereHas('tipo', fn ($t) => $t->where('publico', true));
    }
}