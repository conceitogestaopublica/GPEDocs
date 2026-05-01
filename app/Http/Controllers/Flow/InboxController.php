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
 * GPE Flow — Inbox unificada (memorando + circular + oficio + processo).
 *
 * Le da view SQL `proc_inbox` que une os 4 tipos por destino (usuario ou
 * unidade do organograma) e mostra como uma unica caixa de correspondencias.
 *
 * Vistas:
 *   - pessoal()      itens enderecados ao usuario logado
 *   - setor()        itens enderecados a unidade do usuario logado
 *   - encaminhados() itens que o usuario logado enviou (e remetente)
 *   - rascunhos()    itens com status='rascunho' criados pelo usuario
 *   - arquivados()   itens com status='arquivado' que tocaram o usuario
 */
class InboxController extends Controller
{
    public function pessoal(Request $request): Response
    {
        $userId = (int) Auth::id();
        return $this->render($request, 'pessoal',
            fn ($q) => $q->where('destino_usuario_id', $userId)
        );
    }

    public function setor(Request $request): Response
    {
        $user = Auth::user();
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        // Acesso geral: ve itens em "primeiro recebimento" da UG ativa (originais ainda
        // nao tramitados E primeiras tramitacoes que chegaram em algum setor).
        // Itens ja tramitados a partir de algum setor migram para a aba Tramitacao.
        if ($acessoGeral) {
            return $this->render($request, 'setor',
                fn ($q) => $q->whereNotNull('destino_unidade_id')
                             ->whereNull('destino_usuario_id')
                             ->where('id', 'not like', 'MT-%') // exclui tramitacoes de memorando
            );
        }

        // Sem acesso geral e sem unidade: caixa vazia + aviso para o admin vincular
        if (! $unidadeId) {
            return $this->render($request, 'setor', fn ($q) => $q->whereRaw('1 = 0'),
                avisoSemUnidade: true);
        }

        // Padrao: ve apenas a unidade onde esta lotado (incluindo tramitacoes que chegaram aqui)
        return $this->render($request, 'setor',
            fn ($q) => $q->where('destino_unidade_id', $unidadeId)
                         ->whereNull('destino_usuario_id')
        );
    }

    /**
     * Saida — itens que o usuario originou (criou e despachou pela primeira vez).
     */
    public function saida(Request $request): Response
    {
        $userId = (int) Auth::id();
        // Saida = itens que NAO sao tramitacao (so aparece o estagio inicial — destinos originais)
        // Cada tipo distingue diferentemente: para memorando o item original (sem tramite) tem id 'M-...';
        // para tramite o id comeca com 'MT-' ou (P-...). Filtramos pelo prefixo do id.
        return $this->render($request, 'saida',
            fn ($q) => $q->where('remetente_id', $userId)
                         ->where(function ($q) {
                             $q->where('id', 'like', 'M-%')      // memorando original
                               ->orWhere('id', 'like', 'O-%')    // oficio
                               ->orWhere('id', 'like', 'P-%');   // processo (todas tramitacoes — remetente_id = quem enviou esse passo)
                         })
        );
    }

    /**
     * Tramitacao — itens com chain de encaminhamentos onde o usuario participou
     * como origem (recebeu e tramitou pra frente).
     */
    public function tramitacao(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) $user->id;
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        return $this->render($request, 'tramitacao',
            fn ($q) => $q->where('id', 'like', 'MT-%')
                         ->where(function ($q) use ($userId, $unidadeId, $acessoGeral) {
                             if ($acessoGeral) {
                                 // Ve todas tramitacoes da UG ativa (filtro de UG ja vem do scope)
                                 $q->whereRaw('1 = 1');
                             } else {
                                 // Apenas onde participou: origem ou destino atual
                                 $q->where('remetente_id', $userId);
                                 if ($unidadeId) {
                                     $q->orWhere('destino_unidade_id', $unidadeId);
                                 }
                                 $q->orWhere('destino_usuario_id', $userId);
                             }
                         })
        );
    }

    public function rascunhos(Request $request): Response
    {
        $userId = (int) Auth::id();
        return $this->render($request, 'rascunhos',
            fn ($q) => $q->where('remetente_id', $userId)->where('status', 'rascunho')
        );
    }

    public function arquivados(Request $request): Response
    {
        $userId = (int) Auth::id();
        $user = Auth::user();
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        return $this->render($request, 'arquivados',
            fn ($q) => $q->whereNotNull('arquivado_em')
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

    private function render(Request $request, string $vista, \Closure $escopoFn, bool $avisoSemUnidade = false): Response
    {
        $busca = trim((string) $request->input('busca', ''));
        $tipoFiltro = $request->input('tipo'); // null|memorando|oficio|processo
        $somenteNaoLidos = $request->boolean('nao_lidos');

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

        // Filtro multi-tenant: ja vem na view via ug_id
        $ugId = session('ug_id');
        if ($ugId && ! Auth::user()->super_admin) {
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

        $items->setCollection($items->getCollection()->map(function ($it) use ($users, $unidades) {
            $it->remetente_nome = $users[$it->remetente_id]->name ?? null;
            $it->destino_usuario_nome = $it->destino_usuario_id ? ($users[$it->destino_usuario_id]->name ?? null) : null;
            $it->destino_unidade_nome = $it->destino_unidade_id ? ($unidades[$it->destino_unidade_id]->nome ?? null) : null;
            return $it;
        }));

        // Contagem para os badges do menu
        $contagens = $this->contagens();

        return Inertia::render('GED/Flow/Inbox', [
            'vista'    => $vista,
            'items'    => $items,
            'filtros'  => [
                'busca'      => $busca,
                'tipo'       => $tipoFiltro,
                'nao_lidos'  => $somenteNaoLidos,
            ],
            'contagens' => $contagens,
            'aviso_sem_unidade' => $avisoSemUnidade,
        ]);
    }

    /**
     * Conta itens em cada vista (para badges do menu).
     */
    private function contagens(): array
    {
        $userId = (int) Auth::id();
        $user = Auth::user();
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        $base = DB::table('proc_inbox');
        $ugId = session('ug_id');
        if ($ugId && ! $user->super_admin) {
            $base->where('ug_id', $ugId);
        }

        $pessoal = (clone $base)->where('destino_usuario_id', $userId)->where('lido', false)->count();

        if ($acessoGeral) {
            $setor = (clone $base)->whereNotNull('destino_unidade_id')->whereNull('destino_usuario_id')
                ->where('id', 'not like', 'MT-%')
                ->where('lido', false)->count();
        } elseif ($unidadeId) {
            $setor = (clone $base)->where('destino_unidade_id', $unidadeId)->whereNull('destino_usuario_id')->where('lido', false)->count();
        } else {
            $setor = 0;
        }

        return [
            'pessoal_nao_lidos' => $pessoal,
            'setor_nao_lidos'   => $setor,
        ];
    }
}
