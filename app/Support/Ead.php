<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Treinamento EAD (TR 4.24): matrícula ao abrir o curso, progresso da aula pelo trecho assistido sem pular
 * (o servidor só aceita avanço compatível com o tempo real decorrido), ordem obrigatória, avaliação com nota
 * mínima e tentativas, certificado com código de verificação, e o quadro de progresso por servidor.
 */
final class Ead
{
    /** Folga por atualização: o vídeo pode andar até 1,6× o tempo real + 15 s (velocidade 1,5× e atraso de rede). */
    private const FOLGA_FATOR = 1.6;

    private const FOLGA_SEG = 15;

    public static function matricula(int $cursoId, int $userId): object
    {
        DB::table('ead_matriculas')->insertOrIgnore(['curso_id' => $cursoId, 'user_id' => $userId, 'iniciado_em' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('ead_matriculas')->where('curso_id', $cursoId)->where('user_id', $userId)->first();
    }

    /** Aulas com o progresso do servidor e se cada uma está liberada (ordem obrigatória). */
    public static function aulas(object $curso, ?object $matricula): array
    {
        $prog = $matricula ? DB::table('ead_progresso')->where('matricula_id', $matricula->id)->get()->keyBy('aula_id') : collect();
        $anteriorConcluida = true;

        return DB::table('ead_aulas')->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->get()->map(function ($a) use ($prog, $curso, &$anteriorConcluida) {
            $p = $prog[$a->id] ?? null;
            $liberada = ! $curso->ordem_obrigatoria || $anteriorConcluida;
            $concluida = (bool) $p?->concluida_em;
            $anteriorConcluida = $concluida;

            return ['id' => $a->id, 'ordem' => $a->ordem, 'titulo' => $a->titulo, 'descricao' => $a->descricao, 'video_tipo' => $a->video_tipo,
                'video_url' => $a->video_tipo === 'youtube' ? $a->video_url : "/ead/aulas/{$a->id}/video", 'duracao_seg' => $a->duracao_seg,
                'percentual' => (int) ($p->percentual ?? 0), 'posicao' => (int) ($p->posicao ?? 0), 'assistido_ate' => (int) ($p->assistido_ate ?? 0),
                'concluida' => $concluida, 'liberada' => $liberada, 'vezes_revista' => (int) ($p->vezes_revista ?? 0)];
        })->all();
    }

    /** Recebe o avanço do vídeo. Devolve o progresso aceito. */
    public static function progresso(object $matricula, object $aula, int $posicao, int $assistidoAte, ?int $duracao): array
    {
        $curso = DB::table('ead_cursos')->find($aula->curso_id);
        if ($duracao && $duracao > 0 && (! $aula->duracao_seg || abs($aula->duracao_seg - $duracao) > 2)) {
            DB::table('ead_aulas')->where('id', $aula->id)->update(['duracao_seg' => $duracao, 'updated_at' => now()]);
            $aula->duracao_seg = $duracao;
        }
        $p = DB::table('ead_progresso')->where('matricula_id', $matricula->id)->where('aula_id', $aula->id)->first();
        if (! $p) {
            DB::table('ead_progresso')->insert(['matricula_id' => $matricula->id, 'aula_id' => $aula->id, 'created_at' => now(), 'updated_at' => now()]);
            $p = DB::table('ead_progresso')->where('matricula_id', $matricula->id)->where('aula_id', $aula->id)->first();
        }
        // não aceita "pular": o máximo cresce no ritmo do relógio
        $decorrido = max(0, Carbon::parse($p->updated_at)->diffInSeconds(now(), true));
        $limite = (int) ($p->assistido_ate + $decorrido * self::FOLGA_FATOR + self::FOLGA_SEG);
        $novoMax = max((int) $p->assistido_ate, min($assistidoAte, $limite, (int) ($aula->duracao_seg ?: $assistidoAte)));
        $pct = $aula->duracao_seg ? (int) min(100, floor($novoMax * 100 / $aula->duracao_seg)) : 0;
        $concluiuAgora = ! $p->concluida_em && $pct >= (int) $curso->percentual_minimo;
        DB::table('ead_progresso')->where('id', $p->id)->update(['assistido_ate' => $novoMax, 'posicao' => max(0, $posicao), 'percentual' => max($pct, (int) $p->percentual),
            'concluida_em' => $p->concluida_em ?: ($concluiuAgora ? now() : null),
            'vezes_revista' => $p->concluida_em && $posicao < 5 && $p->posicao > 30 ? $p->vezes_revista + 1 : $p->vezes_revista, 'updated_at' => now()]);
        if ($concluiuAgora) {
            self::conferirAulas($matricula, $curso);
        }

        return ['assistido_ate' => $novoMax, 'percentual' => max($pct, (int) $p->percentual), 'concluida' => (bool) ($p->concluida_em || $concluiuAgora), 'concluiu_agora' => $concluiuAgora];
    }

    private static function conferirAulas(object $matricula, object $curso): void
    {
        $total = DB::table('ead_aulas')->where('curso_id', $curso->id)->count();
        $feitas = DB::table('ead_progresso as p')->join('ead_aulas as a', 'a.id', '=', 'p.aula_id')->where('a.curso_id', $curso->id)
            ->where('p.matricula_id', $matricula->id)->whereNotNull('p.concluida_em')->count();
        if ($total > 0 && $feitas >= $total) {
            DB::table('ead_matriculas')->where('id', $matricula->id)->whereNull('aulas_concluidas_em')->update(['aulas_concluidas_em' => now(), 'updated_at' => now()]);
            if (! DB::table('ead_questoes')->where('curso_id', $curso->id)->exists()) {
                // curso sem avaliação: concluir assistindo
                self::aprovar($matricula, 100);
            }
        }
    }

    /** Corrige a avaliação, guarda a tentativa e aprova se atingir a nota mínima. */
    public static function avaliar(object $matricula, object $curso, array $respostas): array
    {
        $questoes = DB::table('ead_questoes')->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->get();
        $pesoTotal = max(1, $questoes->sum('peso'));
        $acertos = $questoes->filter(fn ($q) => isset($respostas[$q->id]) && (int) $respostas[$q->id] === (int) $q->correta);
        $nota = round(100 * $acertos->sum('peso') / $pesoTotal, 1);
        $aprovado = $nota >= (float) $curso->nota_minima;
        DB::table('ead_tentativas')->insert(['matricula_id' => $matricula->id, 'respostas' => json_encode($respostas), 'nota' => $nota, 'aprovado' => $aprovado, 'created_at' => now()]);
        DB::table('ead_matriculas')->where('id', $matricula->id)->update(['nota' => max($nota, (float) ($matricula->nota ?? 0)), 'updated_at' => now()]);
        if ($aprovado) {
            self::aprovar($matricula, $nota);
        }

        return ['nota' => $nota, 'aprovado' => $aprovado, 'acertos' => $acertos->count(), 'total' => $questoes->count()];
    }

    private static function aprovar(object $matricula, float $nota): void
    {
        DB::table('ead_matriculas')->where('id', $matricula->id)->update(['aprovado' => true, 'nota' => DB::raw('greatest(coalesce(nota, 0), ' . (float) $nota . ')'),
            'concluido_em' => DB::raw('coalesce(concluido_em, now())'), 'certificado_codigo' => DB::raw("coalesce(certificado_codigo, '" . Str::uuid() . "'::uuid)"),
            'updated_at' => now()]);
    }

    public static function tentativasRestantes(object $curso, ?object $matricula): ?int
    {
        if (! $curso->tentativas) {
            return null;
        }

        return max(0, (int) $curso->tentativas - ($matricula ? DB::table('ead_tentativas')->where('matricula_id', $matricula->id)->count() : 0));
    }

    /** Resumo do curso para o servidor: % das aulas, situação. */
    public static function resumo(object $curso, ?object $m): array
    {
        $total = DB::table('ead_aulas')->where('curso_id', $curso->id)->count();
        $feitas = $m ? DB::table('ead_progresso as p')->join('ead_aulas as a', 'a.id', '=', 'p.aula_id')->where('a.curso_id', $curso->id)
            ->where('p.matricula_id', $m->id)->whereNotNull('p.concluida_em')->count() : 0;
        $situacao = ! $m ? 'nao_iniciado' : ($m->aprovado ? 'concluido' : ($m->aulas_concluidas_em ? 'avaliacao' : 'em_andamento'));

        return ['aulas' => $total, 'aulas_concluidas' => $feitas, 'percentual' => $total ? (int) floor($feitas * 100 / $total) : 0, 'situacao' => $situacao,
            'nota' => $m?->nota !== null ? (float) $m->nota : null, 'concluido_em' => $m?->concluido_em ? Carbon::parse($m->concluido_em, 'UTC')->toIso8601String() : null,
            'certificado' => $m?->certificado_codigo ? "/ead/certificados/{$m->certificado_codigo}/pdf" : null];
    }
}
