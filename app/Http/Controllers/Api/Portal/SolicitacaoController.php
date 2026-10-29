<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Portal\Cidadao;
use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoEvento;
use App\Models\Processo\Processo;
use App\Services\Portal\AnexosSolicitacaoService;
use App\Services\Portal\CidadaoPortalException;
use App\Services\Portal\CidadaoPortalService;
use App\Services\Portal\RegistrarSolicitacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Solicitações do cidadão pelo Portal do Cidadão. A solicitação é a mesma do
 * portal embutido: vira processo (quando o serviço tem tipo e setor) e o
 * andamento volta sozinho (SincronizarPortalCidadaoService).
 *
 * `cidadao_id` vem da sessão do PORTAL (já autenticado aqui pela senha) —
 * toda leitura e escrita é escopada por ele e pela UG.
 */
class SolicitacaoController extends PortalApiController
{
    public function index(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $cidadao = $this->cidadao($request);

        $lista = Solicitacao::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ug->id)
            ->where(fn ($q) => $q->where('cidadao_id', $cidadao->id)->orWhere('procurador_cidadao_id', $cidadao->id))
            ->with(['servico:id,titulo,slug', 'cidadao:id,nome', 'procurador:id,nome'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Número do processo de cada uma, numa consulta só (a grade do portal mostra).
        $numeros = Processo::query()->withoutGlobalScope('ug')
            ->whereIn('id', $lista->pluck('processo_id')->filter())
            ->pluck('numero_protocolo', 'id');

        return response()->json([
            'solicitacoes' => $lista->map(fn (Solicitacao $s) => $this->resumo($s) + [
                'processo_numero' => $numeros[$s->processo_id] ?? null,
            ])->values(),
        ]);
    }

    public function show(Request $request, string $codigo): JsonResponse
    {
        $solicitacao = $this->doCidadao($request, $codigo);
        $processo = $solicitacao->processo_id
            ? Processo::query()->withoutGlobalScope('ug')
                ->find($solicitacao->processo_id, ['id', 'numero_protocolo', 'status'])
            : null;

        return response()->json([
            'solicitacao' => $this->resumo($solicitacao) + [
                'descricao' => $solicitacao->descricao,
                'resposta'  => $solicitacao->resposta,
                'anexos'    => $solicitacao->anexos->map(fn ($a) => [
                    'nome'    => $a->nome,
                    'tamanho' => $a->tamanho,
                ])->values(),
                'processo'  => $processo ? [
                    'numero_protocolo' => $processo->numero_protocolo,
                    'status'           => $processo->status,
                ] : null,
                'eventos' => $solicitacao->eventos->map(fn (SolicitacaoEvento $e) => [
                    'tipo'        => $e->tipo,
                    'autor_tipo'  => $e->autor_tipo,
                    'autor_nome'  => $e->autor_nome,
                    'status_novo' => $e->status_novo,
                    'mensagem'    => $e->mensagem,
                    'em'          => $e->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, RegistrarSolicitacaoService $registrar, AnexosSolicitacaoService $anexos): JsonResponse
    {
        $ug = $this->ug($request);
        $cidadao = $this->cidadao($request);
        $dados = $request->validate([
            'servico'          => ['required', 'string', 'max:200'],
            'descricao'        => ['required', 'string', 'max:5000'],
            'telefone_contato' => ['nullable', 'string', 'max:30'],
            'email_contato'    => ['nullable', 'email', 'max:150'],
            'anexos'            => ['nullable', 'array', 'max:' . AnexosSolicitacaoService::MAX_ARQUIVOS],
            'anexos.*.nome'     => ['required', 'string', 'max:200'],
            'anexos.*.conteudo' => ['required', 'string'],
            'procurador_cidadao_id' => ['nullable', 'integer'],
        ]);

        // Por procuração: cidadao_id é o titular (outorgante) e quem protocola é o procurador.
        $procurador = null;
        if (! empty($dados['procurador_cidadao_id'])) {
            $procurador = Cidadao::where('id', $dados['procurador_cidadao_id'])->where('ativo', true)->first();
            if (! $procurador || $procurador->id === $cidadao->id) {
                return response()->json(['erro' => 'Procurador inválido.'], 422);
            }
        }

        $servico = Servico::query()->withoutGlobalScope('ug')
            ->with('categoria:id,area')
            ->where('ug_id', $ug->id)
            ->where('slug', $dados['servico'])
            ->where('publicado', true)
            ->first();
        if (! $servico) {
            return response()->json(['erro' => 'Serviço não encontrado.'], 404);
        }
        if ($servico->acao_fiscal) {
            // Autoatendimento no tributário não vira solicitação.
            return response()->json(['erro' => 'Este serviço é feito direto no portal, sem solicitação.'], 422);
        }

        // Área do Servidor: só para servidor provado (entrou pelo usuário vindo do RH).
        if ($servico->categoria?->area === 'servidor' && ! CidadaoPortalService::paraApi($cidadao)['servidor']) {
            return response()->json(['erro' => 'Serviço exclusivo para servidores municipais.'], 403);
        }

        try {
            $prontos = $anexos->preparar($dados['anexos'] ?? []);
        } catch (CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        $solicitacao = $registrar->registrar($ug, $servico, $cidadao, $dados, procurador: $procurador);
        if ($prontos) {
            $anexos->gravar($solicitacao, $procurador ?? $cidadao, $prontos);
        }

        return response()->json(['solicitacao' => $this->resumo($solicitacao->load(['servico:id,titulo,slug', 'cidadao:id,nome', 'procurador:id,nome']))], 201);
    }

    public function cancelar(Request $request, string $codigo): JsonResponse
    {
        $solicitacao = $this->doCidadao($request, $codigo);
        if (in_array($solicitacao->status, Solicitacao::STATUS_FINAIS, true)) {
            return response()->json(['erro' => 'Solicitação já finalizada não pode ser cancelada.'], 422);
        }
        $cidadao = $this->cidadao($request);   // titular ou procurador: quem pediu o cancelamento

        DB::transaction(function () use ($solicitacao, $cidadao) {
            $statusAnterior = $solicitacao->status;
            $solicitacao->update(['status' => 'cancelada']);
            SolicitacaoEvento::create([
                'solicitacao_id'   => $solicitacao->id,
                'tipo'             => 'cancelada',
                'autor_tipo'       => 'cidadao',
                'autor_nome'       => $cidadao?->nome ?? 'Cidadao',
                'autor_cidadao_id' => $cidadao?->id,
                'status_anterior'  => $statusAnterior,
                'status_novo'      => 'cancelada',
                'mensagem'         => 'Cancelada pelo cidadão.',
            ]);
        });

        return response()->json(['solicitacao' => $this->resumo($solicitacao->refresh())]);
    }

    private function cidadao(Request $request): Cidadao
    {
        $id = (int) $request->input('cidadao_id', 0);
        $cidadao = $id > 0 ? Cidadao::where('id', $id)->where('ativo', true)->first() : null;
        abort_unless($cidadao, 404, 'Cidadão não encontrado.');

        return $cidadao;
    }

    private function doCidadao(Request $request, string $codigo): Solicitacao
    {
        $ug = $this->ug($request);
        $cidadao = $this->cidadao($request);

        return Solicitacao::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ug->id)
            ->where(fn ($q) => $q->where('cidadao_id', $cidadao->id)->orWhere('procurador_cidadao_id', $cidadao->id))
            ->where('codigo', $codigo)
            ->with(['servico:id,titulo,slug', 'eventos', 'cidadao:id,nome', 'procurador:id,nome'])
            ->firstOrFail();
    }

    private function resumo(Solicitacao $s): array
    {
        return [
            'codigo'         => $s->codigo,
            'status'         => $s->status,
            'status_rotulo'  => Solicitacao::STATUS[$s->status] ?? $s->status,
            'servico'        => $s->servico ? ['slug' => $s->servico->slug, 'titulo' => $s->servico->titulo] : null,
            'aberta_em'      => $s->created_at?->toIso8601String(),
            'respondida_em'  => $s->respondida_em?->toIso8601String(),
            'tem_processo'   => $s->processo_id !== null,
            'titular'        => $s->cidadao ? ['id' => $s->cidadao->id, 'nome' => $s->cidadao->nome] : null,
            'procurador'     => $s->procurador ? ['id' => $s->procurador->id, 'nome' => $s->procurador->nome] : null,
        ];
    }
}
