<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notificacao;
use App\Models\Processo\Prazo;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\ProcessoHistorico;
use App\Models\Processo\Tramitacao;
use App\Models\User;
use App\Services\AcaoNoProcesso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Processo parado ou ligado a outro:
 * - sobrestamento: motivo e prazo ou condição de retomada; enquanto sobrestado ninguém tramita e os
 *   prazos não correm — na retomada (manual, ou automática no dia marcado) os prazos das etapas ativas e
 *   os prazos lançados à mão são empurrados pelo tempo que o processo ficou parado;
 * - apensação: o apenso para (as etapas dele se encerram) e acompanha o principal; a desapensação devolve
 *   o apenso à tramitação, no setor em que o principal está;
 * - anexação: definitiva — as peças do anexado passam ao principal e o anexado se encerra.
 * Tudo fica no histórico dos processos envolvidos.
 */
final class VinculosProcesso
{
    public const TIPOS = ['apenso' => 'Apensado', 'anexo' => 'Anexado'];

    public static function sobrestar(Processo $p, User $u, string $motivo, ?string $ate, ?string $condicao): void
    {
        if (AcaoNoProcesso::parado($p)) {
            throw new RuntimeException('O processo já está parado.');
        }
        if (! $ate && ! trim((string) $condicao)) {
            throw new RuntimeException('Informe até quando o processo fica sobrestado ou a condição para retomá-lo.');
        }
        $p->update(['sobrestado_em' => now(), 'sobrestado_ate' => $ate ?: null, 'sobrestamento_motivo' => $motivo,
            'sobrestamento_condicao' => $condicao ? trim($condicao) : null, 'sobrestado_por' => $u->id]);
        self::historico($p, $u, 'sobrestamento', ['motivo' => $motivo, 'ate' => $ate, 'condicao' => $condicao]);
    }

    /** Retoma o processo sobrestado: os prazos ativos andam o tempo que ele ficou parado. */
    public static function retomar(Processo $p, ?User $u, string $observacao = ''): int
    {
        if (! $p->sobrestado_em) {
            throw new RuntimeException('O processo não está sobrestado.');
        }
        $parado = (int) $p->sobrestado_em->diffInSeconds(now(), true);
        $ativas = Tramitacao::where('processo_id', $p->id)->whereIn('status', ['pendente', 'recebido'])->whereNotNull('prazo')->get();
        foreach ($ativas as $t) {
            $t->update(['prazo' => Carbon::parse($t->prazo)->addSeconds($parado)]);
        }
        foreach (Prazo::where('processo_id', $p->id)->whereNull('concluido_em')->get() as $pr) {
            $pr->update(['vence_em' => $pr->vence_em->copy()->addSeconds($parado), 'alertado_em' => null]);
        }
        $dias = round($parado / 86400, 1);
        $p->update(['sobrestado_em' => null, 'sobrestado_ate' => null, 'sobrestamento_motivo' => null, 'sobrestamento_condicao' => null, 'sobrestado_por' => null]);
        self::historico($p, $u, 'retomada', ['dias_parado' => $dias, 'automatica' => $u === null, 'observacao' => $observacao ?: null]);
        if ($u === null) {
            self::avisarSetorAtual($p, "Processo {$p->numero_protocolo} retomado", 'O prazo do sobrestamento terminou: o processo voltou a tramitar e os prazos foram estendidos.');
        }

        return $ativas->count();
    }

    /** Retomada automática dos sobrestamentos vencidos (rotina diária). */
    public static function retomarVencidos(): int
    {
        $n = 0;
        foreach (Processo::withoutGlobalScopes()->whereNotNull('sobrestado_em')->whereNotNull('sobrestado_ate')
            ->whereDate('sobrestado_ate', '<=', now('America/Sao_Paulo')->toDateString())->get() as $p) {
            self::retomar($p, null);
            $n++;
        }

        return $n;
    }

    public static function apensar(Processo $principal, Processo $apenso, User $u, string $motivo): void
    {
        self::conferirPar($principal, $apenso);
        DB::table('proc_vinculos')->insert(['processo_id' => $principal->id, 'vinculado_id' => $apenso->id, 'tipo' => 'apenso',
            'motivo' => $motivo, 'criado_por' => $u->id, 'created_at' => now()]);
        self::encerrarEtapas($apenso, "Apensado ao processo {$principal->numero_protocolo}.");
        $apenso->update(['apensado_a_id' => $principal->id]);
        self::historico($principal, $u, 'apensacao', ['apenso' => $apenso->numero_protocolo, 'apenso_id' => $apenso->id, 'motivo' => $motivo]);
        self::historico($apenso, $u, 'apensado', ['principal' => $principal->numero_protocolo, 'principal_id' => $principal->id, 'motivo' => $motivo]);
    }

    /** Desapensa: o apenso volta a tramitar no setor em que o principal está agora. */
    public static function desapensar(Processo $principal, Processo $apenso, User $u, string $motivo): void
    {
        $vinculo = DB::table('proc_vinculos')->where('processo_id', $principal->id)->where('vinculado_id', $apenso->id)
            ->where('tipo', 'apenso')->whereNull('desapensado_em')->first();
        if (! $vinculo || (int) $apenso->apensado_a_id !== $principal->id) {
            throw new RuntimeException('Este processo não está apensado ao principal.');
        }
        $etapa = AcaoNoProcesso::etapaAtual($principal);
        $ultima = (int) Tramitacao::where('processo_id', $apenso->id)->max('ordem');
        $tipo = $apenso->tipoProcesso;
        $nova = Tramitacao::create([
            'processo_id' => $apenso->id, 'ordem' => $ultima + 1, 'setor_origem' => $etapa?->setor_destino,
            'setor_destino' => $etapa?->setor_destino, 'destino_unidade_id' => $etapa?->destino_unidade_id, 'remetente_id' => $u->id,
            'destinatario_id' => null, 'status' => 'pendente', 'sla_horas' => $tipo?->sla_padrao_horas,
            'prazo' => $tipo ? Expediente::prazo($tipo, $tipo->sla_padrao_horas ?? 72) : null, 'frente' => 1,
            'despacho' => "Desapensado do processo {$principal->numero_protocolo}. Motivo: {$motivo}",
        ]);
        $apenso->update(['apensado_a_id' => null, 'etapa_atual_id' => $nova->id, 'status' => 'em_tramitacao']);
        DB::table('proc_vinculos')->where('id', $vinculo->id)->update(['desapensado_em' => now(), 'desapensado_por' => $u->id, 'motivo_desapensacao' => $motivo]);
        self::historico($principal, $u, 'desapensacao', ['apenso' => $apenso->numero_protocolo, 'apenso_id' => $apenso->id, 'motivo' => $motivo]);
        self::historico($apenso, $u, 'desapensado', ['principal' => $principal->numero_protocolo, 'principal_id' => $principal->id, 'motivo' => $motivo, 'setor' => $etapa?->setor_destino]);
    }

    /** Anexação definitiva: as peças do anexado passam a integrar o principal e o anexado se encerra. */
    public static function anexar(Processo $principal, Processo $anexado, User $u, string $motivo): int
    {
        self::conferirPar($principal, $anexado);
        $pecas = 0;
        foreach (ProcessoAnexo::where('processo_id', $anexado->id)->orderBy('id')->get() as $a) {
            ProcessoAnexo::create([
                'processo_id' => $principal->id, 'tramitacao_id' => $principal->etapa_atual_id,
                'nome' => "[{$anexado->numero_protocolo}] {$a->nome}", 'arquivo_path' => $a->arquivo_path, 'tamanho' => $a->tamanho,
                'mime_type' => $a->mime_type, 'hash_sha256' => $a->hash_sha256, 'enviado_por' => $a->enviado_por, 'documento_id' => $a->documento_id,
            ]);
            $pecas++;
        }
        DB::table('proc_vinculos')->insert(['processo_id' => $principal->id, 'vinculado_id' => $anexado->id, 'tipo' => 'anexo',
            'motivo' => $motivo, 'criado_por' => $u->id, 'created_at' => now()]);
        self::encerrarEtapas($anexado, "Anexado ao processo {$principal->numero_protocolo}.");
        $anexado->update(['anexado_a_id' => $principal->id, 'status' => 'concluido', 'concluido_em' => now(), 'concluido_por' => $u->id,
            'observacao_conclusao' => "Anexado ao processo {$principal->numero_protocolo}. {$motivo}"]);
        self::historico($principal, $u, 'anexacao', ['anexado' => $anexado->numero_protocolo, 'anexado_id' => $anexado->id, 'pecas' => $pecas, 'motivo' => $motivo]);
        self::historico($anexado, $u, 'anexado', ['principal' => $principal->numero_protocolo, 'principal_id' => $principal->id, 'motivo' => $motivo]);

        return $pecas;
    }

    /** Ao concluir ou cancelar o principal, os apensos se encerram com ele. */
    public static function encerrarApensos(Processo $principal, User $u, string $status): int
    {
        $n = 0;
        foreach (Processo::where('apensado_a_id', $principal->id)->get() as $apenso) {
            $apenso->update(['status' => $status === 'cancelado' ? 'cancelado' : 'concluido', 'concluido_em' => now(), 'concluido_por' => $u->id,
                'observacao_conclusao' => "Encerrado junto com o processo principal {$principal->numero_protocolo}."]);
            self::historico($apenso, $u, 'encerrado_com_principal', ['principal' => $principal->numero_protocolo, 'principal_id' => $principal->id]);
            $n++;
        }

        return $n;
    }

    /** Para a tela do processo: vínculos, situação de parado e o que o usuário pode fazer. */
    public static function doProcesso(Processo $p, User $u): array
    {
        $acoes = AcaoNoProcesso::avaliar($p, $u);
        $num = fn ($id) => $id ? Processo::withoutGlobalScopes()->whereKey($id)->first(['id', 'numero_protocolo', 'assunto', 'status']) : null;
        $vinculos = DB::table('proc_vinculos as v')->join('proc_processos as pp', 'pp.id', '=', 'v.vinculado_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.criado_por')->where('v.processo_id', $p->id)->orderBy('v.id')
            ->get(['v.id', 'v.tipo', 'v.motivo', 'v.created_at', 'v.desapensado_em', 'v.motivo_desapensacao', 'pp.id as processo_id',
                'pp.numero_protocolo', 'pp.assunto', 'pp.status', 'u.name as por']);

        return [
            'parado' => AcaoNoProcesso::parado($p),
            'sobrestamento' => $p->sobrestado_em ? ['desde' => $p->sobrestado_em->toIso8601String(), 'ate' => $p->sobrestado_ate?->toDateString(),
                'motivo' => $p->sobrestamento_motivo, 'condicao' => $p->sobrestamento_condicao,
                'por' => $p->sobrestado_por ? User::whereKey($p->sobrestado_por)->value('name') : null] : null,
            'apensado_a' => $num($p->apensado_a_id),
            'anexado_a' => $num($p->anexado_a_id),
            'vinculos' => $vinculos,
            'pode' => ['sobrestar' => $acoes['sobrestar'], 'retomar' => $acoes['retomar'], 'vincular' => $acoes['vincular'],
                'desapensar' => ! in_array($p->status, ['concluido', 'cancelado'], true) && ($acoes['vincular'] || $acoes['despachar'])],
        ];
    }

    // ---------------------------------------------------------------- apoio

    private static function conferirPar(Processo $principal, Processo $outro): void
    {
        if ($principal->id === $outro->id) {
            throw new RuntimeException('Escolha outro processo.');
        }
        if ((int) $principal->ug_id !== (int) $outro->ug_id) {
            throw new RuntimeException('Os dois processos têm de ser da mesma unidade gestora.');
        }
        if (AcaoNoProcesso::parado($principal)) {
            throw new RuntimeException('O processo principal está parado (sobrestado, apensado ou anexado).');
        }
        if (in_array($outro->status, ['concluido', 'cancelado', 'aguardando_assinatura'], true)) {
            throw new RuntimeException("O processo {$outro->numero_protocolo} já está encerrado.");
        }
        if (AcaoNoProcesso::parado($outro)) {
            throw new RuntimeException("O processo {$outro->numero_protocolo} já está sobrestado, apensado ou anexado.");
        }
        if (Processo::where('apensado_a_id', $outro->id)->exists()) {
            throw new RuntimeException("O processo {$outro->numero_protocolo} tem apensos: desapense-os antes.");
        }
    }

    private static function encerrarEtapas(Processo $p, string $despacho): void
    {
        Tramitacao::where('processo_id', $p->id)->whereIn('status', ['pendente', 'recebido'])
            ->update(['status' => 'concluido', 'despachado_em' => now(), 'despacho' => $despacho, 'recebido_em' => DB::raw('COALESCE(recebido_em, NOW())')]);
    }

    private static function avisarSetorAtual(Processo $p, string $titulo, string $mensagem): void
    {
        $etapa = AcaoNoProcesso::etapaAtual($p);
        $ids = $etapa?->destinatario_id ? [(int) $etapa->destinatario_id] : ($etapa?->destino_unidade_id ? Setores::servidores((int) $etapa->destino_unidade_id) : []);
        foreach (array_unique($ids) as $uid) {
            Notificacao::create(['usuario_id' => $uid, 'tipo' => 'processo', 'titulo' => $titulo, 'mensagem' => $mensagem,
                'referencia_tipo' => 'processo', 'referencia_id' => $p->id, 'lida' => false]);
        }
    }

    private static function historico(Processo $p, ?User $u, string $acao, array $detalhes): void
    {
        $req = app()->runningInConsole() ? null : request();
        ProcessoHistorico::create(['processo_id' => $p->id, 'usuario_id' => $u?->id, 'acao' => $acao,
            'detalhes' => array_filter($detalhes, fn ($v) => $v !== null && $v !== ''), 'ip' => $req?->ip(), 'user_agent' => $req?->userAgent()]);
    }
}
