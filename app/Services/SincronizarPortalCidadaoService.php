<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NotificacaoSolicitacaoCidadao;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoEvento;
use App\Models\Processo\Processo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Sincroniza o status da Solicitacao do Portal com o estado do Processo do GPE Flow.
 *
 * Usado em dois momentos:
 *   - Resposta direta sem assinatura (ProcessoController@concluir com pular_assinatura=true)
 *   - Apos assinatura ICP-Brasil completa (AssinaturaController@finalizarProcessoSeVinculado)
 */
class SincronizarPortalCidadaoService
{
    public function sincronizar(Processo $processo, ?string $decisao, ?string $observacao, bool $documentoAssinado = false): void
    {
        $solicitacao = Solicitacao::query()->withoutGlobalScope('ug')
            ->where('processo_id', $processo->id)
            ->with(['servico', 'cidadao', 'ug'])
            ->first();

        if (! $solicitacao) {
            return;
        }

        $statusAnterior = $solicitacao->status;
        $statusNovo = match ($decisao) {
            'deferido', 'parcial' => 'atendida',
            'indeferido'          => 'recusada',
            'arquivado'           => 'cancelada',
            default               => $statusAnterior,
        };

        $solicitacao->update([
            'status'        => $statusNovo,
            'atendente_id'  => Auth::id() ?? $solicitacao->atendente_id,
            'resposta'      => $observacao ?? $solicitacao->resposta,
            'respondida_em' => $solicitacao->respondida_em ?? now(),
        ]);

        $autor = Auth::id() ? User::find(Auth::id()) : null;
        SolicitacaoEvento::create([
            'solicitacao_id'  => $solicitacao->id,
            'tipo'            => $statusNovo === 'atendida' ? 'atendida' : ($statusNovo === 'recusada' ? 'recusada' : 'status_alterado'),
            'autor_tipo'      => 'atendente',
            'autor_nome'      => $autor?->name ?? 'Sistema',
            'autor_user_id'   => Auth::id(),
            'status_anterior' => $statusAnterior,
            'status_novo'     => $statusNovo,
            'mensagem'        => $documentoAssinado
                ? "Documento de decisao assinado digitalmente (".strtoupper((string) $decisao)."). " . ($observacao ?: '')
                : "Decisao no GPE Fluxos e Processos (".strtoupper((string) $decisao)."). " . ($observacao ?: ''),
        ]);

        if ($solicitacao->anonima) {
            return;
        }

        $email = $solicitacao->email_contato ?? $solicitacao->cidadao?->email;
        if (! $email || \App\Support\DescadastroEmail::descadastrado($email)) {   // pediu para não receber avisos
            return;
        }

        // Se foi disparado apos a assinatura, anexa o PDF assinado da decisao
        $pdfAssinado = null;
        if ($documentoAssinado && $processo->documento_decisao_id) {
            $pdfAssinado = $this->localizarPdfAssinado($processo->documento_decisao_id);
        }

        // Rastreado (TR 4.11.12)
        \App\Support\RastreioEmail::enviar(new NotificacaoSolicitacaoCidadao(
            $solicitacao->fresh(),
            $solicitacao->servico,
            $solicitacao->ug,
            'status_alterado',
            null,
            $pdfAssinado,
        ), $email, 'cidadao', "Solicitação {$solicitacao->codigo}", 'solicitacao', $solicitacao->id, null);
    }

    /**
     * Devolve [path_temporario_local, nome_amigavel] do PDF da decisão assinada para o
     * e-mail do cidadão: a cópia para impressão (com os carimbos); o original com as
     * assinaturas digitais fica no portal. Sem cópia (assinaturas antigas), o original.
     */
    private function localizarPdfAssinado(int $documentoId): ?array
    {
        $documento = \App\Models\Documento::query()->withoutGlobalScopes()->with('versaoAtual')->find($documentoId);
        $caminho = $documento?->caminhoImpressao();
        if (! $caminho) {
            $oficial = $documento?->caminhoOficial();
            $caminho = $oficial !== $documento?->versaoAtual?->arquivo_path ? $oficial : null;
        }

        if (! $caminho || ! Storage::disk('documentos')->exists($caminho)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sig_').'.pdf';
        file_put_contents($tmp, Storage::disk('documentos')->get($caminho));

        $nome = ($documento?->nome ?? 'decisao').'-assinado.pdf';

        return [$tmp, $nome];
    }
}
