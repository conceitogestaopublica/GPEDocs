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
            ->where(fn ($q) => self::doTitularOuEnvolvido($q, $cidadao))
            ->with(['servico:id,titulo,slug,setor_responsavel_id', 'cidadao:id,nome', 'procurador:id,nome'])
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
        return response()->json(['solicitacao' => $this->detalhe($this->doCidadao($request, $codigo))]);
    }

    /**
     * Acompanhamento sem login (TR 4.33.27): manifestação anônima ou sigilosa, pelo código e pela
     * chave dada na abertura. Não mostra quem abriu.
     */
    public function acompanhar(Request $request, string $codigo): JsonResponse
    {
        $ug = $this->ug($request);
        $chave = strtoupper(trim((string) $request->query('chave', '')));
        $solicitacao = $chave === '' ? null : Solicitacao::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ug->id)->where('codigo', $codigo)->where('chave_acesso', $chave)
            ->with(['servico:id,titulo,slug,setor_responsavel_id', 'eventos', 'envolvidos'])->first();
        if (! $solicitacao) {
            return response()->json(['erro' => 'Código ou chave não conferem.'], 404);
        }
        $dados = $this->detalhe($solicitacao);
        unset($dados['titular'], $dados['procurador'], $dados['envolvidos']);
        $dados['processo'] = $dados['processo'] ? array_diff_key($dados['processo'], ['avaliacao' => 1, 'localizacao' => 1]) : null;
        $dados['eventos'] = collect($dados['eventos'])->map(fn ($e) => $e['autor_tipo'] === 'cidadao' ? ['autor_nome' => 'Manifestante'] + $e : $e)->all();

        return response()->json(['solicitacao' => $dados]);
    }

    private function detalhe(Solicitacao $solicitacao): array
    {
        $processo = $solicitacao->processo_id
            ? Processo::query()->withoutGlobalScope('ug')->with('tipoProcesso:id,avaliacao,modalidade')
                ->find($solicitacao->processo_id, ['id', 'numero_protocolo', 'status', 'tipo_processo_id', 'latitude', 'longitude', 'local_endereco', 'reaberturas',
                    'prazo_legal_em', 'prorrogado_em', 'prorrogacao_motivo', 'decisao_final', 'decisao_final_em', 'concluido_em'])
            : null;

        return $this->resumo($solicitacao) + [
                'descricao' => $solicitacao->descricao,
                'resposta'  => $solicitacao->resposta,
                'envolvidos' => $solicitacao->envolvidos->map(fn ($p) => ['nome' => $p->nome, 'papel' => $p->papel])->values(),
                'anexos'    => $solicitacao->anexos->map(fn ($a) => [
                    'nome'    => $a->nome,
                    'tamanho' => $a->tamanho,
                ])->values(),
                'processo'  => $processo ? [
                    'numero_protocolo' => $processo->numero_protocolo,
                    'status'           => $processo->status,
                    // Prazo legal da ouvidoria / LAI e a decisão final (TR 4.33.23, 4.34)
                    'prazo_legal'      => \App\Support\Ouvidoria::situacaoPrazo($processo),
                    'prorrogacao'      => $processo->prorrogado_em ? ['em' => $processo->prorrogado_em->toIso8601String(), 'motivo' => $processo->prorrogacao_motivo] : null,
                    'decisao_final'    => $processo->decisao_final ? ['texto' => $processo->decisao_final, 'em' => $processo->decisao_final_em?->toIso8601String()] : null,
                    'localizacao'      => $processo->latitude !== null || $processo->local_endereco
                        ? ['latitude' => $processo->latitude, 'longitude' => $processo->longitude, 'endereco' => $processo->local_endereco] : null,
                    // Avaliação por estrelas depois da conclusão (TR 4.32.25–27): pode = falta avaliar esta rodada
                    'avaliacao'        => [
                        'pode'       => \App\Support\Avaliacoes::pode($processo),
                        'realizadas' => array_map(fn ($a) => ['nota' => $a['nota'], 'comentario' => $a['comentario'], 'rodada' => $a['rodada'], 'em' => $a['created_at']],
                            \App\Support\Avaliacoes::doProcesso($processo->id)),
                    ],
                ] : null,
                'eventos' => $solicitacao->eventos->map(fn (SolicitacaoEvento $e) => [
                    'tipo'        => $e->tipo,
                    'autor_tipo'  => $e->autor_tipo,
                    'autor_nome'  => $e->autor_nome,
                    // Setor e sigla de quem do GED interagiu (TR 4.23.3)
                    'setor'       => $e->setor_nome,
                    'setor_sigla' => $e->setor_sigla,
                    'status_novo' => $e->status_novo,
                    'mensagem'    => $e->mensagem,
                    'em'          => $e->created_at?->toIso8601String(),
                ])->values()->all(),
            ];
    }

    public function store(Request $request, RegistrarSolicitacaoService $registrar, AnexosSolicitacaoService $anexos): JsonResponse
    {
        $ug = $this->ug($request);
        // Manifestação anônima pode vir sem login (TR 4.33.27): o cidadão acompanha pelo código e pela chave
        $anonima = $request->input('identificacao') === 'anonima';
        $cidadao = $anonima && ! $request->filled('cidadao_id') ? null : $this->cidadao($request);
        $dados = $request->validate([
            'servico'          => ['required', 'string', 'max:200'],
            'descricao'        => ['required', 'string', 'max:5000'],
            'telefone_contato' => ['nullable', 'string', 'max:30'],
            'email_contato'    => ['nullable', 'email', 'max:150'],
            'anexos'            => ['nullable', 'array', 'max:' . AnexosSolicitacaoService::MAX_ARQUIVOS],
            'anexos.*.nome'     => ['required', 'string', 'max:200'],
            'anexos.*.conteudo' => ['required', 'string'],
            'procurador_cidadao_id' => ['nullable', 'integer'],
            // Formulário e documentos exigidos do assunto do serviço (TR 4.38.3–4.38.9, 4.38.18)
            'dados_formulario'               => ['nullable', 'array'],
            'documentos_exigidos'            => ['nullable', 'array', 'max:30'],
            'documentos_exigidos.*.indice'   => ['required', 'integer', 'min:0'],
            'documentos_exigidos.*.nome'     => ['nullable', 'string', 'max:200'],
            'documentos_exigidos.*.conteudo' => ['required', 'string'],
            // Pessoas envolvidas que acompanham pela Central (TR 4.23.9, 4.32.22)
            'envolvidos'         => ['nullable', 'array', 'max:10'],
            'envolvidos.*.nome'  => ['required', 'string', 'max:200'],
            'envolvidos.*.cpf'   => ['required', 'string', 'max:14', function ($a, $v, $falha) { if (! \App\Services\ConviteAssinaturaService::cpfValido(preg_replace('/\D/', '', (string) $v))) { $falha('CPF de pessoa envolvida inválido.'); } }],
            'envolvidos.*.email' => ['nullable', 'email', 'max:255'],
            'envolvidos.*.papel' => ['nullable', 'string', 'max:60'],
            // Ouvidoria (TR 4.33.8, 4.33.27)
            'manifestacao_tipo'  => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Ouvidoria::MANIFESTACOES))],
            'identificacao'      => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Ouvidoria::IDENTIFICACOES))],
        ]);

        // Por procuração: cidadao_id é o titular (outorgante) e quem protocola é o procurador.
        $procurador = null;
        if (! empty($dados['procurador_cidadao_id'])) {
            $procurador = Cidadao::where('id', $dados['procurador_cidadao_id'])->where('ativo', true)->first();
            if ($anonima || ! $procurador || $procurador->id === $cidadao?->id) {
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

        if ($anonima && ! $servico->permite_anonimo) {
            return response()->json(['erro' => 'Este serviço não aceita manifestação anônima.'], 422);
        }
        // Área do Servidor: só para servidor provado (entrou pelo usuário vindo do RH).
        if ($servico->categoria?->area === 'servidor' && (! $cidadao || ! CidadaoPortalService::paraApi($cidadao)['servidor'])) {
            return response()->json(['erro' => 'Serviço exclusivo para servidores municipais.'], 403);
        }

        // Assunto do serviço: o formulário segue as mesmas regras da abertura pelo GED
        $tipo = $servico->tipo_processo_id ? \App\Models\Processo\TipoProcesso::find($servico->tipo_processo_id) : null;
        if ($tipo?->schema_formulario) {
            [$regras, $nomes] = \App\Support\CamposDinamicos::regras($tipo->schema_formulario, 'dados_formulario');
            \Illuminate\Support\Facades\Validator::make($request->all(), $regras, [], $nomes)->validate();
            $dados['dados_formulario'] = \App\Support\CamposDinamicos::normalizar($tipo->schema_formulario, $request->input('dados_formulario'));
        } else {
            $dados['dados_formulario'] = null;
        }

        // Onde fica a demanda, conforme o assunto (TR 4.32.21, 4.33.9)
        $local = \App\Support\Avaliacoes::localizacao($request->input('localizacao'), (string) ($tipo?->localizacao ?? 'nao'));

        try {
            $prontos = [
                ...$anexos->prepararExigidos($tipo?->anexos_exigidos, $dados['documentos_exigidos'] ?? []),
                ...$anexos->preparar($dados['anexos'] ?? []),
            ];
            $anexos->conferirTotal($prontos);
        } catch (CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        $solicitacao = $registrar->registrar($ug, $servico, $cidadao, $dados, anonima: $anonima, procurador: $procurador);
        if ($solicitacao->processo_id && ($local['latitude'] !== null || $local['local_endereco'] !== null)) {
            Processo::query()->withoutGlobalScope('ug')->whereKey($solicitacao->processo_id)->update($local);
        }
        if ($prontos) {
            $anexos->gravar($solicitacao, $procurador ?? $cidadao, $prontos);
        }
        foreach ($dados['envolvidos'] ?? [] as $p) {
            \App\Models\Portal\SolicitacaoEnvolvido::firstOrCreate(['solicitacao_id' => $solicitacao->id, 'cpf' => preg_replace('/\D/', '', $p['cpf'])],
                ['nome' => $p['nome'], 'email' => $p['email'] ?? null, 'papel' => $p['papel'] ?? null]);
        }

        // Comprovante logo após abrir: código, setor que vai atender e número do processo (TR 4.32.11)
        return response()->json(['solicitacao' => $this->resumo($solicitacao->load(['servico:id,titulo,slug,setor_responsavel_id', 'cidadao:id,nome', 'procurador:id,nome'])) + [
            'comprovante_url' => url("/api/portal/solicitacoes/{$solicitacao->codigo}/comprovante"),
            // Anônima ou sigilosa: com a chave, acompanha sem login em GET acompanhar/{codigo}?chave=
            'chave_acesso'    => in_array($solicitacao->identificacao, ['anonima', 'sigilosa'], true) ? $solicitacao->chave_acesso : null,
        ]], 201);
    }

    /** Cidadão avalia o atendimento concluído (1 a 5 estrelas e comentário). */
    public function avaliar(Request $request, string $codigo): JsonResponse
    {
        $solicitacao = $this->doCidadao($request, $codigo);
        $d = $request->validate(['nota' => ['required', 'integer', 'between:1,5'], 'comentario' => ['nullable', 'string', 'max:2000']]);
        $processo = $solicitacao->processo_id ? Processo::query()->withoutGlobalScope('ug')->with('tipoProcesso')->find($solicitacao->processo_id) : null;
        if (! $processo) {
            return response()->json(['erro' => 'Esta solicitação não tem atendimento para avaliar.'], 422);
        }
        \App\Support\Avaliacoes::registrar($processo, (int) $d['nota'], $d['comentario'] ?? null, 'portal', $this->cidadao($request)->id);

        return response()->json(['ok' => true], 201);
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
            ->where(fn ($q) => self::doTitularOuEnvolvido($q, $cidadao))
            ->where('codigo', $codigo)
            ->with(['servico:id,titulo,slug,setor_responsavel_id', 'eventos', 'envolvidos', 'cidadao:id,nome', 'procurador:id,nome'])
            ->firstOrFail();
    }

    /**
     * Cidadão (titular, procurador ou envolvido) escreve e anexa depois de abrir (TR 4.32.23).
     * Quem atende é avisado; os anexos vão também para o processo.
     */
    public function mensagem(Request $request, string $codigo, AnexosSolicitacaoService $anexos): JsonResponse
    {
        $solicitacao = $this->doCidadao($request, $codigo);
        $cidadao = $this->cidadao($request);
        $dados = $request->validate([
            'texto'             => ['required_without:anexos', 'nullable', 'string', 'max:5000'],
            'anexos'            => ['nullable', 'array', 'max:' . AnexosSolicitacaoService::MAX_ARQUIVOS],
            'anexos.*.nome'     => ['required', 'string', 'max:200'],
            'anexos.*.conteudo' => ['required', 'string'],
        ]);
        if (in_array($solicitacao->status, ['cancelada'], true)) {
            return response()->json(['erro' => 'Solicitação cancelada não recebe mensagens.'], 422);
        }
        try {
            $prontos = $anexos->preparar($dados['anexos'] ?? []);
            $anexos->conferirTotal($prontos);
        } catch (CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        DB::transaction(function () use ($solicitacao, $cidadao, $dados, $prontos, $anexos) {
            SolicitacaoEvento::create([
                'solicitacao_id' => $solicitacao->id, 'tipo' => 'comentario', 'autor_tipo' => 'cidadao', 'autor_nome' => $cidadao->nome,
                'autor_cidadao_id' => $cidadao->id,
                'mensagem' => trim(($dados['texto'] ?? '') . ($prontos ? "\n(" . count($prontos) . ' anexo(s))' : '')),
            ]);
            if ($prontos) {
                $anexos->gravar($solicitacao, $cidadao, $prontos);
            }
        });

        // Avisa quem atende: o atendente da solicitação ou quem está com o processo
        $avisar = $solicitacao->atendente_id ? [$solicitacao->atendente_id] : [];
        if (! $avisar && $solicitacao->processo_id) {
            $etapa = \App\Models\Processo\Tramitacao::where('processo_id', $solicitacao->processo_id)->where('frente', 1)->orderByDesc('ordem')->first();
            $avisar = $etapa?->destinatario_id ? [$etapa->destinatario_id] : ($etapa?->destino_unidade_id ? \App\Support\Setores::servidores((int) $etapa->destino_unidade_id) : []);
        }
        foreach (array_unique($avisar) as $uid) {
            \App\Models\Notificacao::create(['usuario_id' => $uid, 'tipo' => 'processo', 'titulo' => 'Mensagem do cidadão',
                'mensagem' => "{$cidadao->nome} escreveu na solicitação {$solicitacao->codigo}" . ($prontos ? ' e anexou ' . count($prontos) . ' arquivo(s)' : '') . '.',
                'referencia_tipo' => $solicitacao->processo_id ? 'processo' : null, 'referencia_id' => $solicitacao->processo_id, 'lida' => false]);
        }

        return response()->json(['ok' => true], 201);
    }

    /** Comprovante da solicitação em PDF (TR 4.32.11): código, setor de destino, processo e anexos. */
    public function comprovante(Request $request, string $codigo)
    {
        $s = $this->doCidadao($request, $codigo);
        $s->load('anexos', 'servico:id,titulo,setor_responsavel_id', 'cidadao:id,nome,cpf', 'ug');   // load: o cidadão já veio sem o CPF

        $link = 'http://' . $s->ug?->portal_slug . '.' . parse_url((string) config('app.url'), PHP_URL_HOST) . "/minhas-solicitacoes/{$s->id}";

        return \App\Support\Relatorio::pdf('pdf.comprovante-solicitacao', [
            'solicitacao' => $s, 'resumo' => $this->resumo($s), 'ug' => $s->ug, 'link' => $link,
            // QR code para abrir o acompanhamento no celular (TR 4.36.3)
            'qr' => 'data:image/svg+xml;base64,' . base64_encode(\App\Support\Totp::qrSvg($link)),
        ])->stream("comprovante-{$s->codigo}.pdf");
    }

    /**
     * Processos abertos em nome do cidadão — inclusive no balcão, pelo atendente (TR 4.32.1):
     * o CPF do requerente é o do cidadão logado.
     */
    public function processos(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $cpf = preg_replace('/\D/', '', (string) $this->cidadao($request)->cpf);
        if (strlen($cpf) !== 11) {
            return response()->json(['processos' => []]);
        }
        $lista = Processo::query()->withoutGlobalScope('ug')->with('tipoProcesso:id,nome')
            ->where('ug_id', $ug->id)->whereRaw("regexp_replace(coalesce(requerente_cpf, ''), '\D', '', 'g') = ?", [$cpf])
            ->orderByDesc('id')->limit(200)->get(['id', 'numero_protocolo', 'assunto', 'status', 'tipo_processo_id', 'created_at']);

        return response()->json(['processos' => $lista->map(fn (Processo $p) => [
            'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'tipo' => $p->tipoProcesso?->nome, 'status' => $p->status,
            'aberto_em' => $p->created_at?->toIso8601String(), 'setor_atual' => self::setorAtual($p->id),
        ])->values()]);
    }

    /** Andamento de um processo do cidadão: por onde passou (setor e sigla), sem o conteúdo interno. */
    public function processo(Request $request, string $numero): JsonResponse
    {
        $ug = $this->ug($request);
        $cpf = preg_replace('/\D/', '', (string) $this->cidadao($request)->cpf);
        $p = Processo::query()->withoutGlobalScope('ug')->with('tipoProcesso:id,nome')->where('ug_id', $ug->id)
            ->where('numero_protocolo', $numero)->whereRaw("regexp_replace(coalesce(requerente_cpf, ''), '\D', '', 'g') = ?", [$cpf])->firstOrFail();
        $etapas = \App\Models\Processo\Tramitacao::with('destinoUnidade:id,nome,sigla')->where('processo_id', $p->id)->orderBy('ordem')->orderBy('id')->get();

        return response()->json(['processo' => [
            'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'tipo' => $p->tipoProcesso?->nome, 'status' => $p->status,
            'aberto_em' => $p->created_at?->toIso8601String(), 'concluido_em' => $p->concluido_em?->toIso8601String(),
            'andamento' => $etapas->map(fn ($t) => ['setor' => $t->destinoUnidade?->nome ?? $t->setor_destino, 'sigla' => $t->destinoUnidade?->sigla,
                'situacao' => $t->status, 'desde' => ($t->despachado_em ?? $t->created_at)?->toIso8601String()])->values(),
        ]]);
    }

    private static function setorAtual(int $processoId): ?array
    {
        $t = \App\Models\Processo\Tramitacao::with('destinoUnidade:id,nome,sigla')->where('processo_id', $processoId)->where('frente', 1)->orderByDesc('ordem')->orderByDesc('id')->first();

        return $t ? ['nome' => $t->destinoUnidade?->nome ?? $t->setor_destino, 'sigla' => $t->destinoUnidade?->sigla] : null;
    }

    /** Titular, procurador ou pessoa envolvida (pelo CPF) — TR 4.23.9. */
    private static function doTitularOuEnvolvido($q, Cidadao $cidadao): void
    {
        $q->where('cidadao_id', $cidadao->id)->orWhere('procurador_cidadao_id', $cidadao->id);
        if ($cidadao->cpf) {
            $q->orWhereHas('envolvidos', fn ($e) => $e->where('cpf', preg_replace('/\D/', '', (string) $cidadao->cpf)));
        }
    }

    /** Setor que vai atender: o do processo (etapa atual) ou o responsável pelo serviço. */
    private static function setorDestino(Solicitacao $s): ?array
    {
        $id = $s->processo_id ? \App\Models\Processo\Tramitacao::where('processo_id', $s->processo_id)->where('frente', 1)->orderByDesc('ordem')->value('destino_unidade_id') : null;
        $id ??= $s->servico?->setor_responsavel_id;
        $u = $id ? \App\Models\UgOrganograma::find($id, ['nome', 'sigla', 'parent_id']) : null;
        // Setor e o setor acima dele (ex.: Secretaria de Administração › Gestão de Pessoas) — TR 4.36.5
        $superior = $u?->parent_id ? \App\Models\UgOrganograma::whereKey($u->parent_id)->value('nome') : null;

        return $u ? ['nome' => $u->nome, 'sigla' => $u->sigla, 'superior' => $superior] : null;
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
            'processo_numero' => $s->processo_id ? Processo::query()->withoutGlobalScope('ug')->whereKey($s->processo_id)->value('numero_protocolo') : null,
            'setor_destino'  => self::setorDestino($s),
            'titular'        => $s->cidadao ? ['id' => $s->cidadao->id, 'nome' => $s->cidadao->nome] : null,
            'procurador'     => $s->procurador ? ['id' => $s->procurador->id, 'nome' => $s->procurador->nome] : null,
            'manifestacao_tipo' => $s->manifestacao_tipo,
            'manifestacao'   => \App\Support\Ouvidoria::MANIFESTACOES[$s->manifestacao_tipo] ?? null,
            'identificacao'  => $s->identificacao,
        ];
    }
}
