<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Processo;
use App\Models\Processo\Tramitacao;
use App\Services\AcaoNoProcesso;
use App\Support\Avaliacoes;
use App\Support\Setores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Mapa das demandas, localização do processo, avaliação registrada no balcão e reabertura
 * (TR 4.32.21, 4.32.25–27, 4.33.9, 4.33.24), com faixas de tempo e filtro de setor (4.25.21–26).
 */
class MapaAvaliacaoController extends Controller
{
    private const CORES = ['aberto' => '#0e7490', 'aguardando_assinatura' => '#7c3aed', 'concluido' => '#16a34a',
        'arquivado' => '#64748b', 'cancelado' => '#dc2626'];

    /** Faixas de tempo dos pinos (TR 4.25.23–25): em aberto há quanto tempo; resolvidos em quanto tempo. */
    public const FAIXAS = [
        'aberto_20' => ['Em aberto até 20 dias', '#f59e0b'], 'aberto_30' => ['Em aberto de 20 a 30 dias', '#ea580c'],
        'aberto_mais30' => ['Em aberto há mais de 30 dias', '#dc2626'],
        'resolvido_30' => ['Resolvido em até 30 dias', '#16a34a'], 'resolvido_mais30' => ['Resolvido em mais de 30 dias', '#2563eb'],
    ];

    /** @return array{0: ?string, 1: ?int} [faixa, dias] — cancelado fica sem faixa. */
    private static function faixa(Processo $p): array
    {
        if ($p->status === 'cancelado' || ! $p->created_at) {
            return [null, null];
        }
        if (in_array($p->status, ['concluido', 'arquivado'], true)) {
            $dias = (int) $p->created_at->diffInDays($p->concluido_em ?? $p->updated_at, true);

            return [$dias <= 30 ? 'resolvido_30' : 'resolvido_mais30', $dias];
        }
        $dias = (int) $p->created_at->diffInDays(now(), true);

        return [$dias <= 20 ? 'aberto_20' : ($dias <= 30 ? 'aberto_30' : 'aberto_mais30'), $dias];
    }

    /** Mapa das demandas com localização, no escopo do servidor (setores dele ou toda a UG). */
    public function mapa(Request $request)
    {
        $user = Auth::user();
        $f = [
            'tipo'     => $request->integer('tipo') ?: null,
            'situacao' => in_array($request->input('situacao'), ['abertos', 'concluidos'], true) ? $request->input('situacao') : '',
            'de'       => $request->input('de') ?: null,
            'ate'      => $request->input('ate') ?: null,
            'unidade_id' => $request->integer('unidade_id') ?: null,
            'faixa'    => isset(self::FAIXAS[$request->input('faixa')]) ? $request->input('faixa') : '',
        ];
        $q = \App\Support\RequerimentoServidor::restringir(Processo::query()->with('tipoProcesso:id,nome')->whereNotNull('latitude'), $user);
        if (! $user->acesso_geral_ug && ! $user->super_admin) {
            $setores = Setores::doUsuario($user) ?: [-1];
            $q->where(fn ($w) => $w->where('aberto_por', $user->id)
                ->orWhereHas('tramitacoes', fn ($t) => $t->whereIn('destino_unidade_id', $setores)));
        }
        $q->when($f['tipo'], fn ($w) => $w->where('tipo_processo_id', $f['tipo']))
            ->when($f['situacao'] === 'abertos', fn ($w) => $w->whereNotIn('status', ['concluido', 'arquivado', 'cancelado']))
            ->when($f['situacao'] === 'concluidos', fn ($w) => $w->whereIn('status', ['concluido', 'arquivado']))
            ->when($f['de'], fn ($w) => $w->where('created_at', '>=', $f['de']))
            ->when($f['ate'], fn ($w) => $w->where('created_at', '<=', $f['ate'] . ' 23:59:59'))
            // setor por onde o processo passou, com os subordinados (TR 4.25.21)
            ->when($f['unidade_id'], fn ($w) => $w->whereHas('tramitacoes', fn ($t) => $t->whereIn('destino_unidade_id', \App\Support\Organograma::comDescendentes($f['unidade_id']))));

        $notas = DB::table('proc_avaliacoes')->selectRaw('processo_id, round(avg(nota)::numeric, 1) as nota')->groupBy('processo_id')->pluck('nota', 'processo_id');

        return Inertia::render('GED/Flow/Mapa', [
            'pontos'  => $q->orderByDesc('created_at')->limit(2000)->get()->map(function (Processo $p) {
                [$faixa, $dias] = self::faixa($p);

                return ['id' => $p->id, 'lat' => $p->latitude, 'lng' => $p->longitude, 'numero' => $p->numero_protocolo,
                    'assunto' => $p->assunto, 'tipo' => $p->tipoProcesso?->nome, 'status' => $p->status,
                    'cor' => self::CORES[$p->status] ?? '#0e7490', 'endereco' => $p->local_endereco,
                    'faixa' => $faixa, 'cor_faixa' => self::FAIXAS[$faixa][1] ?? '#64748b', 'dias' => $dias,
                    'nota' => isset($notas[$p->id]) ? (float) $notas[$p->id] : null, 'data' => $p->created_at?->toIso8601String()];
            })->when($f['faixa'], fn ($c) => $c->where('faixa', $f['faixa']))->values(),
            'faixas'  => collect(self::FAIXAS)->map(fn ($x, $k) => ['chave' => $k, 'rotulo' => $x[0], 'cor' => $x[1]])->values(),
            'setores' => DB::table('ug_organograma')->where('ug_id', session('ug_id', $user->ug_id))->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']),
            'filtros' => $f,
            'tipos'   => DB::table('proc_tipos_processo')->where('ativo', true)->where('localizacao', '<>', 'nao')->orderBy('nome')->get(['id', 'nome']),
            'cidade'  => self::cidade(),
        ]);
    }

    public function localizacao(Request $request, int $id)
    {
        $p = Processo::with('tipoProcesso')->findOrFail($id);
        $this->podeMexer($p);
        $dados = Avaliacoes::localizacao($request->input('localizacao'), $p->tipoProcesso?->localizacao === 'obrigatoria' ? 'obrigatoria' : 'opcional');
        $p->update($dados);
        DB::table('proc_historico')->insert([
            'processo_id' => $p->id, 'usuario_id' => Auth::id(), 'acao' => 'localizacao',
            'detalhes' => json_encode($dados, JSON_UNESCAPED_UNICODE), 'ip' => $request->ip(), 'created_at' => now(),
        ]);

        return back()->with('success', 'Localização salva.');
    }

    /** Avaliação que o cidadão dá no balcão (ou por telefone) e o servidor registra. */
    public function avaliar(Request $request, int $id)
    {
        $d = $request->validate(['nota' => ['required', 'integer', 'between:1,5'], 'comentario' => ['nullable', 'string', 'max:2000']]);
        $p = Processo::with('tipoProcesso')->findOrFail($id);
        Avaliacoes::registrar($p, (int) $d['nota'], $d['comentario'] ?? null, 'balcao', null, Auth::id());

        return back()->with('success', 'Avaliação registrada.');
    }

    /**
     * Reabrir processo concluído ou arquivado: volta para o setor que o encerrou e abre
     * uma nova rodada de avaliação (TR 4.32.27).
     */
    public function reabrir(Request $request, int $id)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:2000']]);

        return DB::transaction(function () use ($request, $d, $id) {
            $p = Processo::lockForUpdate()->findOrFail($id);
            abort_unless(in_array($p->status, ['concluido', 'arquivado'], true), 422, 'Só processo concluído ou arquivado pode ser reaberto.');
            $ultima = Tramitacao::where('processo_id', $p->id)->where('frente', 1)->orderByDesc('ordem')->orderByDesc('id')->first();
            $user = Auth::user();
            $doSetor = $ultima?->destino_unidade_id && in_array($ultima->destino_unidade_id, Setores::doUsuario($user), true);
            abort_unless($user->super_admin || $p->concluido_por === $user->id || $doSetor, 403, 'Só quem concluiu ou o setor que concluiu reabre o processo.');

            $nova = Tramitacao::create([
                'processo_id' => $p->id, 'tipo_etapa_id' => $ultima?->tipo_etapa_id,
                'ordem' => (int) Tramitacao::where('processo_id', $p->id)->max('ordem') + 1,
                'setor_origem' => $ultima?->setor_destino, 'setor_destino' => $ultima?->setor_destino,
                'destino_unidade_id' => $ultima?->destino_unidade_id, 'remetente_id' => $user->id,
                'status' => 'pendente', 'despacho' => "Processo reaberto: {$d['motivo']}", 'despachado_em' => now(), 'frente' => 1,
            ]);
            $decisaoAnterior = $p->decisao;
            $p->update(['status' => 'aberto', 'concluido_em' => null, 'decisao' => null, 'etapa_atual_id' => $nova->id,
                'reaberturas' => (int) $p->reaberturas + 1]);
            DB::table('proc_historico')->insert([
                'processo_id' => $p->id, 'usuario_id' => $user->id, 'acao' => 'reabertura',
                'detalhes' => json_encode(['motivo' => $d['motivo'], 'decisao_anterior' => $decisaoAnterior], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(), 'created_at' => now(),
            ]);
            // A solicitação do portal volta a "em andamento" e o cidadão vê o motivo
            $sol = \App\Models\Portal\Solicitacao::query()->withoutGlobalScope('ug')->where('processo_id', $p->id)->first();
            if ($sol) {
                $anterior = $sol->status;
                $sol->update(['status' => 'em_atendimento']);
                \App\Models\Portal\SolicitacaoEvento::create([
                    'solicitacao_id' => $sol->id, 'tipo' => 'status_alterado', 'autor_tipo' => 'atendente',
                    'autor_nome' => $user->name, 'autor_user_id' => $user->id, 'status_anterior' => $anterior,
                    'status_novo' => 'em_atendimento', 'mensagem' => "Processo reaberto: {$d['motivo']}",
                ]);
            }

            return back()->with('success', 'Processo reaberto. Voltou para ' . ($ultima?->setor_destino ?? 'o setor') . '.');
        });
    }

    /** Dados do mapa, da avaliação e da reabertura para a tela do processo. */
    public static function paraTela(Processo $p): array
    {
        $user = Auth::user();
        $ultima = Tramitacao::where('processo_id', $p->id)->where('frente', 1)->orderByDesc('ordem')->orderByDesc('id')->first();
        $encerrado = in_array($p->status, ['concluido', 'arquivado'], true);
        $acoes = AcaoNoProcesso::avaliar($p, $user);

        return [
            'localizacao'   => ['latitude' => $p->latitude, 'longitude' => $p->longitude, 'endereco' => $p->local_endereco,
                'exigencia' => $p->tipoProcesso?->localizacao ?? 'nao',
                'pode' => $user->super_admin || $acoes['despachar'] || ($p->aberto_por === $user->id && $p->status === 'aberto')],
            'avaliacoes'    => Avaliacoes::doProcesso($p->id),
            'pode_avaliar'  => Avaliacoes::pode($p) && $user->can('processo.tramitar'),
            'pode_reabrir'  => $encerrado && $user->can('processo.tramitar') && ($user->super_admin || $p->concluido_por === $user->id
                || ($ultima?->destino_unidade_id && in_array($ultima->destino_unidade_id, Setores::doUsuario($user), true))),
            'cidade'        => self::cidade($p->ug_id),
        ];
    }

    /** Cidade da UG para centralizar o mapa ("Taquaraçu de Minas, MG"). */
    public static function cidade(?int $ugId = null): string
    {
        $ug = \App\Models\Ug::withoutGlobalScopes()->find($ugId ?? session('ug_id'));
        $e = $ug?->enderecoArray() ?? [];

        return trim(implode(', ', array_filter([$e['cidade'] ?? null, $e['uf'] ?? null])));
    }

    private function podeMexer(Processo $p): void
    {
        $user = Auth::user();
        $acoes = AcaoNoProcesso::avaliar($p, $user);
        abort_unless($user->super_admin || $acoes['despachar'] || ($p->aberto_por === $user->id && in_array($p->status, ['aberto'], true)), 403,
            'Só quem está com o processo altera a localização.');
    }
}
