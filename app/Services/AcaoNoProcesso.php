<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Processo\Processo;
use App\Models\Processo\Tramitacao;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Quem pode agir no processo, numa regra só para a tela (que mostra os botões) e para o
 * servidor (que antes não conferia nada: qualquer usuário da UG recebia, despachava,
 * devolvia, concluía ou cancelava qualquer processo chamando a rota direto).
 *
 * O processo pode estar em vários setores ao mesmo tempo (TR 4.9.4): cada FRENTE tem a
 * sua etapa ativa (frente 1 = fluxo principal). Cópias não são etapa: só ciência.
 *
 *   etapas ativas   tramitações de trâmite pendentes ou recebidas (uma por frente)
 *   etapa atual     a ativa da frente principal (a mais recente, se não houver)
 *   ativo na etapa  destinatário pessoal, servidor que atende o setor de destino (principal,
 *                   secundário ou abaixo de quem é responsável — App\Support\Setores), ou quem tem
 *                   acesso geral à UG quando o destino é uma unidade
 *   concluir        ativo na frente principal (ou o autor antes do 1º despacho), e só quando
 *                   as outras frentes já resolveram a parte delas (TR 4.9.5)
 *   resolver        ativo numa frente que não é a principal: resolve a parte do setor
 */
final class AcaoNoProcesso
{
    private const ATIVAS = ['pendente', 'recebido'];

    /** @return Collection<int, Tramitacao> */
    public static function etapasAtivas(Processo $processo): Collection
    {
        return $processo->tramitacoes()
            ->where('modo', 'tramite')
            ->whereIn('status', self::ATIVAS)
            ->orderByDesc('id')
            ->get();
    }

    public static function etapaAtual(Processo $processo): ?Tramitacao
    {
        $ativas = self::etapasAtivas($processo);

        return $ativas->firstWhere('frente', 1) ?? $ativas->first();
    }

    /** A etapa em que o usuário age: a da frente dele; senão, a atual (só para mostrar). */
    public static function etapaDoUsuario(Processo $processo, User $user): ?Tramitacao
    {
        $ativas = self::etapasAtivas($processo);

        return $ativas->first(fn (Tramitacao $t) => $t->frente === 1 && self::ativoNaEtapa($t, $user))
            ?? $ativas->first(fn (Tramitacao $t) => self::ativoNaEtapa($t, $user))
            ?? $ativas->firstWhere('frente', 1)
            ?? $ativas->first();
    }

    /** Cópia ainda sem ciência endereçada ao usuário ou a um setor dele. */
    public static function copiaDoUsuario(Processo $processo, User $user): ?Tramitacao
    {
        return $processo->tramitacoes()
            ->where('modo', 'copia')
            ->where('status', 'pendente')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Tramitacao $t) => self::ativoNaEtapa($t, $user));
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
            || \App\Support\Setores::atende($user, $etapa->destino_unidade_id)
            || ($user->acesso_geral_ug && $etapa->destino_unidade_id !== null);
    }

    /**
     * @return array{etapa: ?Tramitacao, receber: bool, despachar: bool, concluir: bool,
     *               cancelar: bool, resolver: bool, aguardando: list<string>}
     */
    public static function avaliar(Processo $processo, User $user): array
    {
        $etapa = self::etapaDoUsuario($processo, $user);
        $ativo = self::ativoNaEtapa($etapa, $user);
        $encerrado = in_array($processo->status, ['concluido', 'cancelado', 'aguardando_assinatura'], true);

        $semDespachoAinda = $processo->tramitacoes()->where('modo', 'tramite')->count() <= 1 && $etapa?->status === 'pendente';
        $autor = $processo->aberto_por === $user->id;
        $principal = $etapa === null || $etapa->frente === 1;

        // Setores que ainda não resolveram a parte deles (outras frentes ativas)
        $aguardando = self::etapasAtivas($processo)
            ->filter(fn (Tramitacao $t) => $t->frente !== 1)
            ->pluck('setor_destino')->unique()->values()->all();

        $podeEncerrar = ! $encerrado && $principal && ($ativo || ($autor && $semDespachoAinda));

        return [
            'etapa'      => $etapa,
            'receber'    => ! $encerrado && $ativo && $etapa->status === 'pendente',
            // Despachar etapa ainda pendente registra o recebimento antes (ver TramitacaoController).
            'despachar'  => ! $encerrado && $ativo,
            'concluir'   => $podeEncerrar && $aguardando === [],
            'cancelar'   => $podeEncerrar,
            'resolver'   => ! $encerrado && $ativo && ! $principal,
            'aguardando' => $aguardando,
        ];
    }

    /** A tramitação informada na rota tem de ser uma etapa ativa, e o usuário tem de estar nela. */
    public static function exigirAtivoNaTramitacao(Tramitacao $tramitacao, User $user): void
    {
        // Tramitação não tem escopo próprio de UG: o do processo esconde a de outra UG.
        if (! $tramitacao->processo) {
            abort(404);
        }
        if ($tramitacao->modo !== 'tramite' || ! in_array($tramitacao->status, self::ATIVAS, true)) {
            abort(409, 'Esta etapa não é mais uma etapa ativa do processo.');
        }
        if (! self::ativoNaEtapa($tramitacao, $user)) {
            abort(403, 'Você não é destinatário desta etapa do processo.');
        }
    }
}
