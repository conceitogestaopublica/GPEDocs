<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\NotificacaoSolicitacaoCidadao;
use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoEvento;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Ug;
use App\Services\AbrirProcessoDoPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SolicitacaoController extends Controller
{
    public function create(Request $request, string $ug, string $slug): Response
    {
        $ugModel = $this->resolverUg($ug);
        $servico = $this->resolverServico($ugModel->id, $slug);
        $cidadao = Auth::guard('cidadao')->user();

        // Se nao logado e o servico NAO permite anonimo: vai pro login
        if (! $cidadao && ! $servico->permite_anonimo) {
            return redirect('/entrar')
                ->with('warning', 'Faca login ou cadastre-se para solicitar este servico.');
        }

        return Inertia::render('Portal/Solicitar', [
            'ug'      => $this->ugPublica($ugModel),
            'servico' => $servico,
            'cidadao' => $cidadao ? [
                'nome'     => $cidadao->nome,
                'email'    => $cidadao->email,
                'telefone' => $cidadao->telefone,
            ] : null,
        ]);
    }

    public function store(Request $request, string $ug, string $slug, AbrirProcessoDoPortalService $abrirProcesso)
    {
        $ugModel = $this->resolverUg($ug);
        $servico = $this->resolverServico($ugModel->id, $slug);
        $cidadao = Auth::guard('cidadao')->user();

        $regrasBase = [
            'descricao'        => ['required', 'string', 'max:5000'],
            'anonima'          => ['nullable', 'boolean'],
        ];

        $anonima = $servico->permite_anonimo && (bool) $request->input('anonima', false);
        if (! $anonima) {
            // Identificada: precisa estar logada
            if (! $cidadao) {
                return redirect('/entrar')
                    ->with('warning', 'Faca login ou cadastre-se para solicitar este servico.');
            }
            $regrasBase['telefone_contato'] = ['nullable', 'string', 'max:30'];
            $regrasBase['email_contato']    = ['nullable', 'email', 'max:150'];
        } else {
            // Anonima: tudo opcional
            $regrasBase['telefone_contato'] = ['nullable', 'string', 'max:30'];
            $regrasBase['email_contato']    = ['nullable', 'email', 'max:150'];
        }

        $data = $request->validate($regrasBase);

        $solicitacao = DB::transaction(function () use ($ugModel, $servico, $cidadao, $data, $anonima, $abrirProcesso) {
            $sol = Solicitacao::query()->withoutGlobalScope('ug')->create([
                'codigo'           => Solicitacao::gerarCodigo($ugModel->id),
                'ug_id'            => $ugModel->id,
                'servico_id'       => $servico->id,
                'cidadao_id'       => $anonima ? null : $cidadao?->id,
                'anonima'          => $anonima,
                'status'           => 'aberta',
                'descricao'        => $data['descricao'],
                'telefone_contato' => $anonima ? null : ($data['telefone_contato'] ?? $cidadao?->telefone),
                'email_contato'    => $anonima ? null : ($data['email_contato'] ?? $cidadao?->email),
            ]);

            SolicitacaoEvento::create([
                'solicitacao_id'   => $sol->id,
                'tipo'             => 'criada',
                'autor_tipo'       => 'cidadao',
                'autor_nome'       => $anonima ? 'Anonimo' : ($cidadao?->nome ?? 'Cidadao'),
                'autor_cidadao_id' => $anonima ? null : $cidadao?->id,
                'status_novo'      => 'aberta',
                'mensagem'         => $anonima
                    ? 'Solicitacao registrada anonimamente.'
                    : 'Solicitacao registrada pelo cidadao.',
            ]);

            // Cria Processo no GPE Flow se servico tem setor + tipo configurados
            $processo = $abrirProcesso->abrir($sol, $servico);
            if ($processo) {
                $sol->update(['processo_id' => $processo->id]);
            }

            return $sol;
        });

        // Notifica por email apenas quando identificada
        if (! $anonima) {
            $email = $solicitacao->email_contato ?? $cidadao?->email;
            if ($email) {
                Mail::to($email)
                    ->send(new NotificacaoSolicitacaoCidadao($solicitacao, $servico, $ugModel, 'criada'));
            }
        }

        if ($anonima) {
            return Inertia::render('Portal/SolicitacaoConfirmacaoAnonima', [
                'ug'      => $this->ugPublica($ugModel),
                'codigo'  => $solicitacao->codigo,
                'servico' => ['titulo' => $servico->titulo],
            ]);
        }

        return redirect("/minhas-solicitacoes/{$solicitacao->id}")
            ->with('success', "Solicitacao {$solicitacao->codigo} registrada. Voce sera notificado por email sobre o andamento.");
    }

    public function minhasSolicitacoes(Request $request, string $ug): Response
    {
        $ugModel = $this->resolverUg($ug);
        $cidadao = Auth::guard('cidadao')->user();

        $solicitacoes = Solicitacao::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('cidadao_id', $cidadao->id)
            ->with('servico:id,titulo,slug,icone')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Portal/MinhasSolicitacoes', [
            'ug'           => $this->ugPublica($ugModel),
            'solicitacoes' => $solicitacoes,
            'statusList'   => Solicitacao::STATUS,
        ]);
    }

    public function show(Request $request, string $ug, int $id): Response
    {
        $ugModel = $this->resolverUg($ug);
        $cidadao = Auth::guard('cidadao')->user();

        $solicitacao = Solicitacao::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('cidadao_id', $cidadao->id)
            ->where('id', $id)
            ->with([
                'servico:id,titulo,slug,icone,categoria_id',
                'servico.categoria:id,nome,slug',
                'eventos',
                'atendente:id,name',
            ])
            ->firstOrFail();

        // Anexos do processo vinculado (resposta do servidor) — visivel ao cidadao
        $anexos = collect();
        $decisaoPdf = null;
        if ($solicitacao->processo_id) {
            $anexos = ProcessoAnexo::query()
                ->withoutGlobalScope('ug')
                ->where('processo_id', $solicitacao->processo_id)
                ->orderByDesc('id')
                ->get(['id', 'nome', 'tamanho', 'mime_type', 'created_at']);

            // PDF da decisao: prefere o ARQUIVO ASSINADO (em ged_assinaturas.arquivo_assinado_path)
            // Se ainda nao foi assinado, mostra o PDF original (versao 1) com aviso de "Aguardando".
            $processo = \App\Models\Processo\Processo::query()->withoutGlobalScope('ug')
                ->find($solicitacao->processo_id, ['id', 'numero_protocolo', 'documento_decisao_id', 'status']);
            if ($processo && $processo->documento_decisao_id) {
                $assinaturaAssinada = \App\Models\Assinatura::query()
                    ->where('documento_id', $processo->documento_decisao_id)
                    ->where('status', 'assinado')
                    ->whereNotNull('arquivo_assinado_path')
                    ->orderByDesc('assinado_em')
                    ->first(['id', 'arquivo_assinado_path', 'assinado_em']);

                if ($assinaturaAssinada && \Illuminate\Support\Facades\Storage::disk('documentos')->exists($assinaturaAssinada->arquivo_assinado_path)) {
                    $decisaoPdf = [
                        'numero'      => $processo->numero_protocolo,
                        'tamanho'     => \Illuminate\Support\Facades\Storage::disk('documentos')->size($assinaturaAssinada->arquivo_assinado_path),
                        'criado_em'   => $assinaturaAssinada->assinado_em,
                        'assinado'    => true,
                    ];
                } else {
                    $versaoAtual = \App\Models\Versao::query()
                        ->where('documento_id', $processo->documento_decisao_id)
                        ->orderByDesc('versao')
                        ->first(['id', 'versao', 'tamanho', 'created_at']);
                    if ($versaoAtual) {
                        $decisaoPdf = [
                            'numero'    => $processo->numero_protocolo,
                            'tamanho'   => $versaoAtual->tamanho,
                            'criado_em' => $versaoAtual->created_at,
                            'assinado'  => false,
                        ];
                    }
                }
            }
        }

        return Inertia::render('Portal/SolicitacaoShow', [
            'ug'          => $this->ugPublica($ugModel),
            'solicitacao' => $solicitacao,
            'anexos'      => $anexos,
            'decisaoPdf'  => $decisaoPdf,
            'statusList'  => Solicitacao::STATUS,
        ]);
    }

    /**
     * Download do PDF da decisao (versao mais recente — assinada se ja foi).
     * Acessivel apenas pelo cidadao dono da solicitacao.
     */
    public function baixarDecisao(Request $request, string $ug, int $id)
    {
        $ugModel = $this->resolverUg($ug);
        $cidadao = Auth::guard('cidadao')->user();

        $solicitacao = Solicitacao::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('cidadao_id', $cidadao->id)
            ->where('id', $id)
            ->firstOrFail(['id', 'codigo', 'processo_id']);

        if (! $solicitacao->processo_id) {
            abort(404);
        }

        $processo = \App\Models\Processo\Processo::query()->withoutGlobalScope('ug')
            ->find($solicitacao->processo_id, ['id', 'documento_decisao_id', 'numero_protocolo']);

        if (! $processo?->documento_decisao_id) {
            abort(404);
        }

        // Prefere o PDF ASSINADO (ged_assinaturas.arquivo_assinado_path); fallback pro original
        $assinada = \App\Models\Assinatura::query()
            ->where('documento_id', $processo->documento_decisao_id)
            ->where('status', 'assinado')
            ->whereNotNull('arquivo_assinado_path')
            ->orderByDesc('assinado_em')
            ->first();

        $path = null;
        $sufixo = '';
        if ($assinada && Storage::disk('documentos')->exists($assinada->arquivo_assinado_path)) {
            $path = $assinada->arquivo_assinado_path;
            $sufixo = '-assinado';
        } else {
            $versao = \App\Models\Versao::query()
                ->where('documento_id', $processo->documento_decisao_id)
                ->orderByDesc('versao')
                ->first();
            if ($versao && Storage::disk('documentos')->exists($versao->arquivo_path)) {
                $path = $versao->arquivo_path;
            }
        }

        if (! $path) {
            abort(404);
        }

        $nome = 'decisao-'.str_replace(['/', '\\'], '-', $processo->numero_protocolo).$sufixo.'.pdf';
        return Storage::disk('documentos')->download($path, $nome);
    }

    /**
     * Download de anexo do processo da solicitacao.
     * Garante que o anexo pertence a um processo de uma solicitacao do cidadao logado.
     */
    public function baixarAnexo(Request $request, string $ug, int $anexoId)
    {
        $ugModel = $this->resolverUg($ug);
        $cidadao = Auth::guard('cidadao')->user();

        $anexo = ProcessoAnexo::query()->withoutGlobalScope('ug')->findOrFail($anexoId);

        $solicitacao = Solicitacao::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('cidadao_id', $cidadao->id)
            ->where('processo_id', $anexo->processo_id)
            ->first();

        if (! $solicitacao) {
            abort(404);
        }

        if (! Storage::disk('documentos')->exists($anexo->arquivo_path)) {
            abort(404);
        }

        return Storage::disk('documentos')->download($anexo->arquivo_path, $anexo->nome);
    }

    public function cancelar(Request $request, string $ug, int $id)
    {
        $ugModel = $this->resolverUg($ug);
        $cidadao = Auth::guard('cidadao')->user();

        $solicitacao = Solicitacao::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('cidadao_id', $cidadao->id)
            ->where('id', $id)
            ->firstOrFail();

        if (in_array($solicitacao->status, Solicitacao::STATUS_FINAIS, true)) {
            return back()->with('error', 'Solicitacao ja finalizada nao pode ser cancelada.');
        }

        DB::transaction(function () use ($solicitacao, $cidadao) {
            $statusAnterior = $solicitacao->status;
            $solicitacao->update(['status' => 'cancelada']);

            SolicitacaoEvento::create([
                'solicitacao_id'   => $solicitacao->id,
                'tipo'             => 'cancelada',
                'autor_tipo'       => 'cidadao',
                'autor_nome'       => $cidadao->nome,
                'autor_cidadao_id' => $cidadao->id,
                'status_anterior'  => $statusAnterior,
                'status_novo'      => 'cancelada',
                'mensagem'         => 'Cancelada pelo cidadao.',
            ]);
        });

        return back()->with('success', 'Solicitacao cancelada.');
    }

    private function resolverUg(string $slug): Ug
    {
        return Ug::query()->where('portal_slug', $slug)->where('ativo', true)->firstOrFail();
    }

    private function resolverServico(int $ugId, string $slug): Servico
    {
        return Servico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugId)
            ->where('slug', $slug)
            ->where('publicado', true)
            ->firstOrFail();
    }

    private function ugPublica(Ug $ug): array
    {
        return [
            'id'       => $ug->id,
            'codigo'   => $ug->codigo,
            'slug'     => $ug->portal_slug,
            'nome'     => $ug->nome,
            'cidade'   => $ug->cidade,
            'uf'       => $ug->uf,
            'brasao'   => $ug->brasao_path ? "/_brasao/{$ug->id}" : null,
            'site'     => $ug->site,
            'email'    => $ug->email_institucional,
            'telefone' => $ug->telefone,
        ];
    }
}
