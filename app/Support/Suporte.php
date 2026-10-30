<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\SuporteAtendimentoEmail;
use App\Models\Notificacao;
use App\Models\Processo\Processo;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use App\Models\Processo\Tramitacao;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Suporte na plataforma (TR 4.26.1–10): abertura (chat ou e-mail), mensagens, atendentes com nível e
 * presença, escalonamento 1 → 2 → 3 (o nível 3 abre o chamado técnico da TI como processo), outros
 * usuários e atendentes no mesmo atendimento, resolução com e-mail de avaliação ou reabertura, e o resumo
 * com atendentes e horários.
 */
final class Suporte
{
    public const STATUS = ['aberto' => 'Aguardando atendente', 'em_atendimento' => 'Em atendimento', 'resolvido' => 'Resolvido',
        'fechado' => 'Fechado', 'cancelado' => 'Cancelado'];

    public const NIVEIS = [1 => 'Nível 1 — atendimento', 2 => 'Nível 2 — especialista', 3 => 'Nível 3 — TI (chamado técnico)'];

    /** Atendente online: marcado disponível e com o painel aberto no último 2 min. */
    public static function atendentesDisponiveis(): array
    {
        return DB::table('sup_atendentes as a')->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.disponivel', true)->where('a.visto_em', '>=', now()->subMinutes(2))->whereNull('u.suspenso_em')
            ->orderBy('a.nivel')->orderBy('u.name')->get(['u.id', 'u.name as nome', 'a.nivel'])
            ->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome, 'nivel' => (int) $a->nivel])->all();
    }

    public static function ehAtendente(?User $u): bool
    {
        return $u && ($u->super_admin || $u->can('suporte.atender'));
    }

    public static function podeVer(?User $u, object $a): bool
    {
        return self::ehAtendente($u) || DB::table('sup_participantes')->where('atendimento_id', $a->id)->where('user_id', $u?->id)->exists();
    }

    public static function config(): object
    {
        return DB::table('sup_config')->first() ?? (object) ['email_entrada' => null, 'chamado_tipo_id' => null, 'chamado_unidade_id' => null, 'horas_fechamento' => 72];
    }

    public static function abrir(User $solicitante, string $titulo, string $descricao, ?int $assuntoId, string $canal = 'chat'): object
    {
        $id = DB::transaction(function () use ($solicitante, $titulo, $descricao, $assuntoId, $canal) {
            $ano = now('America/Sao_Paulo')->year;
            DB::select('select pg_advisory_xact_lock(?)', [crc32("suporte-{$ano}")]);
            $ultimo = (int) DB::table('sup_atendimentos')->where('numero', 'like', "SUP-{$ano}/%")->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));
            $id = DB::table('sup_atendimentos')->insertGetId([
                'numero' => "SUP-{$ano}/" . str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT), 'assunto_id' => $assuntoId,
                'titulo' => mb_substr($titulo, 0, 200), 'status' => 'aberto', 'nivel' => 1, 'solicitante_id' => $solicitante->id, 'canal' => $canal,
                'token' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('sup_participantes')->insert(['atendimento_id' => $id, 'user_id' => $solicitante->id, 'papel' => 'solicitante', 'created_at' => now()]);
            self::mensagem($id, $solicitante->id, $descricao);

            return $id;
        });
        $a = DB::table('sup_atendimentos')->find($id);
        self::sistema($id, 'Atendimento aberto' . ($canal === 'email' ? ' por e-mail' : '') . '. ' . (self::atendentesDisponiveis()
            ? 'Um atendente vai responder em instantes.' : 'Nenhum atendente on-line agora: a resposta chega aqui e por e-mail.'));
        self::avisarAtendentes($a, 1, "Novo atendimento {$a->numero}: {$a->titulo}");
        self::email($a, 'aberto');

        return $a;
    }

    public static function mensagem(int $atendimentoId, ?int $autorId, string $texto, string $tipo = 'mensagem'): int
    {
        $id = DB::table('sup_mensagens')->insertGetId(['atendimento_id' => $atendimentoId, 'autor_id' => $autorId, 'tipo' => $tipo,
            'conteudo' => mb_substr(trim($texto), 0, 10000), 'created_at' => now()]);
        DB::table('sup_atendimentos')->where('id', $atendimentoId)->update(['updated_at' => now()]);
        if ($autorId) {
            DB::table('sup_participantes')->where('atendimento_id', $atendimentoId)->where('user_id', $autorId)->update(['lido_ate' => $id]);
        }

        return $id;
    }

    public static function sistema(int $atendimentoId, string $texto): void
    {
        self::mensagem($atendimentoId, null, $texto, 'sistema');
    }

    /** Resposta de um atendente: entra no atendimento e marca a primeira resposta (SLA). */
    public static function responder(object $a, User $u, string $texto, bool $nota = false): int
    {
        $ehAtendente = self::ehAtendente($u) && (int) $a->solicitante_id !== $u->id;
        if ($ehAtendente) {
            self::participar($a->id, $u->id, 'atendente', $u->id);
            if (! $nota) {
                $mudar = ['updated_at' => now()];
                if (! $a->primeira_resposta_em) {
                    $mudar['primeira_resposta_em'] = now();
                }
                if ($a->status === 'aberto') {
                    $mudar += ['status' => 'em_atendimento', 'atendente_id' => $a->atendente_id ?: $u->id];
                }
                DB::table('sup_atendimentos')->where('id', $a->id)->update($mudar);
            }
        } elseif (in_array($a->status, ['resolvido', 'fechado'], true)) {
            // o usuário escreveu depois de resolvido: reabre
            self::reabrir($a, $u, 'mensagem nova do usuário');
        }

        return self::mensagem($a->id, $u->id, $texto, $nota && $ehAtendente ? 'nota' : 'mensagem');
    }

    public static function participar(int $atendimentoId, int $userId, string $papel, ?int $por): bool
    {
        return (bool) DB::table('sup_participantes')->insertOrIgnore(['atendimento_id' => $atendimentoId, 'user_id' => $userId, 'papel' => $papel,
            'adicionado_por' => $por, 'created_at' => now()]);
    }

    public static function assumir(object $a, User $u): void
    {
        self::participar($a->id, $u->id, 'atendente', $u->id);
        DB::table('sup_atendimentos')->where('id', $a->id)->update(['atendente_id' => $u->id, 'status' => in_array($a->status, ['aberto', 'resolvido'], true) ? 'em_atendimento' : $a->status,
            'updated_at' => now()]);
        self::sistema($a->id, "{$u->name} assumiu o atendimento.");
    }

    /** Inclui outro usuário (4.26.4) ou outro atendente (4.26.5) na conversa. */
    public static function incluir(object $a, User $quem, User $por): void
    {
        $papel = self::ehAtendente($quem) ? 'atendente' : 'convidado';
        if (self::participar($a->id, $quem->id, $papel, $por->id)) {
            self::sistema($a->id, "{$por->name} incluiu " . ($papel === 'atendente' ? 'o atendente ' : '') . "{$quem->name} no atendimento.");
            Notificacao::create(['usuario_id' => $quem->id, 'tipo' => 'suporte', 'titulo' => "Você foi incluído no atendimento {$a->numero}",
                'mensagem' => $a->titulo, 'referencia_tipo' => 'suporte', 'referencia_id' => $a->id, 'lida' => false]);
        }
    }

    /** Escalonamento (4.26.6): nível 2; no nível 3 abre o chamado técnico da TI como processo. */
    public static function escalar(object $a, User $u, string $motivo): ?Processo
    {
        $nivel = min(3, (int) $a->nivel + 1);
        $processo = null;
        if ($nivel === 3) {
            $processo = self::abrirChamadoTi($a, $u, $motivo);
        }
        DB::table('sup_atendimentos')->where('id', $a->id)->update(['nivel' => $nivel, 'atendente_id' => null, 'status' => 'aberto',
            'processo_id' => $processo?->id ?? $a->processo_id, 'updated_at' => now()]);
        self::sistema($a->id, "{$u->name} encaminhou para o " . self::NIVEIS[$nivel] . ". Motivo: {$motivo}"
            . ($processo ? " Chamado técnico {$processo->numero_protocolo} aberto para a TI." : ''));
        self::avisarAtendentes($a, $nivel, "Atendimento {$a->numero} encaminhado ao nível {$nivel}: {$a->titulo}");

        return $processo;
    }

    private static function abrirChamadoTi(object $a, User $u, string $motivo): ?Processo
    {
        $cfg = self::config();
        $tipo = $cfg->chamado_tipo_id ? TipoProcesso::find($cfg->chamado_tipo_id) : TipoProcesso::where('modalidade', 'chamado')->where('ativo', true)->first();
        $unidade = $cfg->chamado_unidade_id ?: null;
        if (! $tipo || ! $unidade) {
            return null;
        }
        $setor = DB::table('ug_organograma')->find($unidade);
        $solicitante = User::find($a->solicitante_id);
        $historico = DB::table('sup_mensagens')->where('atendimento_id', $a->id)->where('tipo', 'mensagem')->orderBy('id')->limit(30)->get()
            ->map(fn ($m) => '- ' . mb_substr($m->conteudo, 0, 500))->implode("\n");

        return DB::transaction(function () use ($a, $u, $motivo, $tipo, $setor, $solicitante, $historico) {
            $ano = date('Y');
            DB::select('select pg_advisory_xact_lock(?)', [crc32("prot-{$tipo->id}-{$ano}")]);
            $ultimo = Processo::withoutGlobalScopes()->where('tipo_processo_id', $tipo->id)->whereYear('created_at', $ano)
                ->max(DB::raw("CAST(SPLIT_PART(numero_protocolo, '/', 2) AS INTEGER)"));
            $p = Processo::create([
                'ug_id' => $setor->ug_id, 'numero_protocolo' => $tipo->sigla . '-' . $ano . '/' . str_pad((string) (((int) $ultimo) + 1), 6, '0', STR_PAD_LEFT),
                'tipo_processo_id' => $tipo->id, 'assunto' => mb_substr("[{$a->numero}] {$a->titulo}", 0, 250),
                'descricao' => "Escalonado do suporte ({$a->numero}) por {$u->name}.\nMotivo: {$motivo}\n\nConversa:\n{$historico}",
                'requerente_nome' => $solicitante?->name, 'requerente_email' => $solicitante?->email, 'solicitante_user_id' => $solicitante?->id,
                'setor_origem' => 'Suporte', 'status' => 'aberto', 'prioridade' => 'normal', 'aberto_por' => $u->id, 'canal' => 'interno',
            ]);
            $etapa = TipoEtapa::where('tipo_processo_id', $tipo->id)->orderBy('ordem')->first();
            $t = Tramitacao::create(['processo_id' => $p->id, 'tipo_etapa_id' => $etapa?->id, 'ordem' => 1, 'setor_origem' => 'Suporte',
                'setor_destino' => $setor->nome, 'destino_unidade_id' => $setor->id, 'remetente_id' => $u->id, 'destinatario_id' => $etapa?->responsavel_id,
                'status' => 'pendente', 'despachado_em' => now(), 'sla_horas' => $etapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo' => Expediente::prazo($tipo, $etapa?->sla_horas ?? $tipo->sla_padrao_horas ?? 72)]);
            $p->update(['etapa_atual_id' => $t->id]);
            DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u->id, 'acao' => 'aberto_pelo_suporte',
                'detalhes' => json_encode(['atendimento' => $a->numero, 'motivo' => $motivo], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);

            return $p;
        });
    }

    public static function resolver(object $a, User $u, string $solucao): void
    {
        self::participar($a->id, $u->id, 'atendente', $u->id);
        self::mensagem($a->id, $u->id, $solucao);
        DB::table('sup_atendimentos')->where('id', $a->id)->update(['status' => 'resolvido', 'resolvido_em' => now(), 'atendente_id' => $a->atendente_id ?: $u->id,
            'primeira_resposta_em' => $a->primeira_resposta_em ?: now(), 'updated_at' => now()]);
        self::sistema($a->id, "{$u->name} marcou como resolvido. O usuário recebeu o resumo por e-mail para avaliar ou reabrir.");
        self::email(DB::table('sup_atendimentos')->find($a->id), 'resolvido');
    }

    public static function reabrir(object $a, ?User $u, string $motivo): void
    {
        DB::table('sup_atendimentos')->where('id', $a->id)->update(['status' => $a->atendente_id ? 'em_atendimento' : 'aberto', 'resolvido_em' => null, 'fechado_em' => null,
            'reaberturas' => DB::raw('reaberturas + 1'), 'updated_at' => now()]);
        self::sistema($a->id, 'Atendimento reaberto' . ($u ? " por {$u->name}" : '') . ($motivo ? ": {$motivo}" : '.'));
        if ($a->atendente_id) {
            Notificacao::create(['usuario_id' => $a->atendente_id, 'tipo' => 'suporte', 'titulo' => "Atendimento {$a->numero} reaberto",
                'mensagem' => $motivo ?: $a->titulo, 'referencia_tipo' => 'suporte', 'referencia_id' => $a->id, 'lida' => false]);
        } else {
            self::avisarAtendentes($a, (int) $a->nivel, "Atendimento {$a->numero} reaberto: {$a->titulo}");
        }
    }

    /** O usuário concorda com a solução e avalia (4.26.8): fecha. */
    public static function avaliar(object $a, int $nota, ?string $comentario): void
    {
        DB::table('sup_atendimentos')->where('id', $a->id)->update(['avaliacao_nota' => $nota, 'avaliacao_comentario' => $comentario ? mb_substr($comentario, 0, 2000) : null,
            'avaliado_em' => now(), 'status' => 'fechado', 'fechado_em' => now(), 'updated_at' => now()]);
        self::sistema($a->id, "O usuário concordou com a solução e avaliou com nota {$nota}." . ($comentario ? " Comentário: {$comentario}" : ''));
    }

    /** Resumo do atendimento (4.26.9): quem atendeu e os horários. */
    public static function resumo(object $a): array
    {
        $fuso = 'America/Sao_Paulo';
        $h = fn ($t) => $t ? Carbon::parse($t, 'UTC')->setTimezone($fuso)->format('d/m/Y H:i') : null;
        $atendentes = DB::table('sup_participantes as p')->join('users as u', 'u.id', '=', 'p.user_id')->where('p.atendimento_id', $a->id)->where('p.papel', 'atendente')
            ->get(['u.id', 'u.name'])->map(function ($x) use ($a, $h) {
                $faixa = DB::table('sup_mensagens')->where('atendimento_id', $a->id)->where('autor_id', $x->id)->selectRaw('min(created_at) as de, max(created_at) as ate, count(*) as n')->first();

                return ['nome' => $x->name, 'de' => $h($faixa->de), 'ate' => $h($faixa->ate), 'mensagens' => (int) $faixa->n];
            })->all();
        $minutos = fn ($ini, $fim) => $ini && $fim ? (int) Carbon::parse($ini, 'UTC')->diffInMinutes(Carbon::parse($fim, 'UTC'), true) : null;

        return ['aberto_em' => $h($a->created_at), 'primeira_resposta_em' => $h($a->primeira_resposta_em), 'resolvido_em' => $h($a->resolvido_em),
            'fechado_em' => $h($a->fechado_em), 'min_primeira_resposta' => $minutos($a->created_at, $a->primeira_resposta_em),
            'min_resolucao' => $minutos($a->created_at, $a->resolvido_em), 'atendentes' => $atendentes];
    }

    private static function avisarAtendentes(object $a, int $nivel, string $titulo): void
    {
        foreach (DB::table('sup_atendentes')->where('nivel', '>=', $nivel)->where('disponivel', true)->pluck('user_id') as $uid) {
            if ((int) $uid === (int) $a->solicitante_id) {
                continue;
            }
            Notificacao::create(['usuario_id' => $uid, 'tipo' => 'suporte', 'titulo' => $titulo, 'mensagem' => 'Abra a Central de suporte para atender.',
                'referencia_tipo' => 'suporte', 'referencia_id' => $a->id, 'lida' => false]);
        }
    }

    /** E-mail ao usuário: número na abertura; resumo + avaliar/reabrir na resolução. Responder o e-mail cai na conversa. */
    public static function email(object $a, string $tipo): void
    {
        $u = User::find($a->solicitante_id);
        if (! $u?->email) {
            return;
        }
        RastreioEmail::enviar(new SuporteAtendimentoEmail($a, $tipo, self::resumo($a), RespostaPorEmail::endereco('suporte', (int) $a->id, $u->email, $u->id)),
            $u->email, 'servidor', ($tipo === 'resolvido' ? 'Atendimento resolvido: ' : 'Atendimento aberto: ') . $a->numero, 'suporte', (int) $a->id, null);
    }

    /** Fecha os resolvidos sem resposta do usuário depois do prazo configurado. */
    public static function fecharResolvidos(): int
    {
        $horas = (int) (self::config()->horas_fechamento ?: 72);
        $n = 0;
        foreach (DB::table('sup_atendimentos')->where('status', 'resolvido')->where('resolvido_em', '<', now()->subHours($horas))->get() as $a) {
            DB::table('sup_atendimentos')->where('id', $a->id)->update(['status' => 'fechado', 'fechado_em' => now(), 'updated_at' => now()]);
            self::sistema($a->id, "Fechado automaticamente: {$horas} h sem resposta do usuário depois de resolvido.");
            $n++;
        }

        return $n;
    }
}
