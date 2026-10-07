<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Processo\Processo;
use App\Models\Processo\Tramitacao;
use App\Models\User;

/**
 * Quem pode agir no processo, numa regra só para a tela (que mostra os botões) e para o
 * servidor (que antes não conferia nada: qualquer usuário da UG recebia, despachava,
 * devolvia, concluía ou cancelava qualquer processo chamando a rota direto).
 *
 *   etapa ativa     a tramitação mais recente pendente ou recebida
 *   ativo na etapa  destinatário pessoal, servidor da unidade de destino, ou quem tem
 *                   acesso geral à UG quando o destino é uma unidade
 *   concluir        ativo na etapa, ou o autor enquanto o processo não saiu da 1ª etapa
 */
final class AcaoNoProcesso
{
    public static function etapaAtual(Processo $processo): ?Tramitacao
    {
        return $processo->tramitacoes()
            ->whereIn('status', ['pendente', 'recebido'])
            ->orderByDesc('id')
            ->first();
    }

    public static function ativoNaEtapa(?Tramitacao $etapa, User $user): bool
    {
        if (! $etapa) {
            return false;
        }
        if ($user->super_admin) {
            return true;
        }

        return $etapa->destinatario_id === $user->id
            || ($user->unidade_id && $etapa->destino_unidade_id === $user->unidade_id)
            || ($user->acesso_geral_ug && $etapa->destino_unidade_id !== null);
    }

    /** @return array{etapa: ?Tramitacao, receber: bool, despachar: bool, concluir: bool} */
    public static function avaliar(Processo $processo, User $user): array
    {
        $etapa = self::etapaAtual($processo);
        $ativo = self::ativoNaEtapa($etapa, $user);
        $encerrado = in_array($processo->status, ['concluido', 'cancelado', 'aguardando_assinatura'], true);

        $semDespachoAinda = $processo->tramitacoes()->count() <= 1 && $etapa?->status === 'pendente';
        $autor = $processo->aberto_por === $user->id;

        return [
            'etapa'     => $etapa,
            'receber'   => ! $encerrado && $ativo && $etapa->status === 'pendente',
            // Despachar etapa ainda pendente registra o recebimento antes (ver TramitacaoController).
            'despachar' => ! $encerrado && $ativo,
            'concluir'  => ! $encerrado && ($ativo || ($autor && $semDespachoAinda)),
        ];
    }

    /** A tramitação informada na rota tem de ser a etapa ativa, e o usuário tem de estar nela. */
    public static function exigirAtivoNaTramitacao(Tramitacao $tramitacao, User $user): void
    {
        // Tramitação não tem escopo próprio de UG: o do processo esconde a de outra UG.
        if (! $tramitacao->processo) {
            abort(404);
        }
        $atual = self::etapaAtual($tramitacao->processo);
        if (! $atual || $atual->id !== $tramitacao->id) {
            abort(409, 'Esta etapa não é mais a etapa ativa do processo.');
        }
        if (! self::ativoNaEtapa($tramitacao, $user)) {
            abort(403, 'Você não é destinatário desta etapa do processo.');
        }
    }
}
