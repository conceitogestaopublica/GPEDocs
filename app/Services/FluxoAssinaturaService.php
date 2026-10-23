<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assinatura;
use App\Models\Notificacao;
use App\Models\SolicitacaoAssinatura;

/**
 * Regras de andamento de uma solicitação de assinatura, comuns ao signatário interno
 * (AssinaturaController) e ao externo por link (AssinaturaExternaController): vez na
 * ordem, aviso dos próximos, conclusão, processo vinculado e webhooks.
 */
class FluxoAssinaturaService
{
    public function __construct(private readonly ConviteAssinaturaService $convites)
    {
    }

    /**
     * Solicitação recusada por um signatário (ou cancelada pela origem) não aceita mais
     * assinaturas — antes, os demais assinavam e ela virava "concluída".
     */
    public function impedimento(Assinatura $assinatura): ?string
    {
        return match ($assinatura->solicitacao?->status) {
            'recusada'  => 'Esta solicitação foi recusada por um signatário e não aceita mais assinaturas.',
            'cancelada' => 'Esta solicitação foi cancelada.',
            default     => $this->ehAVez($assinatura) ? null
                : 'Esta solicitação é em ordem: aguarde a assinatura dos signatários anteriores.',
        };
    }

    /** Em solicitação sequencial, só assina quem não tem ninguém de ordem menor por assinar. */
    public function ehAVez(Assinatura $assinatura): bool
    {
        if (! $assinatura->solicitacao?->sequencial) {
            return true;
        }

        return ! Assinatura::where('solicitacao_id', $assinatura->solicitacao_id)
            ->where('ordem', '<', $assinatura->ordem)
            ->where('status', '!=', 'assinado')
            ->exists();
    }

    /**
     * Avisa quem pode assinar ao criar a solicitação: todos, ou só a primeira ordem quando
     * ela é sequencial. Interno recebe notificação no sistema; externo, o convite por e-mail.
     */
    public function avisarIniciais(SolicitacaoAssinatura $solicitacao): void
    {
        $assinaturas = $solicitacao->assinaturas()->where('status', 'pendente')->get();
        if ($solicitacao->sequencial) {
            $primeira = $assinaturas->min('ordem');
            $assinaturas = $assinaturas->where('ordem', $primeira);
        }

        $assinaturas->each(fn (Assinatura $a) => $this->avisar($a, 'Assinatura solicitada',
            "Você tem uma solicitação de assinatura para o documento \"{$solicitacao->documento?->nome}\"."));
    }

    /**
     * Depois de uma assinatura em solicitação sequencial: se a ordem avançou, avisa quem
     * passou a poder assinar (na criação só o primeiro foi avisado).
     */
    public function avisarProximos(Assinatura $assinada): void
    {
        $solicitacao = $assinada->solicitacao;
        if (! $solicitacao?->sequencial) {
            return;
        }

        $proximaOrdem = $solicitacao->assinaturas()->where('status', 'pendente')->min('ordem');
        if ($proximaOrdem === null || $proximaOrdem <= $assinada->ordem) {
            return; // ainda falta alguém da mesma ordem
        }

        $solicitacao->assinaturas()->where('status', 'pendente')->where('ordem', $proximaOrdem)->get()
            ->each(fn (Assinatura $a) => $this->avisar($a, 'Sua vez de assinar',
                "Chegou a sua vez de assinar o documento \"{$solicitacao->documento?->nome}\"."));
    }

    private function avisar(Assinatura $a, string $titulo, string $mensagem): void
    {
        if ($a->externo) {
            $this->convites->enviar($a);

            return;
        }

        Notificacao::create([
            'usuario_id'      => $a->signatario_id,
            'tipo'            => 'assinatura_pendente',
            'titulo'          => $titulo,
            'mensagem'        => $mensagem,
            'referencia_tipo' => 'documento',
            'referencia_id'   => $a->documento_id,
        ]);
    }

    /** Concluída só quando TODAS foram assinadas — recusa não conta como assinatura. */
    public function todasAssinadas(SolicitacaoAssinatura $solicitacao): bool
    {
        return $solicitacao->assinaturas()->where('status', '!=', 'assinado')->doesntExist();
    }

    /**
     * Fecha o andamento depois de uma assinatura: status da solicitação, aviso dos próximos,
     * webhooks e processo vinculado. Devolve se todas foram assinadas.
     */
    public function aposAssinar(Assinatura $assinatura, string $tipoWebhook, ?int $usuarioId): bool
    {
        $solicitacao = $assinatura->solicitacao;
        $todasAssinadas = $this->todasAssinadas($solicitacao);
        $solicitacao->update(['status' => $todasAssinadas ? 'concluida' : 'em_andamento']);
        $this->avisarProximos($assinatura);

        $this->dispararWebhook($solicitacao, 'assinatura.individual', array_filter([
            'tipo_assinatura' => $tipoWebhook,
            'signatario_id'   => $assinatura->signatario_id,
            'signatario_nome' => $assinatura->externo ? $assinatura->nome_signatario : null,
            'externo'         => $assinatura->externo ?: null,
            'cpf'             => $assinatura->cpf_signatario,
            'assinado_em'     => $assinatura->assinado_em?->toIso8601String(),
            'todas_assinadas' => $todasAssinadas,
        ], fn ($v) => $v !== null));

        if ($todasAssinadas) {
            $this->finalizarProcessoSeVinculado($solicitacao, $usuarioId);
            $this->dispararWebhook($solicitacao, 'assinatura.todas_concluidas', [
                'concluido_em' => now()->toIso8601String(),
            ]);
        }

        return $todasAssinadas;
    }

    /**
     * Se a solicitacao esta vinculada a um processo (decisao administrativa),
     * marca o processo como concluido apos a assinatura.
     */
    public function finalizarProcessoSeVinculado(SolicitacaoAssinatura $solicitacao, ?int $usuarioId): void
    {
        $processo = \App\Models\Processo\Processo::where('solicitacao_assinatura_id', $solicitacao->id)
            ->where('status', 'aguardando_assinatura')
            ->first();

        if (! $processo) {
            return;
        }

        $processo->update([
            'status'       => 'concluido',
            'concluido_em' => now(),
        ]);

        \App\Models\Processo\ProcessoHistorico::create([
            'processo_id' => $processo->id,
            'usuario_id'  => $usuarioId,
            'acao'        => 'assinatura_decisao',
            'detalhes'    => [
                'decisao'        => $processo->decisao,
                'solicitacao_id' => $solicitacao->id,
            ],
        ]);

        // Se este processo veio do Portal do Cidadao, agora que o documento foi assinado,
        // notifica o cidadao com o PDF assinado em anexo (Lei 14.063/2020).
        app(SincronizarPortalCidadaoService::class)
            ->sincronizar($processo->fresh(), $processo->decisao, $processo->observacao_conclusao, true);
    }

    /**
     * Centraliza disparo de webhooks via service. Eventos suportados:
     *   - assinatura.individual       (cada signatario individual)
     *   - assinatura.recusada
     *   - assinatura.todas_concluidas (final, marca callback_executado=true)
     */
    public function dispararWebhook(SolicitacaoAssinatura $solicitacao, string $evento, array $extra = []): void
    {
        $documento = $solicitacao->documento;
        if (! $documento) {
            return;
        }

        // Para o evento final, evita reenvio se ja foi marcado como executado
        if ($evento === 'assinatura.todas_concluidas' && $documento->callback_executado) {
            return;
        }

        app(WebhookDispatcher::class)->disparar($documento, $evento, $extra);
    }
}
