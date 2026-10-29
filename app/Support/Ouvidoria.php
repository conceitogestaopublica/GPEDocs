<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use App\Models\Processo\Tramitacao;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ouvidoria (TR 4.33, Lei 13.460/2017) e e-SIC (TR 4.34, Lei 12.527/2011) sobre o motor de
 * processos: prazos legais (30 + 30 dias úteis; 20 + 10 dias corridos), manifestante sigiloso
 * só para a ouvidoria, prorrogação justificada e a resposta que resolve em todos os setores,
 * com a decisão final enviada ao manifestante.
 */
final class Ouvidoria
{
    public const MANIFESTACOES = ['denuncia' => 'Denúncia', 'elogio' => 'Elogio', 'reclamacao' => 'Reclamação',
        'solicitacao' => 'Solicitação', 'sugestao' => 'Sugestão'];

    public const IDENTIFICACOES = ['aberta' => 'Identificada', 'sigilosa' => 'Sigilosa — só a ouvidoria vê os dados',
        'anonima' => 'Anônima — acompanha pelo código'];

    public const CANAIS = ['portal' => 'Portal / app', 'balcao' => 'Balcão', 'presencial' => 'Presencial', 'telefone' => 'Telefone',
        'email' => 'E-mail', 'redes_sociais' => 'Redes sociais'];

    /** Prazo e prorrogação de cada lei: [dias, prorrogação, dias úteis?]. */
    private const PRAZOS = ['ouvidoria' => [30, 30, true], 'esic' => [20, 10, false]];

    /** ouvidoria | esic; outras modalidades do assunto (chamado, legislativo...) não são desta classe. */
    public static function modalidade(?Processo $p): ?string
    {
        $m = $p?->tipoProcesso?->modalidade;

        return isset(self::PRAZOS[$m]) ? $m : null;
    }

    /** Prazo legal a partir da abertura (ou da data dada). */
    public static function prazoLegal(string $modalidade, CarbonInterface $inicio, bool $prorrogado = false): ?CarbonInterface
    {
        [$dias, $mais, $uteis] = self::PRAZOS[$modalidade] ?? [null, null, false];
        if (! $dias) {
            return null;
        }
        $total = $dias + ($prorrogado ? $mais : 0);

        return $uteis ? Expediente::somarDiasUteis($inicio, $total) : $inicio->copy()->addDays($total)->endOfDay();
    }

    /** Ao abrir: prazo legal; anônima não guarda identificação (TR 4.33.27). */
    public static function aoAbrir(Processo $p): void
    {
        $mod = self::modalidade($p->loadMissing('tipoProcesso'));
        if (! $mod) {
            return;
        }
        $dados = ['prazo_legal_em' => self::prazoLegal($mod, $p->created_at ?? now())];
        if ($p->identificacao === 'anonima') {
            $dados += ['requerente_nome' => null, 'requerente_cpf' => null, 'requerente_email' => null, 'requerente_telefone' => null];
        }
        Processo::withoutEvents(fn () => $p->forceFill($dados)->save());
    }

    /** Dados do manifestante sigiloso: só a ouvidoria (TR 4.33.27). */
    public static function podeVerManifestante(?User $u, Processo $p): bool
    {
        return $p->identificacao !== 'sigilosa' || ($u && ($u->super_admin || $u->can('ouvidoria.gerir')));
    }

    /** Tira os dados do manifestante do modelo antes de mandar para a tela de quem não pode ver. */
    public static function ocultarManifestante(Processo $p): Processo
    {
        foreach (['requerente_cpf', 'requerente_email', 'requerente_telefone', 'requerente_endereco'] as $c) {
            $p->setAttribute($c, null);
        }
        $p->setAttribute('requerente_nome', 'Manifestante sigiloso');

        return $p;
    }

    /** Prorroga uma vez, com justificativa (Lei 13.460 art. 16; LAI art. 11 §2º). */
    public static function prorrogar(Processo $p, string $motivo, User $u): void
    {
        $mod = self::modalidade($p);
        abort_unless($mod && $u->can($mod === 'esic' ? 'esic.gerir' : 'ouvidoria.gerir'), 403);
        if ($p->prorrogado_em) {
            throw ValidationException::withMessages(['motivo' => 'O prazo já foi prorrogado uma vez; a lei não permite outra prorrogação.']);
        }
        DB::transaction(function () use ($p, $motivo, $u, $mod) {
            $p->update(['prorrogado_em' => now(), 'prorrogacao_motivo' => $motivo,
                'prazo_legal_em' => self::prazoLegal($mod, $p->created_at, prorrogado: true)]);
            DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u->id, 'acao' => 'prazo_prorrogado',
                'detalhes' => json_encode(['motivo' => $motivo, 'novo_prazo' => $p->prazo_legal_em?->toIso8601String()], JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(), 'created_at' => now()]);
            self::avisarPortal($p, "Prazo de resposta prorrogado até {$p->prazo_legal_em?->setTimezone('America/Sao_Paulo')->format('d/m/Y')}: {$motivo}", null);
        });
    }

    /**
     * A ouvidoria responde e resolve em todos os setores (TR 4.33.22) e encaminha a decisão
     * administrativa final ao manifestante (TR 4.33.23): encerra todas as frentes e conclui.
     */
    public static function resolverEmTodos(Processo $p, string $decisao, User $u): void
    {
        abort_unless($u->can('ouvidoria.gerir') || $u->super_admin, 403);
        abort_if(in_array($p->status, ['concluido', 'arquivado', 'cancelado'], true), 422, 'Processo já encerrado.');
        DB::transaction(function () use ($p, $decisao, $u) {
            Tramitacao::where('processo_id', $p->id)->whereNotIn('status', ['concluido', 'despachado', 'devolvido', 'resolvido', 'cancelado'])
                ->update(['status' => 'concluido', 'recebido_em' => DB::raw('COALESCE(recebido_em, NOW())')]);
            $p->update(['status' => 'concluido', 'concluido_por' => $u->id, 'concluido_em' => now(), 'decisao_final' => $decisao,
                'decisao_final_em' => now(), 'observacao_conclusao' => $decisao]);
            DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u->id, 'acao' => 'resolvido_todos_setores',
                'detalhes' => json_encode(['decisao_final' => $decisao], JSON_UNESCAPED_UNICODE), 'ip' => request()->ip(), 'created_at' => now()]);
        });
        self::avisarPortal($p->fresh(), "Resposta final: {$decisao}", 'atendida');
        RespostaManifestante::enviarDecisao($p->fresh(), $decisao);
    }

    /** Evento e situação na solicitação do portal ligada ao processo, se houver. */
    private static function avisarPortal(Processo $p, string $mensagem, ?string $status): void
    {
        $s = \App\Models\Portal\Solicitacao::withoutGlobalScope('ug')->where('processo_id', $p->id)->first();
        if (! $s) {
            return;
        }
        $anterior = $s->status;
        if ($status) {
            $s->update(['status' => $status, 'resposta' => $mensagem, 'respondida_em' => now()]);
        }
        \App\Models\Portal\SolicitacaoEvento::create(['solicitacao_id' => $s->id, 'tipo' => $status ? 'atendida' : 'comentario',
            'autor_tipo' => 'atendente', 'autor_nome' => auth()->user()?->name ?? 'Ouvidoria', 'autor_user_id' => auth()->id(),
            'status_anterior' => $status ? $anterior : null, 'status_novo' => $status, 'mensagem' => $mensagem]);
    }

    /** Situação do prazo para as telas: dias que faltam (negativo = vencido). */
    public static function situacaoPrazo(Processo $p): ?array
    {
        if (! $p->prazo_legal_em) {
            return null;
        }
        $encerrado = in_array($p->status, ['concluido', 'arquivado', 'cancelado'], true);
        $dias = (int) floor(now()->startOfDay()->diffInDays($p->prazo_legal_em->copy()->startOfDay(), false));

        return ['vence_em' => $p->prazo_legal_em->toIso8601String(), 'dias' => $dias, 'prorrogado' => (bool) $p->prorrogado_em,
            'situacao' => $encerrado ? ($p->concluido_em && $p->concluido_em->lte($p->prazo_legal_em) ? 'no_prazo' : 'fora_do_prazo')
                : ($dias < 0 ? 'vencido' : ($dias <= 5 ? 'vencendo' : 'em_dia'))];
    }
}
