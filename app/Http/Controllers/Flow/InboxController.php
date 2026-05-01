<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GPE Flow — Inbox unificada por ESTADO.
 *
 * Ler da view SQL `proc_inbox` (memorando + oficio + processo) e organiza
 * por estado do item da perspectiva do usuario logado:
 *
 *   ENTRADA (preciso agir):
 *     pessoal()            — endereçado a mim, ainda nao recebi
 *     setor()              — endereçado ao meu setor, ainda ninguem recebeu
 *     aguardandoAssinatura() — eu decidi um processo, falta assinar
 *
 *   EM ANDAMENTO:
 *     emTramitacao()       — itens em fluxo onde participei (ainda nao final)
 *
 *   CONCLUIDOS:
 *     concluidos()         — itens finalizados onde participei
 *
 *   PRIVADO:
 *     saida()              — tudo que eu originei (qualquer status)
 *     rascunhos()          — meus rascunhos
 *
 * Estados finais: concluido, cancelado, arquivado.
 * Estados ativos: rascunho, aberto, enviado, em_tramitacao, aguardando_assinatura.
 */
class InboxController extends Controller
{
    private const ESTADOS_FINAIS = ['concluido', 'cancelado', 'arquivado'];

    /**
     * Caixa Pessoal — itens enderecados pessoalmente a mim, ainda em estado ativo.
     */
    public function pessoal(Request $request): Response
    {
        $userId = (int) Auth::id();
        return $this->render($request, 'pessoal',
            fn ($q) => $q->where('destino_usuario_id', $userId)
                         ->whereNotIn('status', self::ESTADOS_FINAIS)
        );
    }

    /**
     * Caixa Setor — itens enderecados ao meu setor (sem destinatario especifico).
     * Usuario com acesso_geral_ug ve todos itens em primeiro recebimento da UG.
     */
    public function setor(Request $request): Response
    {
        $user = Auth::user();
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        if ($acessoGeral) {
            return $this->render($request, 'setor',
                fn ($q) => $q->whereNotNull('destino_unidade_id')
                             ->whereNull('destino_usuario_id')
                             ->whereNotIn('status', self::ESTADOS_FINAIS)
                             ->where('id', 'not like', 'MT-%')
            );
        }

        if (! $unidadeId) {
            return $this->render($request, 'setor', fn ($q) => $q->whereRaw('1 = 0'),
                avisoSemUnidade: true);
        }

        return $this->render($request, 'setor',
            fn ($q) => $q->where('destino_unidade_id', $unidadeId)
                         ->whereNull('destino_usuario_id')
                         ->whereNotIn('status', self::ESTADOS_FINAIS)
        );
    }

    /**
     * Aguardando Assinatura — processos onde eu sou o signatario pendente.
     * Para Lei 14.063/2020 (decisoes que exigem assinatura ICP-Brasil).
     */
    public function aguardandoAssinatura(Request $request): Response
    {
        $userId = (int) Auth::id();
        return $this->render($request, 'aguardando_assinatura',
            fn ($q) => $q->where('status', 'aguardando_assinatura')
                         ->where('id', 'like', 'P-%') // somente processos por enquanto
                         ->whereIn('item_id', function ($sub) use ($userId) {
                             $sub->select('p.id')
                                 ->from('proc_processos as p')
                                 ->join('ged_assinaturas as a', 'a.solicitacao_id', '=', 'p.solicitacao_assinatura_id')
                                 ->where('a.signatario_id', $userId)
                                 ->where('a.status', 'pendente');
                         })
        );
    }

    /**
     * Em Tramitacao — itens em fluxo (nao final) onde eu participei (originei,
     * fui destino atual, ou meu setor foi destino).
     */
    public function emTramitacao(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) $user->id;
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        return $this->render($request, 'em_tramitacao',
            fn ($q) => $q->whereNotIn('status', self::ESTADOS_FINAIS)
                         ->where('status', '!=', 'aguardando_assinatura')
                         ->where('status', '!=', 'rascunho')
                         ->where(function ($q) use ($userId, $unidadeId, $acessoGeral) {
                             $q->where('remetente_id', $userId)
                               ->orWhere('destino_usuario_id', $userId);
                             if ($acessoGeral) {
                                 $q->orWhereNotNull('destino_unidade_id');
                             } elseif ($unidadeId) {
                                 $q->orWhere('destino_unidade_id', $unidadeId);
                             }
                         })
        );
    }

    /**
     * Concluidos — itens em estado final que eu participei.
     * Engloba o antigo "Arquivados" + processos decididos/cancelados.
     */
    public function concluidos(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) $user->id;
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        return $this->render($request, 'concluidos',
            fn ($q) => $q->whereIn('status', self::ESTADOS_FINAIS)
                         ->where(function ($q) use ($userId, $unidadeId, $acessoGeral) {
                             $q->where('remetente_id', $userId)
                               ->orWhere('destino_usuario_id', $userId);
                             if ($acessoGeral) {
                                 $q->orWhereNotNull('destino_unidade_id');
                             } elseif ($unidadeId) {
                                 $q->orWhere('destino_unidade_id', $unidadeId);
                             }
                         })
        );
    }

    /**
     * Saida (Originados) — tudo que eu criei, em qualquer estado.
     * Vista privada — overlap proposital com outras categorias.
     */
    public function saida(Request $request): Response
    {
        $userId = (int) Auth::id();
        return $this->render($request, 'saida',
            fn ($q) => $q->where('remetente_id', $userId)
                         ->where(function ($q) {
                             $q->where('id', 'like', 'M-%')
                               ->orWhere('id', 'like', 'O-%')
                               ->orWhere('id', 'like', 'P-%');
                         })
        );
    }

    /**
     * Rascunhos — itens que eu criei mas ainda nao despachei.
     */
    public function rascunhos(Request $request): Response
    {
        $userId = (int) Auth::id();
        return $this->render($request, 'rascunhos',
            fn ($q) => $q->where('remetente_id', $userId)->where('status', 'rascunho')
        );
    }

    private function render(Request $request, string $vista, \Closure $escopoFn, bool $avisoSemUnidade = false): Response
    {
        $busca = trim((string) $request->input('busca', ''));
        $tipoFiltro = $request->input('tipo');
        $somenteNaoLidos = $request->boolean('nao_lidos');
        $dataDe = $request->input('data_de');
        $dataAte = $request->input('data_ate');

        $query = DB::table('proc_inbox');

        $escopoFn($query);

        if ($busca !== '') {
            $termo = "%{$busca}%";
            $query->where(function ($q) use ($termo) {
                $q->where('numero', 'like', $termo)
                  ->orWhere('assunto', 'like', $termo);
            });
        }
        if (in_array($tipoFiltro, ['memorando', 'oficio', 'processo'], true)) {
            $query->where('tipo', $tipoFiltro);
        }
        if ($somenteNaoLidos) {
            $query->where('lido', false);
        }
        if ($dataDe) {
            $query->where('criado_em', '>=', $dataDe . ' 00:00:00');
        }
        if ($dataAte) {
            $query->where('criado_em', '<=', $dataAte . ' 23:59:59');
        }

        // Filtro multi-tenant — sempre que ha UG selecionada, filtra (inclui super_admin)
        $ugId = session('ug_id');
        if ($ugId) {
            $query->where('ug_id', $ugId);
        }

        $items = $query->orderByDesc('criado_em')->paginate(20)->withQueryString();

        // Hidrata remetente/destino_usuario/destino_unidade dos ids
        $remetenteIds = collect($items->items())->pluck('remetente_id')->filter()->unique();
        $destUserIds  = collect($items->items())->pluck('destino_usuario_id')->filter()->unique();
        $destUnidIds  = collect($items->items())->pluck('destino_unidade_id')->filter()->unique();

        $users = DB::table('users')->whereIn('id', $remetenteIds->merge($destUserIds))
            ->select('id', 'name', 'email')->get()->keyBy('id');
        $unidades = DB::table('ug_organograma')->whereIn('id', $destUnidIds)
            ->select('id', 'nome', 'codigo')->get()->keyBy('id');

        // Pre-carrega info pra acoes inline: assinaturas pendentes do user logado
        // e processos/memorandos/oficios/circulares arquivados (para botao Arquivar no GED)
        $userId = (int) Auth::id();
        $procIds = collect($items->items())
            ->filter(fn ($it) => $it->tipo === 'processo')
            ->pluck('item_id')
            ->unique();

        // Memorandos arquivados: candidatos a arquivar no GED
        $memoIds = collect($items->items())
            ->filter(fn ($it) => $it->tipo === 'memorando' && in_array($it->status, ['arquivado'], true))
            ->pluck('item_id')->unique();
        $oficioIds = collect($items->items())
            ->filter(fn ($it) => $it->tipo === 'oficio' && in_array($it->status, ['arquivado'], true))
            ->pluck('item_id')->unique();

        $assinaturasPorProcesso = [];
        $documentosPorProcesso = [];
        if ($procIds->isNotEmpty()) {
            $procs = DB::table('proc_processos')
                ->whereIn('id', $procIds)
                ->get(['id', 'status', 'solicitacao_assinatura_id', 'documento_decisao_id']);

            // Assinaturas pendentes do user logado (so processos com solicitacao)
            $solicitacaoIds = $procs->pluck('solicitacao_assinatura_id')->filter()->unique();
            if ($solicitacaoIds->isNotEmpty()) {
                $assinaturas = DB::table('ged_assinaturas')
                    ->whereIn('solicitacao_id', $solicitacaoIds)
                    ->where('signatario_id', $userId)
                    ->where('status', 'pendente')
                    ->get(['id', 'solicitacao_id']);

                foreach ($procs as $p) {
                    if (! $p->solicitacao_assinatura_id) continue;
                    $a = $assinaturas->firstWhere('solicitacao_id', $p->solicitacao_assinatura_id);
                    if ($a && $p->status === 'aguardando_assinatura') {
                        $assinaturasPorProcesso[$p->id] = (int) $a->id;
                    }
                }
            }

            // Documentos das decisoes (para arquivamento no GED) — usa documento_decisao_id
            // primeiro, com fallback para solicitacao->documento_id (compat com decisoes antigas)
            $docIds = $procs->pluck('documento_decisao_id')->filter()->unique();
            $docsViaSolicitacao = collect();
            if ($solicitacaoIds->isNotEmpty()) {
                $docsViaSolicitacao = DB::table('ged_solicitacoes_assinatura')
                    ->whereIn('id', $solicitacaoIds)
                    ->pluck('documento_id', 'id');
            }

            $todosDocIds = $docIds->merge($docsViaSolicitacao->values())->filter()->unique();
            $documentos = $todosDocIds->isNotEmpty()
                ? DB::table('ged_documentos')->whereIn('id', $todosDocIds)
                    ->get(['id', 'nome', 'pasta_id', 'status'])->keyBy('id')
                : collect();

            foreach ($procs as $p) {
                if ($p->status !== 'concluido') continue;
                $docId = $p->documento_decisao_id ?? ($p->solicitacao_assinatura_id ? $docsViaSolicitacao[$p->solicitacao_assinatura_id] ?? null : null);
                if ($docId && isset($documentos[$docId])) {
                    $d = $documentos[$docId];
                    $documentosPorProcesso[$p->id] = [
                        'documento_id' => (int) $d->id,
                        'nome'         => $d->nome,
                        'arquivado'    => $d->status === 'arquivado' && $d->pasta_id !== null,
                    ];
                }
            }
        }

        // Memorandos/Oficios arquivados: pode_arquivar_ged se ainda nao tem documento_id ja com pasta
        $memosInfo = $memoIds->isNotEmpty()
            ? DB::table('proc_memorandos as m')->whereIn('m.id', $memoIds)
                ->leftJoin('ged_documentos as d', 'd.id', '=', 'm.documento_id')
                ->select('m.id', 'm.documento_id', 'd.pasta_id', 'd.status as doc_status')
                ->get()->keyBy('id')
            : collect();
        $oficiosInfo = $oficioIds->isNotEmpty()
            ? DB::table('proc_oficios as o')->whereIn('o.id', $oficioIds)
                ->leftJoin('ged_documentos as d', 'd.id', '=', 'o.documento_id')
                ->select('o.id', 'o.documento_id', 'd.pasta_id', 'd.status as doc_status')
                ->get()->keyBy('id')
            : collect();

        $items->setCollection($items->getCollection()->map(function ($it) use ($users, $unidades, $assinaturasPorProcesso, $documentosPorProcesso, $memosInfo, $oficiosInfo) {
            $it->remetente_nome = $users[$it->remetente_id]->name ?? null;
            $it->destino_usuario_nome = $it->destino_usuario_id ? ($users[$it->destino_usuario_id]->name ?? null) : null;
            $it->destino_unidade_nome = $it->destino_unidade_id ? ($unidades[$it->destino_unidade_id]->nome ?? null) : null;

            $it->pode_assinar = isset($assinaturasPorProcesso[$it->item_id]);
            $it->assinatura_id = $assinaturasPorProcesso[$it->item_id] ?? null;

            // Estado de arquivamento no GED — varia por tipo
            $jaArquivado = false;
            $podeArquivar = false;
            $docId = null;

            if ($it->tipo === 'processo') {
                $docInfo = $documentosPorProcesso[$it->item_id] ?? null;
                if ($docInfo) {
                    $podeArquivar = ! $docInfo['arquivado'];
                    $jaArquivado  = $docInfo['arquivado'];
                    $docId        = $docInfo['documento_id'];
                }
            } elseif ($it->tipo === 'memorando' && isset($memosInfo[$it->item_id])) {
                $m = $memosInfo[$it->item_id];
                $jaArquivado = $m->doc_status === 'arquivado' && $m->pasta_id !== null;
                $podeArquivar = ! $jaArquivado && $it->status === 'arquivado';
                $docId = $m->documento_id;
            } elseif ($it->tipo === 'oficio' && isset($oficiosInfo[$it->item_id])) {
                $o = $oficiosInfo[$it->item_id];
                $jaArquivado = $o->doc_status === 'arquivado' && $o->pasta_id !== null;
                $podeArquivar = ! $jaArquivado && $it->status === 'arquivado';
                $docId = $o->documento_id;
            }

            $it->pode_arquivar_ged = $podeArquivar;
            $it->ja_arquivado_ged  = $jaArquivado;
            $it->documento_id      = $docId;

            return $it;
        }));

        $contagens = $this->contagens();

        // Pastas da UG ativa (para modal de arquivar no GED)
        $pastas = $ugId
            ? DB::table('ged_pastas')->where('ug_id', $ugId)->orderBy('parent_id')->orderBy('nome')
                ->get(['id','nome','parent_id','descricao'])
            : collect();

        return Inertia::render('GED/Flow/Inbox', [
            'vista'    => $vista,
            'items'    => $items,
            'filtros'  => [
                'busca'      => $busca,
                'tipo'       => $tipoFiltro,
                'nao_lidos'  => $somenteNaoLidos,
                'data_de'    => $dataDe,
                'data_ate'   => $dataAte,
            ],
            'contagens' => $contagens,
            'pastas'    => $pastas,
            'aviso_sem_unidade' => $avisoSemUnidade,
        ]);
    }

    /**
     * Contagens para badges do menu (todas as 7 vistas).
     */
    private function contagens(): array
    {
        $userId = (int) Auth::id();
        $user = Auth::user();
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        $base = DB::table('proc_inbox');
        $ugId = session('ug_id');
        if ($ugId) {
            $base->where('ug_id', $ugId);
        }

        $pessoal = (clone $base)
            ->where('destino_usuario_id', $userId)
            ->whereNotIn('status', self::ESTADOS_FINAIS)
            ->where('lido', false)
            ->count();

        if ($acessoGeral) {
            $setor = (clone $base)->whereNotNull('destino_unidade_id')->whereNull('destino_usuario_id')
                ->whereNotIn('status', self::ESTADOS_FINAIS)
                ->where('id', 'not like', 'MT-%')
                ->where('lido', false)->count();
        } elseif ($unidadeId) {
            $setor = (clone $base)->where('destino_unidade_id', $unidadeId)->whereNull('destino_usuario_id')
                ->whereNotIn('status', self::ESTADOS_FINAIS)
                ->where('lido', false)->count();
        } else {
            $setor = 0;
        }

        // Aguardando assinatura: processos com Assinatura pendente para mim
        $assinatura = DB::table('ged_assinaturas as a')
            ->join('proc_processos as p', 'p.solicitacao_assinatura_id', '=', 'a.solicitacao_id')
            ->where('a.signatario_id', $userId)
            ->where('a.status', 'pendente')
            ->where('p.status', 'aguardando_assinatura')
            ->when($ugId, fn ($q) => $q->where('p.ug_id', $ugId))
            ->count();

        return [
            'pessoal_nao_lidos'    => $pessoal,
            'setor_nao_lidos'      => $setor,
            'aguardando_assinatura'=> $assinatura,
        ];
    }
}
