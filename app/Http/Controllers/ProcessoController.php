<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\Notificacao;
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

        return Inertia::render('GED/Processos/Show', [
            'processo'             => $processo,
            'usuarios'             => $usuarios,
            'unidades'             => $unidades,
            'pode_receber'         => $podeReceber,
            'pode_despachar'       => $podeDespachar,
            'pode_concluir'        => $podeConcluir,
            'assinatura_pendente'  => $assinaturaPendente,
        ]);
    }

    public function concluir(Request $request, $id)
    {
        $request->validate([
            'observacao_conclusao' => ['nullable', 'string'],
            'decisao'              => ['nullable', 'string', 'in:deferido,indeferido,parcial,arquivado'],
        ]);

        try {
            DB::beginTransaction();

            $processo = Processo::with(['tipoProcesso', 'abertoPor', 'tramitacoes.remetente'])->findOrFail($id);
            $decisao = $request->input('decisao');
            $exigeAssinatura = in_array($decisao, ['deferido', 'indeferido', 'parcial'], true);

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

            // Para decisoes formais: gera PDF + Documento + SolicitacaoAssinatura
            if ($exigeAssinatura) {
                $autor = User::find(Auth::id());
                $pdf = Pdf::loadView('pdf.processo-decisao', [
                    'processo' => $processo->refresh()->load(['tipoProcesso', 'abertoPor', 'tramitacoes.remetente']),
                    'autor'    => $autor,
                ]);
                $pdf->setPaper('A4', 'portrait');
                $pdfBytes = $pdf->output();

                $filename = 'decisao-' . str_replace(['/', '\\'], '-', $processo->numero_protocolo) . '.pdf';
                $path = 'documentos/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
                Storage::disk('documentos')->put($path, $pdfBytes);

                $documento = Documento::create([
                    'nome'              => 'Decisao - ' . $processo->numero_protocolo,
                    'descricao'         => "Decisao administrativa do processo {$processo->numero_protocolo}: " . strtoupper($decisao),
                    'tipo_documental_id'=> 25, // "Decisao Administrativa" (criado no seed/setup)
                    'pasta_id'          => null,
                    'versao_atual'      => 1,
                    'tamanho'           => strlen($pdfBytes),
                    'mime_type'         => 'application/pdf',
                    'autor_id'          => Auth::id(),
                    'status'            => 'rascunho',
                ]);

                Versao::create([
                    'documento_id' => $documento->id,
                    'versao'       => 1,
                    'arquivo_path' => $path,
                    'tamanho'      => strlen($pdfBytes),
                    'hash_sha256'  => hash('sha256', $pdfBytes),
                    'autor_id'     => Auth::id(),
                    'comentario'   => 'Documento gerado automaticamente para assinatura da decisao do processo ' . $processo->numero_protocolo,
                ]);

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

            DB::commit();

            if ($exigeAssinatura) {
                return redirect()->back()->with('success',
                    'Decisao registrada. Para tornar oficial, assine digitalmente o documento de decisao (Lei 14.063/2020).');
            }

            return redirect()->back()->with('success', 'Processo arquivado.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao concluir processo: ' . $e->getMessage());
        }
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
}
