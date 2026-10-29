<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Diario\Edicao;
use App\Models\Diario\Materia;
use App\Support\DiarioOficial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Área pública do Diário Oficial (TR 4.43.32–4.43.46): calendário (datas com edição no mês ou
 * semana), edições do dia, edição com sumário e atos (cada um em PDF), versão certificada,
 * busca textual e avançada. Só edições publicadas.
 */
class DiarioController extends PortalApiController
{
    /** Datas com edição: ?mes=AAAA-MM ou ?de=&ate= (semana). */
    public function calendario(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mes')) ? $request->query('mes') : now()->format('Y-m');
        $de = $request->date('de')?->format('Y-m-d') ?? "{$mes}-01";
        $ate = $request->date('ate')?->format('Y-m-d') ?? date('Y-m-t', strtotime("{$mes}-01"));
        $edicoes = $this->publicadas($ug->id)->whereBetween('data', [$de, $ate])->withCount('materias')->orderBy('data')->orderBy('numero')->get();

        return response()->json([
            'de' => $de, 'ate' => $ate,
            'datas' => $edicoes->groupBy(fn ($e) => $e->data->format('Y-m-d'))->map(fn ($g) => $g->map(fn ($e) => self::resumo($e))->values()),
            'identidade' => \App\Support\IdentidadeVisual::paraTela(),
        ]);
    }

    /** Edições de um dia (padrão: a data mais recente com edição). */
    public function dia(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $data = $request->date('data')?->format('Y-m-d') ?? $this->publicadas($ug->id)->max('data');

        return response()->json(['data' => $data ? substr((string) $data, 0, 10) : null,
            'edicoes' => $data ? $this->publicadas($ug->id)->whereDate('data', $data)->withCount('materias')->orderBy('numero')->get()->map(fn ($e) => self::resumo($e)) : []]);
    }

    public function edicao(Request $request, int $id): JsonResponse
    {
        $ug = $this->ug($request);
        $e = $this->publicadas($ug->id)->withCount('materias')->findOrFail($id);
        $caminhos = DiarioOficial::caminhos();

        return response()->json(['edicao' => self::resumo($e) + [
            'assinada' => $e->assinada, 'certificado' => $e->certificado_cn, 'hash_sha256' => $e->hash_sha256,
            'responsavel' => \App\Models\User::find($e->publicada_por)?->name,
            'atos' => Materia::withoutGlobalScopes()->with('ato.tipo')->where('edicao_id', $e->id)->orderBy('ordem')->get()->map(fn (Materia $m) => [
                'id' => $m->id, 'grupo' => $caminhos[$m->estrutura_id] ?? 'Outros atos', 'identificacao' => $m->ato?->identificacao(), 'tipo' => $m->ato?->tipo?->nome,
                'ementa' => $m->ato?->ementa, 'pagina' => $m->pagina_inicial, 'pdf_url' => url("/api/portal/diario/atos/{$m->id}/pdf"),
            ]),
        ]]);
    }

    /** Versão certificada (edição completa assinada). */
    public function pdf(Request $request, int $id)
    {
        $ug = $this->ug($request);

        return \App\Http\Controllers\DiarioController::respostaPdf($this->publicadas($ug->id)->findOrFail($id));
    }

    /** Cada ato da edição em PDF (o assinado). */
    public function atoPdf(Request $request, int $materia)
    {
        $ug = $this->ug($request);
        $m = Materia::withoutGlobalScopes()->with('ato.tipo')->where('ug_id', $ug->id)->whereHas('edicao', fn ($q) => $q->withoutGlobalScopes()->where('status', 'publicada'))->findOrFail($materia);

        return \App\Http\Controllers\AtoController::respostaPdf($m->ato);
    }

    /** Busca textual e avançada: q, tipo (de ato), de, ate, ordem (recentes|antigas), onde (titulo|conteudo|tudo). */
    public function busca(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $q = trim((string) $request->query('q'));
        $onde = in_array($request->query('onde'), ['titulo', 'conteudo'], true) ? $request->query('onde') : 'tudo';
        $res = Materia::withoutGlobalScopes()->with(['ato.tipo', 'edicao'])->where('dof_materias.ug_id', $ug->id)
            ->whereHas('edicao', fn ($w) => $w->withoutGlobalScopes()->where('status', 'publicada')
                ->when($request->date('de'), fn ($x, $v) => $x->where('data', '>=', $v->format('Y-m-d')))
                ->when($request->date('ate'), fn ($x, $v) => $x->where('data', '<=', $v->format('Y-m-d'))))
            ->whereHas('ato', fn ($w) => $w->withoutGlobalScopes()
                ->when($request->integer('tipo'), fn ($x, $v) => $x->where('tipo_id', $v))
                ->when($q !== '', fn ($x) => $x->where(fn ($y) => match ($onde) {
                    'titulo' => $y->where('ementa', 'ilike', "%{$q}%"),
                    'conteudo' => $y->where('conteudo', 'ilike', "%{$q}%"),
                    default => $y->where('ementa', 'ilike', "%{$q}%")->orWhere('conteudo', 'ilike', "%{$q}%"),
                })))
            ->join('dof_edicoes as ed', 'ed.id', '=', 'dof_materias.edicao_id')
            ->orderBy('ed.data', $request->query('ordem') === 'antigas' ? 'asc' : 'desc')->orderBy('ed.numero', $request->query('ordem') === 'antigas' ? 'asc' : 'desc')->orderBy('dof_materias.ordem')
            ->select('dof_materias.*')->paginate(20)->withQueryString();

        return response()->json([
            'resultados' => collect($res->items())->map(fn (Materia $m) => [
                'edicao' => self::resumo($m->edicao), 'tipo' => $m->ato?->tipo?->nome, 'identificacao' => $m->ato?->identificacao(),
                'ementa' => $m->ato?->ementa, 'pagina' => $m->pagina_inicial, 'pdf_url' => url("/api/portal/diario/atos/{$m->id}/pdf"),
            ]),
            'pagina' => $res->currentPage(), 'paginas' => $res->lastPage(), 'total' => $res->total(),
            'tipos' => \App\Models\Processo\AtoTipo::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    private function publicadas(int $ugId)
    {
        return Edicao::withoutGlobalScope('ug')->where('ug_id', $ugId)->where('status', 'publicada');
    }

    private static function resumo(Edicao $e): array
    {
        return [
            'id' => $e->id, 'numero' => $e->numero, 'data' => $e->data->format('Y-m-d'), 'tipo' => $e->tipo, 'tipo_rotulo' => Edicao::TIPOS[$e->tipo] ?? $e->tipo,
            'qtd_atos' => $e->materias_count ?? Materia::withoutGlobalScopes()->where('edicao_id', $e->id)->count(), 'paginas' => $e->paginas,
            'publicada_em' => $e->publicada_em?->toIso8601String(), 'pdf_url' => url("/api/portal/diario/edicoes/{$e->id}/pdf"),
            'verificar_url' => url("/diario/verificar/{$e->qr_code_token}"),
        ];
    }
}