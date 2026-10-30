<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Processo;
use App\Support\Ouvidoria;
use App\Support\PainelOuvidoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Painel da ouvidoria (TR 4.33.25–26) e do e-SIC (TR 4.34): indicadores, filtros e a lista
 * com a situação do prazo legal. Quem tem ouvidoria.gerir / esic.gerir vê toda a UG.
 */
class OuvidoriaController extends Controller
{
    public function ouvidoria(Request $request)
    {
        return $this->painel($request, 'ouvidoria');
    }

    public function esic(Request $request)
    {
        return $this->painel($request, 'esic');
    }

    private function painel(Request $request, string $modalidade)
    {
        $user = Auth::user();
        abort_unless($user->super_admin || $user->can($modalidade === 'esic' ? 'esic.gerir' : 'ouvidoria.gerir'), 403);

        $f = array_filter([
            'tipo'          => $request->input('tipo'),
            'situacao'      => in_array($request->input('situacao'), ['abertos', 'vencendo', 'vencidos', 'respondidos'], true) ? $request->input('situacao') : null,
            'identificacao' => $request->input('identificacao'),
            'canal'         => $request->input('canal'),
            'setor'         => $request->integer('setor') ?: null,
            'de'            => $request->input('de'),
            'ate'           => $request->input('ate'),
            'busca'         => trim((string) $request->input('busca')),
        ], fn ($v) => $v !== null && $v !== '');

        $lista = PainelOuvidoria::consulta($modalidade, $f)->with('tipoProcesso:id,nome')
            ->orderByRaw("case when status in ('concluido','arquivado','cancelado') then 1 else 0 end")
            ->orderBy('prazo_legal_em')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Processo $p) => [
                'id' => $p->id, 'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'status' => $p->status,
                'manifestacao' => Ouvidoria::MANIFESTACOES[$p->manifestacao_tipo] ?? null,
                'identificacao' => $p->identificacao, 'canal' => Ouvidoria::CANAIS[$p->canal] ?? $p->canal,
                // Na lista do painel o manifestante sigiloso continua visível: só a ouvidoria chega aqui
                'manifestante' => $p->identificacao === 'anonima' ? 'Anônimo' : $p->requerente_nome,
                'aberto_em' => $p->created_at?->toIso8601String(), 'prazo' => Ouvidoria::situacaoPrazo($p),
            ]);

        return Inertia::render('GED/Flow/Ouvidoria', [
            'modalidade'  => $modalidade,
            'filtros'     => $f,
            'indicadores' => PainelOuvidoria::indicadores(PainelOuvidoria::consulta($modalidade, $f)),
            'lista'       => $lista,
            'opcoes'      => [
                'tipos'          => Ouvidoria::MANIFESTACOES,
                'identificacoes' => Ouvidoria::IDENTIFICACOES,
                'canais'         => Ouvidoria::CANAIS,
                'setores'        => \App\Models\UgOrganograma::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            ],
        ]);
    }
}
