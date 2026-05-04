<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\Notificacao;
use App\Mail\NotificacaoSolicitacaoCidadao;
use App\Models\Portal\Solicitacao as PortalSolicitacao;
use App\Models\Portal\SolicitacaoEvento as PortalEvento;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\ProcessoHistorico;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use App\Models\Processo\Tramitacao;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use App\Models\Versao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProcessoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Processo::with(['tipoProcesso', 'abertoPor', 'etapaAtual'])
            ->whereNull('deleted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tipo_processo_id')) {
            $query->where('tipo_processo_id', $request->input('tipo_processo_id'));
        }

        if ($request->filled('prioridade')) {
            $query->where('prioridade', $request->input('prioridade'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('numero_protocolo', 'ilike', "%{$search}%")
                  ->orWhere('assunto', 'ilike', "%{$search}%");
            });
        }

        $processos = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $tiposProcesso = TipoProcesso::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']);

        return Inertia::render('GED/Processos/Index', [
            'processos'      => $processos,
            'tipos_processo'  => $tiposProcesso,
            'filters'        => $request->only(['search', 'status', 'tipo_processo_id', 'prioridade']),
        ]);
    }

    public function create(): Response
    {
        $tiposProcesso = TipoProcesso::where('ativo', true)
            ->with('etapas')
            ->orderBy('nome')
            ->get();

        $ugId = session('ug_id');
        $unidades = \App\Models\UgOrganograma::query()
            ->when($ugId, fn ($q) => $q->where('ug_id', $ugId))
            ->where('ativo', true)
            ->orderBy('nivel')
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo', 'nivel', 'parent_id']);

        $usuarios = User::where('id', '!=', Auth::id())
            ->when($ugId, fn ($q) => $q->where(function ($q) use ($ugId) {
                $q->whereHas('ugs', fn ($q) => $q->where('ugs.id', $ugId))
                  ->orWhere('ug_id', $ugId);
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'unidade_id']);

        return Inertia::render('GED/Processos/Create', [
            'tipos_processo' => $tiposProcesso,
            'unidades'       => $unidades,
            'usuarios'       => $usuarios,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto'            => ['required', 'string', 'max:255'],
            'tipo_processo_id'   => ['required', 'integer', 'exists:proc_tipos_processo,id'],
            'descricao'          => ['nullable', 'string'],
            'dados_formulario'   => ['nullable', 'array'],
            'prioridade'         => ['nullable', 'string', 'in:baixa,normal,alta,urgente'],
            'requerente_nome'    => ['nullable', 'string', 'max:255'],
            'requerente_cpf'     => ['nullable', 'string', 'max:20'],
            'requerente_email'   => ['nullable', 'string', 'email', 'max:255'],
            'requerente_telefone'=> ['nullable', 'string', 'max:20'],
            'setor_origem'       => ['nullable', 'string', 'max:150'],
            'setor_destino_inicial' => ['required', 'integer', 'exists:ug_organograma,id'],
            'destinatario_inicial'  => ['nullable', 'integer', 'exists:users,id'],
            'files'              => ['nullable', 'array'],
            'files.*'            => ['file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $tipoId = (int) $request->input('tipo_processo_id');
            $tipo = TipoProcesso::findOrFail($tipoId);
            $year = date('Y');

            $lastNum = Processo::where('tipo_processo_id', $tipoId)
                ->whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero_protocolo, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $protocolo = $tipo->sigla . '-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $primeiraEtapa = TipoEtapa::where('tipo_processo_id', $tipoId)
                ->orderBy('ordem')
                ->first();

            $processo = Processo::create([
                'numero_protocolo'  => $protocolo,
                'tipo_processo_id'  => $tipoId,
                'assunto'           => $request->input('assunto'),
                'descricao'         => $request->input('descricao'),
                'dados_formulario'  => $request->input('dados_formulario'),
                'prioridade'        => $request->input('prioridade', 'normal'),
                'requerente_nome'   => $request->input('requerente_nome'),
                'requerente_cpf'    => $request->input('requerente_cpf'),
                'requerente_email'  => $request->input('requerente_email'),
                'requerente_telefone' => $request->input('requerente_telefone'),
                'setor_origem'      => $request->input('setor_origem'),
                'status'            => 'aberto',
                'aberto_por'        => Auth::id(),
            ]);

            // Primeira tramitacao ja vai despachada para o setor escolhido na abertura
            $unidadeDestino = \App\Models\UgOrganograma::find((int) $request->input('setor_destino_inicial'));

            $tramitacao = Tramitacao::create([
                'processo_id'        => $processo->id,
                'tipo_etapa_id'      => $primeiraEtapa?->id,
                'ordem'              => 1,
                'setor_origem'       => $request->input('setor_origem'),
                'setor_destino'      => $unidadeDestino?->nome,
                'destino_unidade_id' => $unidadeDestino?->id,
                'remetente_id'       => Auth::id(),
                'destinatario_id'    => $request->input('destinatario_inicial') ?: $primeiraEtapa?->responsavel_id,
                'status'             => 'pendente',
                'despachado_em'      => now(),
                'sla_horas'          => $primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo'              => now()->addHours($primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas),
            ]);

            $processo->update(['etapa_atual_id' => $tramitacao->id]);

            // Armazenar anexos
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('processos', 'documentos');

                    ProcessoAnexo::create([
                        'processo_id'   => $processo->id,
                        'tramitacao_id' => $tramitacao->id,
                        'nome'          => $file->getClientOriginalName(),
                        'arquivo_path'  => $path,
                        'tamanho'       => $file->getSize(),
                        'mime_type'     => $file->getMimeType(),
                        'hash_sha256'   => hash_file('sha256', $file->getRealPath()),
                        'enviado_por'   => Auth::id(),
                    ]);
                }
            }

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'abertura',
                'detalhes'    => [
                    'numero_protocolo' => $protocolo,
                    'assunto'          => $processo->assunto,
                    'tipo'             => $tipo->nome,
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Notificar destino (usuario especifico OU todos do setor)
            $usuariosNotificar = [];
            if ($request->filled('destinatario_inicial')) {
                $usuariosNotificar[] = (int) $request->input('destinatario_inicial');
            } elseif ($unidadeDestino) {
                $usuariosNotificar = User::where('unidade_id', $unidadeDestino->id)->pluck('id')->all();
            }
            foreach (array_unique($usuariosNotificar) as $uid) {
                Notificacao::create([
                    'usuario_id'     => (int) $uid,
                    'tipo'           => 'processo',
                    'titulo'         => 'Novo processo recebido',
                    'mensagem'       => "Processo {$protocolo} - {$processo->assunto} foi encaminhado para voce.",
                    'referencia_tipo'=> 'processo',
                    'referencia_id'  => $processo->id,
                    'lida'           => false,
                ]);
            }

            DB::commit();

            return redirect("/processos/{$processo->id}")->with('success', 'Processo aberto com sucesso. Protocolo: ' . $protocolo);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao abrir processo: ' . $e->getMessage());
        }
    }

    public function show($id): Response
    {
        $processo = Processo::with([
            'tramitacoes.remetente',
            'tramitacoes.destinatario',
            'tramitacoes.recebedor',
            'anexos.enviadoPor',
            'comentarios.usuario',
            'historico.usuario',
            'tipoProcesso.etapas',
            'abertoPor',
            'concluidoPor',
        ])->findOrFail($id);

        $ugId = session('ug_id');
        $usuarios = User::where('id', '!=', Auth::id())
            ->when($ugId, fn ($q) => $q->where(function ($q) use ($ugId) {
                $q->whereHas('ugs', fn ($q) => $q->where('ugs.id', $ugId))
                  ->orWhere('ug_id', $ugId);
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'unidade_id']);

        $unidades = \App\Models\UgOrganograma::query()
            ->when($ugId, fn ($q) => $q->where('ug_id', $ugId))
            ->where('ativo', true)
            ->orderBy('nivel')
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo', 'nivel', 'parent_id']);

        // Determina o que o usuario logado pode fazer no estado atual
        $user = Auth::user();
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        $etapaAtual = $processo->tramitacoes
            ->whereIn('status', ['pendente', 'recebido'])
            ->sortByDesc('id')
            ->first();

        $souAtivoNaEtapa = $etapaAtual && (
            $etapaAtual->destinatario_id === $user->id
            || ($unidadeId && $etapaAtual->destino_unidade_id === $unidadeId)
            || ($acessoGeral && $etapaAtual->destino_unidade_id !== null)
        );

        $podeReceber = $etapaAtual && $etapaAtual->status === 'pendente' && $souAtivoNaEtapa;
        $podeDespachar = $etapaAtual && $etapaAtual->status === 'recebido' && $souAtivoNaEtapa;
        // So pode concluir/cancelar quem esta na etapa ativa OU o autor (caso ainda nao tenha sido despachado)
        $souAutor = $processo->aberto_por === $user->id;
        $semDespachoAinda = $processo->tramitacoes->count() <= 1 && $etapaAtual && $etapaAtual->status === 'pendente';
        $podeConcluir = $souAtivoNaEtapa || ($souAutor && $semDespachoAinda);

        // Se ha solicitacao de assinatura pendente, passa a assinatura do usuario logado
        $assinaturaPendente = null;
        $decisaoAssinada = null;
        if ($processo->solicitacao_assinatura_id && $processo->status === 'aguardando_assinatura') {
            $solicitacao = \App\Models\SolicitacaoAssinatura::with('documento')->find($processo->solicitacao_assinatura_id);
            if ($solicitacao) {
                $minha = \App\Models\Assinatura::where('solicitacao_id', $solicitacao->id)
                    ->where('signatario_id', Auth::id())
                    ->where('status', 'pendente')
                    ->first();
                if ($minha) {
                    $assinaturaPendente = [
                        'id'            => $minha->id,
                        'solicitacao_id'=> $solicitacao->id,
                        'documento_id'  => $solicitacao->documento_id,
                        'documento_nome'=> $solicitacao->documento?->nome,
                        'mensagem'      => $solicitacao->mensagem,
                    ];
                }
            }
        }

        // Para processos concluidos: monta info da decisao para banner + botoes de
        // download/arquivar (incluindo arquivamento simples sem assinatura).
        if ($processo->status === 'concluido') {
            $documentoId = $processo->documento_decisao_id;
            if (! $documentoId && $processo->solicitacao_assinatura_id) {
                $documentoId = \App\Models\SolicitacaoAssinatura::where('id', $processo->solicitacao_assinatura_id)
                    ->value('documento_id');
            }

            if ($documentoId) {
                $documento = \App\Models\Documento::find($documentoId);
                $pastaNome = null;
                if ($documento?->pasta_id) {
                    $pastaNome = DB::table('ged_pastas')->where('id', $documento->pasta_id)->value('nome');
                }

                // Assinatura ICP (so existe pra deferido/indeferido/parcial)
                $assinada = null;
                if ($processo->solicitacao_assinatura_id) {
                    $assinada = \App\Models\Assinatura::where('solicitacao_id', $processo->solicitacao_assinatura_id)
                        ->where('status', 'assinado')
                        ->orderByDesc('arquivo_assinado_path')
                        ->first();
                }

                $decisaoAssinada = [
                    'assinatura_id'    => $assinada?->id,
                    'documento_id'     => $documentoId,
                    'tem_pdf_assinado' => $assinada && ! empty($assinada->arquivo_assinado_path),
                    'tipo_assinatura'  => $assinada?->tipo_assinatura,
                    'assinado_em'      => $assinada?->assinado_em?->format('d/m/Y H:i'),
                    'pasta_id'         => $documento?->pasta_id,
                    'pasta_nome'       => $pastaNome,
                    'arquivado_no_ged' => $documento?->status === 'arquivado' && $documento?->pasta_id !== null,
                ];
            }
        }

        // Pastas do GPE Docs (para arquivar a decisao)
        $pastas = DB::table('ged_pastas')
            ->where('ug_id', $processo->ug_id)
            ->orderBy('parent_id')
            ->orderBy('nome')
            ->get(['id', 'nome', 'parent_id', 'descricao']);

        // Detecta se o processo veio do Portal do Cidadao (vinculado a uma solicitacao)
        $solicitacaoPortal = PortalSolicitacao::query()->withoutGlobalScope('ug')
            ->where('processo_id', $processo->id)
            ->first(['id', 'codigo', 'cidadao_id', 'anonima', 'email_contato']);

        return Inertia::render('GED/Processos/Show', [
            'processo'             => $processo,
            'usuarios'             => $usuarios,
            'unidades'             => $unidades,
            'pode_receber'         => $podeReceber,
            'pode_despachar'       => $podeDespachar,
            'pode_concluir'        => $podeConcluir,
            'assinatura_pendente'  => $assinaturaPendente,
            'decisao_assinada'     => $decisaoAssinada,
            'pastas'               => $pastas,
            'solicitacao_portal'   => $solicitacaoPortal,
        ]);
    }

    public function concluir(Request $request, $id)
    {
        $request->validate([
            'observacao_conclusao' => ['nullable', 'string'],
            'decisao'              => ['nullable', 'string', 'in:deferido,indeferido,parcial,arquivado'],
            'anexo'                => ['nullable', 'file', 'max:51200'],
            'pular_assinatura'     => ['nullable', 'boolean'],
        ]);

        try {
            DB::beginTransaction();

            $processo = Processo::with(['tipoProcesso', 'abertoPor', 'tramitacoes.remetente'])->findOrFail($id);
            $decisao = $request->input('decisao');
            $pularAssinatura = (bool) $request->input('pular_assinatura', false);
            // So permite pular assinatura se processo for do Portal Cidadao (resposta informal)
            if ($pularAssinatura && $processo->setor_origem !== 'Portal do Cidadao') {
                $pularAssinatura = false;
            }
            $exigeAssinatura = in_array($decisao, ['deferido', 'indeferido', 'parcial'], true) && ! $pularAssinatura;

            // Decisoes formais (deferido/indeferido/parcial) ficam aguardando assinatura digital
            // (Lei 14.063/2020 art. 4 III). Arquivamento simples nao precisa.
            $processo->update([
                'status'              => $exigeAssinatura ? 'aguardando_assinatura' : 'concluido',
                'concluido_por'       => Auth::id(),
                'concluido_em'        => $exigeAssinatura ? null : now(),
                'observacao_conclusao'=> $request->input('observacao_conclusao'),
                'decisao'             => $decisao,
            ]);

            // Encerra a tramitacao ativa (decisao foi tomada, ninguem mais despacha)
            Tramitacao::where('processo_id', $processo->id)
                ->whereIn('status', ['pendente', 'recebido'])
                ->update(['status' => 'concluido', 'recebido_em' => DB::raw('COALESCE(recebido_em, NOW())')]);

            // Toda decisao (inclusive arquivamento) gera PDF + Documento — pra poder
            // arquivar em pasta do GPE Docs. So as decisoes formais (deferido/indeferido/
            // parcial) criam SolicitacaoAssinatura+Assinatura para exigir ICP-Brasil.
            $autor = User::find(Auth::id());
            $ug = \App\Models\Ug::find($processo->ug_id);
            $pdf = Pdf::loadView('pdf.processo-decisao', [
                'processo' => $processo->refresh()->load(['tipoProcesso', 'abertoPor', 'tramitacoes.remetente']),
                'autor'    => $autor,
                'ug'       => $ug,
            ]);
            $pdf->setPaper('A4', 'portrait');
            $pdfBytes = $pdf->output();

            $filename = 'decisao-' . str_replace(['/', '\\'], '-', $processo->numero_protocolo) . '.pdf';
            $path = 'documentos/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
            Storage::disk('documentos')->put($path, $pdfBytes);

            // Texto pesquisavel: junta dados do processo + parecer + dados do formulario
            $textoPesquisavel = collect([
                'Decisao Administrativa',
                $processo->numero_protocolo,
                $processo->tipoProcesso?->nome,
                $processo->assunto,
                strtoupper($decisao),
                $processo->observacao_conclusao,
                $processo->requerente_nome,
                $processo->requerente_cpf,
                ...(array) ($processo->dados_formulario ?? []),
            ])->filter()->implode(' | ');

            $documento = Documento::create([
                'nome'              => 'Decisao - ' . $processo->numero_protocolo,
                'descricao'         => "Decisao administrativa do processo {$processo->numero_protocolo}: " . strtoupper($decisao),
                'tipo_documental_id'=> 25,
                'pasta_id'          => null,
                'versao_atual'      => 1,
                'tamanho'           => strlen($pdfBytes),
                'mime_type'         => 'application/pdf',
                'autor_id'          => Auth::id(),
                'status'            => 'rascunho',
                'ocr_texto'         => $textoPesquisavel,
            ]);

            Versao::create([
                'documento_id' => $documento->id,
                'versao'       => 1,
                'arquivo_path' => $path,
                'tamanho'      => strlen($pdfBytes),
                'hash_sha256'  => hash('sha256', $pdfBytes),
                'autor_id'     => Auth::id(),
                'comentario'   => 'Documento gerado automaticamente da decisao do processo ' . $processo->numero_protocolo,
            ]);

            $processo->update(['documento_decisao_id' => $documento->id]);

            // Para decisoes formais: cria SolicitacaoAssinatura+Assinatura
            if ($exigeAssinatura) {
                $solicitacao = SolicitacaoAssinatura::create([
                    'documento_id'   => $documento->id,
                    'solicitante_id' => Auth::id(),
                    'status'         => 'pendente',
                    'mensagem'       => "Decisao do processo {$processo->numero_protocolo} (" . strtoupper($decisao) . ") - assinar para tornar oficial.",
                ]);

                Assinatura::create([
                    'solicitacao_id'   => $solicitacao->id,
                    'documento_id'     => $documento->id,
                    'signatario_id'    => Auth::id(),
                    'ordem'            => 1,
                    'status'           => 'pendente',
                    'email_signatario' => $autor->email,
                ]);

                $processo->update(['solicitacao_assinatura_id' => $solicitacao->id]);
            }

            // Anexo opcional do parecer (anexa ao processo)
            if ($request->hasFile('anexo')) {
                $file = $request->file('anexo');
                $anexoPath = $file->store('processos', 'documentos');
                ProcessoAnexo::create([
                    'processo_id'   => $processo->id,
                    'tramitacao_id' => $processo->etapa_atual_id,
                    'nome'          => $file->getClientOriginalName(),
                    'arquivo_path'  => $anexoPath,
                    'tamanho'       => $file->getSize(),
                    'mime_type'     => $file->getMimeType(),
                    'hash_sha256'   => hash_file('sha256', $file->getRealPath()),
                    'enviado_por'   => Auth::id(),
                ]);
            }

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => $exigeAssinatura ? 'decisao' : 'arquivamento',
                'detalhes'    => [
                    'decisao'    => $decisao,
                    'observacao' => $request->input('observacao_conclusao'),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Sincroniza com Portal Cidadao (se este processo veio de uma solicitacao)
            $this->sincronizarSolicitacaoPortal($processo, $decisao, $request->input('observacao_conclusao'));

            DB::commit();

            if ($exigeAssinatura) {
                return redirect()->back()->with('success',
                    'Decisao registrada. Para tornar oficial, assine digitalmente o documento de decisao (Lei 14.063/2020).');
            }

            if ($pularAssinatura) {
                return redirect()->back()->with('success', 'Resposta enviada ao cidadao. Processo encerrado.');
            }

            return redirect()->back()->with('success', 'Processo arquivado.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao concluir processo: ' . $e->getMessage());
        }
    }

    /**
     * Arquiva o documento da decisao em uma pasta do GPE Docs.
     * Move o documento gerado (PDF da decisao) para a pasta escolhida e
     * marca como `arquivado` para sair da listagem de rascunhos do GED.
     */
    public function arquivarNoGed(Request $request, $id)
    {
        $request->validate([
            'pasta_id' => ['required', 'integer', 'exists:ged_pastas,id'],
        ]);

        $processo = Processo::findOrFail($id);

        if ($processo->status !== 'concluido') {
            return redirect()->back()->with('error', 'So processos concluidos podem ser arquivados no GPE Docs.');
        }

        // Localiza o Documento da decisao — preferencia para documento_decisao_id (coluna
        // direta), com fallback para solicitacao_assinatura_id (compatibilidade com decisoes
        // antigas, antes da coluna existir).
        $documentoId = $processo->documento_decisao_id;
        if (! $documentoId && $processo->solicitacao_assinatura_id) {
            $documentoId = \App\Models\SolicitacaoAssinatura::where('id', $processo->solicitacao_assinatura_id)
                ->value('documento_id');
        }

        if (! $documentoId) {
            return redirect()->back()->with('error', 'Processo sem documento gerado para arquivar.');
        }

        $documento = Documento::find($documentoId);
        if (! $documento) {
            return redirect()->back()->with('error', 'Documento nao encontrado.');
        }

        // Valida que a pasta esta na mesma UG do processo
        $pasta = DB::table('ged_pastas')->where('id', $request->input('pasta_id'))->first();
        if (! $pasta || $pasta->ug_id !== $processo->ug_id) {
            return redirect()->back()->with('error', 'A pasta selecionada nao pertence a UG deste processo.');
        }

        $documento->update([
            'pasta_id' => (int) $request->input('pasta_id'),
            'status'   => 'arquivado',
        ]);

        ProcessoHistorico::create([
            'processo_id' => $processo->id,
            'usuario_id'  => Auth::id(),
            'acao'        => 'arquivado_no_ged',
            'detalhes'    => [
                'pasta_id'    => (int) $request->input('pasta_id'),
                'pasta_nome'  => $pasta->nome,
                'documento_id'=> $documento->id,
            ],
        ]);

        return redirect()->back()->with('success', "Decisao arquivada na pasta \"{$pasta->nome}\" do GPE Docs.");
    }

    /**
     * Marca o processo como concluido APOS a assinatura digital da decisao.
     * Chamado pelo callback do servico de assinatura.
     */
    public function finalizarAposAssinatura($id)
    {
        $processo = Processo::findOrFail($id);

        if ($processo->status !== 'aguardando_assinatura') {
            return redirect()->back()->with('error', 'Processo nao esta aguardando assinatura.');
        }

        $processo->update([
            'status'        => 'concluido',
            'concluido_em'  => now(),
        ]);

        ProcessoHistorico::create([
            'processo_id' => $processo->id,
            'usuario_id'  => Auth::id(),
            'acao'        => 'assinatura_decisao',
            'detalhes'    => ['decisao' => $processo->decisao],
        ]);

        return redirect()->back()->with('success', 'Decisao assinada digitalmente. Processo encerrado oficialmente.');
    }

    public function cancelar(Request $request, $id)
    {
        $request->validate([
            'motivo' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            $processo = Processo::findOrFail($id);
            $processo->update([
                'status' => 'cancelado',
            ]);

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'cancelamento',
                'detalhes'    => [
                    'motivo' => $request->input('motivo'),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Processo cancelado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao cancelar processo: ' . $e->getMessage());
        }
    }

    /**
     * Quando um processo originado do Portal Cidadao for decidido/arquivado,
     * atualiza a solicitacao vinculada e dispara email para o cidadao.
     */
    private function sincronizarSolicitacaoPortal(Processo $processo, ?string $decisao, ?string $observacao): void
    {
        $solicitacao = PortalSolicitacao::query()->withoutGlobalScope('ug')
            ->where('processo_id', $processo->id)
            ->with(['servico', 'cidadao', 'ug'])
            ->first();

        if (! $solicitacao) {
            return;
        }

        $statusAnterior = $solicitacao->status;
        $statusNovo = match ($decisao) {
            'deferido', 'parcial'  => 'atendida',
            'indeferido'           => 'recusada',
            'arquivado'            => 'cancelada',
            default                => 'atendida',
        };

        $solicitacao->update([
            'status'        => $statusNovo,
            'atendente_id'  => Auth::id(),
            'resposta'      => $observacao,
            'respondida_em' => now(),
        ]);

        $autor = User::find(Auth::id());
        PortalEvento::create([
            'solicitacao_id'  => $solicitacao->id,
            'tipo'            => $statusNovo === 'atendida' ? 'atendida' : ($statusNovo === 'recusada' ? 'recusada' : 'status_alterado'),
            'autor_tipo'      => 'atendente',
            'autor_nome'      => $autor?->name ?? 'Sistema',
            'autor_user_id'   => Auth::id(),
            'status_anterior' => $statusAnterior,
            'status_novo'     => $statusNovo,
            'mensagem'        => "Decisao no GPE Flow ({$decisao}): ".($observacao ?: '(sem parecer)'),
        ]);

        // Email — so para solicitacoes identificadas
        if (! $solicitacao->anonima) {
            $email = $solicitacao->email_contato ?? $solicitacao->cidadao?->email;
            if ($email) {
                Mail::to($email)->send(new NotificacaoSolicitacaoCidadao(
                    $solicitacao->fresh(),
                    $solicitacao->servico,
                    $solicitacao->ug,
                    'status_alterado'
                ));
            }
        }
    }
}
