<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Mail\NotificacaoSolicitacaoCidadao;
use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalSolicitacoesController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $servicoId = $request->input('servico_id');
        $busca = (string) $request->input('q', '');

        $solicitacoes = Solicitacao::query()
            ->with([
                'servico:id,titulo,slug,icone',
                'cidadao:id,nome,email,telefone',
                'atendente:id,name',
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($servicoId, fn ($q) => $q->where('servico_id', $servicoId))
            ->when($busca !== '', function ($q) use ($busca) {
                $q->where(function ($qq) use ($busca) {
                    $qq->where('codigo', 'ilike', "%{$busca}%")
                       ->orWhere('descricao', 'ilike', "%{$busca}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $servicos = Servico::query()->orderBy('titulo')->get(['id', 'titulo']);

        $contagens = Solicitacao::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Portal/Admin/Solicitacoes/Index', [
            'solicitacoes' => $solicitacoes,
            'servicos'     => $servicos,
            'statusList'   => Solicitacao::STATUS,
            'contagens'    => $contagens,
            'filtros'      => [
                'status'     => $status,
                'servico_id' => $servicoId,
                'q'          => $busca,
            ],
        ]);
    }

    public function show(int $id): Response
    {
        $solicitacao = Solicitacao::query()
            ->with([
                'servico:id,titulo,slug,icone,descricao_curta,categoria_id',
                'servico.categoria:id,nome',
                'cidadao:id,nome,email,cpf,telefone',
                'atendente:id,name',
                'eventos',
                'anexos',
                'eventos.autorUser:id,name',
                'eventos.autorCidadao:id,nome',
                'ug:id,nome,codigo,portal_slug',
            ])
            ->findOrFail($id);

        $processo = null;
        if ($solicitacao->processo_id) {
            $processo = \App\Models\Processo\Processo::query()->withoutGlobalScope('ug')
                ->find($solicitacao->processo_id, ['id', 'numero_protocolo', 'status', 'etapa_atual_id']);
        }

        return Inertia::render('Portal/Admin/Solicitacoes/Show', [
            'solicitacao' => $solicitacao,
            'processo'    => $processo,
            'statusList'  => Solicitacao::STATUS,
        ]);
    }

    /** Baixa um anexo enviado pelo cidadão (a solicitação já é escopada pela UG). */
    public function anexo(int $id, int $anexoId): StreamedResponse
    {
        $anexo = Solicitacao::query()->findOrFail($id)->anexos()->findOrFail($anexoId);

        return Storage::disk('documentos')->response($anexo->arquivo_path, $anexo->nome, [
            'Content-Type' => $anexo->mime_type,
        ]);
    }

    public function alterarStatus(Request $request, int $id)
    {
        $data = $request->validate([
            // Decisão do atendimento: deferir → atendida, indeferir → recusada (com motivo).
            'decisao'  => ['nullable', 'string', 'in:deferir,indeferir'],
            'status'   => ['required_without:decisao', 'nullable', 'string', 'in:aberta,em_atendimento,atendida,recusada,cancelada'],
            'resposta' => ['required_if:decisao,indeferir', 'nullable', 'string', 'max:5000'],
        ], [
            'resposta.required_if' => 'Para indeferir, escreva o motivo na resposta.',
        ]);
        if (! empty($data['decisao'])) {
            $data['status'] = $data['decisao'] === 'deferir' ? 'atendida' : 'recusada';
        }

        $solicitacao = Solicitacao::with('servico', 'cidadao', 'ug')->findOrFail($id);
        $atendente = Auth::user();

        DB::transaction(function () use ($solicitacao, $data, $atendente) {
            $statusAnterior = $solicitacao->status;
            $payload = [
                'status'       => $data['status'],
                'atendente_id' => $atendente->id,
            ];
            if (! empty($data['resposta'])) {
                $payload['resposta']      = $data['resposta'];
                $payload['respondida_em'] = now();
            }
            $solicitacao->update($payload);

            SolicitacaoEvento::create([
                'solicitacao_id'  => $solicitacao->id,
                'tipo'            => $data['status'] === 'atendida' ? 'atendida' : ($data['status'] === 'recusada' ? 'recusada' : 'status_alterado'),
                'autor_tipo'      => 'atendente',
                'autor_nome'      => $atendente->name,
                'autor_user_id'   => $atendente->id,
                'status_anterior' => $statusAnterior,
                'status_novo'     => $data['status'],
                'mensagem'        => match ($data['decisao'] ?? null) {
                    'deferir'   => trim('Pedido deferido. ' . ($data['resposta'] ?? '')),
                    'indeferir' => trim('Pedido indeferido. ' . ($data['resposta'] ?? '')),
                    default     => $data['resposta'] ?? null,
                },
            ]);
        });

        $email = $solicitacao->email_contato ?? $solicitacao->cidadao?->email;
        if ($email) {
            Mail::to($email)
                ->send(new NotificacaoSolicitacaoCidadao(
                    $solicitacao->fresh(),
                    $solicitacao->servico,
                    $solicitacao->ug,
                    'status_alterado'
                ));
        }

        $feito = match ($data['decisao'] ?? null) {
            'deferir'   => 'Pedido deferido',
            'indeferir' => 'Pedido indeferido',
            default     => 'Status atualizado para "' . Solicitacao::STATUS[$data['status']] . '"',
        };

        return back()->with('success', $feito . ($email ? ' e requerente notificado por e-mail.' : '.'));
    }

    public function comentar(Request $request, int $id)
    {
        $data = $request->validate([
            'mensagem' => ['required', 'string', 'max:5000'],
        ]);

        $solicitacao = Solicitacao::with('cidadao', 'servico', 'ug')->findOrFail($id);
        $atendente = Auth::user();

        SolicitacaoEvento::create([
            'solicitacao_id' => $solicitacao->id,
            'tipo'           => 'comentario',
            'autor_tipo'     => 'atendente',
            'autor_nome'     => $atendente->name,
            'autor_user_id'  => $atendente->id,
            'mensagem'       => $data['mensagem'],
        ]);

        $email = $solicitacao->email_contato ?? $solicitacao->cidadao?->email;
        if ($email) {
            Mail::to($email)
                ->send(new NotificacaoSolicitacaoCidadao(
                    $solicitacao,
                    $solicitacao->servico,
                    $solicitacao->ug,
                    'comentario',
                    $data['mensagem']
                ));
        }

        return back()->with('success', 'Mensagem enviada ao cidadão por email.');
    }
}
