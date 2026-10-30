<?php

declare(strict_types=1);

namespace App\Support;

use App\Integracoes\Tramita\Fabrica;
use App\Models\Notificacao;
use App\Models\Processo\Oficio;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use App\Models\Processo\Tramitacao;
use App\Models\User;
use App\Tenant\TenantStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tramita GOV.BR no GED (TR 4.28): envia ofício ou processo com os arquivos e guarda NRE, IDT, data e hora;
 * acompanha até o recibo de conclusão ou a recusa; recebe o que outros entes enviam (aguarda aceite ou aceita
 * sozinho), abre o processo com os arquivos e devolve o recibo; recusa com motivo e justificativa.
 */
final class Tramita
{
    public const MOTIVOS_RECUSA = ['destino_incorreto' => 'Destinatário incorreto (não é desta unidade)', 'formato_nao_suportado' => 'Formato de arquivo não suportado',
        'componente_corrompido' => 'Arquivo corrompido ou ilegível', 'componente_faltando' => 'Faltam documentos', 'duplicado' => 'Trâmite duplicado', 'outro' => 'Outro motivo'];

    public const STATUS = ['enviado' => 'Enviado — aguardando recibo', 'concluido' => 'Recebido pelo destino', 'recusado' => 'Recusado',
        'erro' => 'Erro no envio', 'aguardando_aceite' => 'Aguardando aceite', 'recebido' => 'Recebido e aceito'];

    private const MAX_ARQUIVO = 50 * 1024 * 1024;

    public static function ativo(): bool
    {
        return (bool) Fabrica::config()->ativo;
    }

    public static function enviarOficio(Oficio $o, array $destino, User $u): object
    {
        $componentes = [self::componente('oficio-' . str_replace(['/', '\\'], '-', $o->numero) . '.pdf', 'application/pdf', app(\App\Services\OficioEmailService::class)->pdf($o))];
        foreach (DB::table('proc_oficio_anexos')->where('oficio_id', $o->id)->orderBy('id')->get() as $a) {
            if ($c = self::doDisco($a->nome, $a->mime_type, $a->arquivo_path)) {
                $componentes[] = $c;
            }
        }
        $meta = ['tipo' => 'oficio', 'numero' => $o->numero, 'assunto' => $o->assunto, 'data' => Carbon::parse($o->created_at)->toDateString(),
            'remetente' => Fabrica::config()->estrutura_nome, 'interessados' => array_values(array_filter([$o->destinatario_orgao, $o->destinatario_nome]))];

        return self::enviar('oficio', ['oficio_id' => $o->id], "Ofício {$o->numero} — {$o->assunto}", $meta, $componentes, $destino, $u);
    }

    public static function enviarProcesso(Processo $p, array $destino, User $u): object
    {
        $componentes = [];
        foreach (ProcessoAnexo::where('processo_id', $p->id)->orderBy('ordem')->orderBy('id')->get() as $a) {
            if ($c = self::doDisco($a->nome, $a->mime_type, $a->arquivo_path)) {
                $componentes[] = $c;
            }
        }
        $meta = ['tipo' => 'processo', 'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'descricao' => $p->descricao,
            'data' => $p->created_at?->toDateString(), 'especie' => $p->tipoProcesso?->nome, 'remetente' => Fabrica::config()->estrutura_nome,
            'interessados' => array_values(array_filter([$p->requerente_nome]))];
        $t = self::enviar('processo', ['processo_id' => $p->id], "Processo {$p->numero_protocolo} — {$p->assunto}", $meta, $componentes, $destino, $u);
        DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u->id, 'acao' => 'tramita_enviado',
            'detalhes' => json_encode(['destino' => $destino['nome'] ?? '', 'nre' => $t->nre, 'idt' => $t->idt], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);

        return $t;
    }

    private static function enviar(string $tipo, array $ref, string $assunto, array $meta, array $componentes, array $destino, User $u): object
    {
        $meta['documentos'] = array_map(fn ($c) => ['nome' => $c['nome'], 'mime' => $c['mime'], 'tamanho' => strlen($c['conteudo']), 'hash' => $c['hash']], $componentes);
        $r = Fabrica::barramento()->enviar($meta, $componentes, $destino);
        $id = DB::table('tramita_tramites')->insertGetId($ref + [
            'sentido' => 'enviado', 'tipo' => $tipo, 'status' => 'enviado', 'nre' => $r['nre'], 'idt' => $r['idt'],
            'outro_repositorio' => $destino['repositorio'] ?? null, 'outro_estrutura' => $destino['estrutura'], 'outro_nome' => $destino['nome'] ?? null,
            'assunto' => mb_substr($assunto, 0, 300), 'metadados' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'componentes' => json_encode($meta['documentos'], JSON_UNESCAPED_UNICODE), 'usuario_id' => $u->id,
            'enviado_em' => Carbon::parse($r['enviado_em'])->utc(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('tramita_tramites')->find($id);
    }

    /** Acompanha os enviados e busca o que chegou. Devolve um resumo para o comando/tela. */
    public static function sincronizar(): array
    {
        $cfg = Fabrica::config();
        $bar = Fabrica::barramento($cfg);
        $res = ['concluidos' => 0, 'recusados' => 0, 'recebidos' => 0, 'erros' => []];
        foreach (DB::table('tramita_tramites')->where('sentido', 'enviado')->where('status', 'enviado')->get() as $t) {
            try {
                $s = $bar->situacao($t->idt);
                if ($s['status'] === 'concluido') {
                    DB::table('tramita_tramites')->where('id', $t->id)->update(['status' => 'concluido', 'recibo' => json_encode($s['recibo'] ?? [], JSON_UNESCAPED_UNICODE),
                        'concluido_em' => isset($s['recibo']['data_hora']) ? Carbon::parse($s['recibo']['data_hora'])->utc() : now(), 'updated_at' => now()]);
                    self::avisar($t->usuario_id, "Tramita: {$t->assunto} recebido pelo destino", "NRE {$t->nre} · IDT {$t->idt} · {$t->outro_nome}", $t);
                    $res['concluidos']++;
                } elseif ($s['status'] === 'recusado') {
                    DB::table('tramita_tramites')->where('id', $t->id)->update(['status' => 'recusado', 'recusa_motivo' => $s['motivo'] ?? 'outro',
                        'recusa_justificativa' => $s['justificativa'] ?? null, 'concluido_em' => now(), 'updated_at' => now()]);
                    self::avisar($t->usuario_id, "Tramita: {$t->assunto} foi RECUSADO", (self::MOTIVOS_RECUSA[$s['motivo'] ?? 'outro'] ?? $s['motivo']) . '. ' . ($s['justificativa'] ?? ''), $t);
                    $res['recusados']++;
                }
            } catch (\Throwable $e) {
                $res['erros'][] = "{$t->idt}: {$e->getMessage()}";
            }
        }
        try {
            foreach ($bar->pendencias() as $p) {
                if (DB::table('tramita_tramites')->where('sentido', 'recebido')->where('idt', $p['idt'])->exists()) {
                    continue;
                }
                $t = self::guardarRecebido($p['idt'], $bar->receber($p['idt']));
                $res['recebidos']++;
                if ($cfg->aceite_automatico) {
                    self::aceitar($t, null);
                } elseif ($cfg->unidade_entrada_id) {
                    foreach (DB::table('users')->where('unidade_id', $cfg->unidade_entrada_id)->whereNull('suspenso_em')->pluck('id') as $uid) {
                        self::avisar($uid, "Tramita: chegou {$t->assunto}", "De {$t->outro_nome}. Aceite ou recuse em Tramita GOV.BR → Recebidos.", $t);
                    }
                }
            }
        } catch (\Throwable $e) {
            $res['erros'][] = 'Pendências: ' . $e->getMessage();
        }
        DB::table('tramita_config')->update(['sincronizado_em' => now()]);

        return $res;
    }

    private static function guardarRecebido(string $idt, array $r): object
    {
        $comps = [];
        foreach ($r['componentes'] as $c) {
            $hashOk = hash('sha256', $c['conteudo']) === $c['hash'];
            $path = TenantStorage::pasta('tramita') . '/' . Str::uuid() . '.' . (pathinfo($c['nome'], PATHINFO_EXTENSION) ?: 'bin');
            Storage::disk('documentos')->put($path, $c['conteudo']);
            $comps[] = ['nome' => $c['nome'], 'mime' => $c['mime'], 'tamanho' => strlen($c['conteudo']), 'hash' => $c['hash'], 'hash_ok' => $hashOk, 'path' => $path];
        }
        $m = $r['metadados'];
        $id = DB::table('tramita_tramites')->insertGetId(['sentido' => 'recebido', 'tipo' => ($m['tipo'] ?? 'processo') === 'oficio' ? 'oficio' : 'processo',
            'status' => 'aguardando_aceite', 'nre' => $r['nre'], 'idt' => $idt, 'outro_repositorio' => $r['remetente']['repositorio'] ?? null,
            'outro_estrutura' => $r['remetente']['estrutura'] ?? null, 'outro_nome' => $r['remetente']['nome'] ?? null,
            'assunto' => mb_substr(trim(($m['numero'] ?? '') . ' — ' . ($m['assunto'] ?? '')), 0, 300), 'metadados' => json_encode($m, JSON_UNESCAPED_UNICODE),
            'componentes' => json_encode($comps, JSON_UNESCAPED_UNICODE), 'enviado_em' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('tramita_tramites')->find($id);
    }

    /** Aceita o recebido: abre o processo com os arquivos e devolve o recibo ao barramento. */
    public static function aceitar(object $t, ?User $u): Processo
    {
        $cfg = Fabrica::config();
        $comps = json_decode((string) $t->componentes, true) ?: [];
        if (collect($comps)->contains('hash_ok', false)) {
            throw new \App\Integracoes\Tramita\FalhaTramita('Há arquivo com hash diferente do informado: recuse com o motivo "Arquivo corrompido".');
        }
        $tipo = $cfg->tipo_recebido_id ? TipoProcesso::find($cfg->tipo_recebido_id) : null;
        $unidade = $cfg->unidade_entrada_id ? DB::table('ug_organograma')->find($cfg->unidade_entrada_id) : null;
        if (! $tipo || ! $unidade) {
            throw new \App\Integracoes\Tramita\FalhaTramita('Configure o assunto e a unidade de entrada dos recebidos em Tramita GOV.BR → Configuração.');
        }
        $m = json_decode((string) $t->metadados, true) ?: [];
        $p = DB::transaction(function () use ($t, $u, $tipo, $unidade, $m, $comps) {
            $ano = date('Y');
            DB::select('select pg_advisory_xact_lock(?)', [crc32("prot-{$tipo->id}-{$ano}")]);
            $ultimo = Processo::withoutGlobalScopes()->where('tipo_processo_id', $tipo->id)->whereYear('created_at', $ano)
                ->max(DB::raw("CAST(SPLIT_PART(numero_protocolo, '/', 2) AS INTEGER)"));
            $p = Processo::withoutGlobalScopes()->create([
                'ug_id' => $unidade->ug_id, 'numero_protocolo' => $tipo->sigla . '-' . $ano . '/' . str_pad((string) (((int) $ultimo) + 1), 6, '0', STR_PAD_LEFT),
                'tipo_processo_id' => $tipo->id, 'assunto' => mb_substr($m['assunto'] ?? $t->assunto, 0, 250),
                'descricao' => trim(($m['descricao'] ?? '') . "\n\nRecebido pelo Tramita GOV.BR de {$t->outro_nome}. Documento de origem: " . ($m['numero'] ?? '-') . ". NRE {$t->nre}, IDT {$t->idt}."),
                'dados_formulario' => ['Origem (Tramita)' => $t->outro_nome, 'Documento de origem' => $m['numero'] ?? null, 'NRE' => $t->nre, 'IDT' => $t->idt],
                'requerente_nome' => mb_substr((string) $t->outro_nome, 0, 200), 'setor_origem' => 'Tramita GOV.BR', 'status' => 'aberto',
                'prioridade' => 'normal', 'aberto_por' => $u?->id, 'canal' => 'tramita', 'identificacao' => 'aberta',
            ]);
            $etapa = TipoEtapa::where('tipo_processo_id', $tipo->id)->orderBy('ordem')->first();
            $tr = Tramitacao::create(['processo_id' => $p->id, 'tipo_etapa_id' => $etapa?->id, 'ordem' => 1, 'setor_origem' => 'Tramita GOV.BR',
                'setor_destino' => $unidade->nome, 'destino_unidade_id' => $unidade->id, 'remetente_id' => $u?->id, 'destinatario_id' => $etapa?->responsavel_id,
                'status' => 'pendente', 'despachado_em' => now(), 'sla_horas' => $etapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo' => Expediente::prazo($tipo, $etapa?->sla_horas ?? $tipo->sla_padrao_horas ?? 72)]);
            $p->update(['etapa_atual_id' => $tr->id]);
            foreach ($comps as $c) {
                $novo = TenantStorage::pasta('processos') . '/' . Str::uuid() . '.' . (pathinfo($c['nome'], PATHINFO_EXTENSION) ?: 'bin');
                Storage::disk('documentos')->copy($c['path'], $novo);
                ProcessoAnexo::create(['processo_id' => $p->id, 'tramitacao_id' => $tr->id, 'nome' => mb_substr($c['nome'], 0, 250), 'arquivo_path' => $novo,
                    'tamanho' => $c['tamanho'], 'mime_type' => $c['mime'], 'hash_sha256' => $c['hash'], 'enviado_por' => $u?->id]);
            }
            DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u?->id, 'acao' => 'tramita_recebido',
                'detalhes' => json_encode(['origem' => $t->outro_nome, 'nre' => $t->nre, 'idt' => $t->idt], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);

            return $p;
        });
        $recibo = Fabrica::barramento($cfg)->confirmarRecebimento($t->idt, array_column($comps, 'hash'));
        DB::table('tramita_tramites')->where('id', $t->id)->update(['status' => 'recebido', 'processo_id' => $p->id, 'usuario_id' => $u?->id,
            'recibo' => json_encode($recibo, JSON_UNESCAPED_UNICODE), 'concluido_em' => now(), 'updated_at' => now()]);

        return $p;
    }

    public static function recusar(object $t, string $motivo, string $justificativa, User $u): void
    {
        Fabrica::barramento()->recusar($t->idt, $motivo, $justificativa);
        DB::table('tramita_tramites')->where('id', $t->id)->update(['status' => 'recusado', 'recusa_motivo' => $motivo, 'recusa_justificativa' => $justificativa,
            'usuario_id' => $u->id, 'concluido_em' => now(), 'updated_at' => now()]);
    }

    /** @return array{nome: string, mime: string, conteudo: string, hash: string}|null */
    private static function doDisco(string $nome, ?string $mime, string $path): ?array
    {
        $disco = Storage::disk('documentos');
        if (! $disco->exists($path) || $disco->size($path) > self::MAX_ARQUIVO) {
            return null;
        }

        return self::componente($nome, $mime ?: 'application/octet-stream', $disco->get($path));
    }

    private static function componente(string $nome, string $mime, string $conteudo): array
    {
        return ['nome' => $nome, 'mime' => $mime, 'conteudo' => $conteudo, 'hash' => hash('sha256', $conteudo)];
    }

    private static function avisar(?int $uid, string $titulo, string $msg, object $t): void
    {
        if ($uid) {
            Notificacao::create(['usuario_id' => $uid, 'tipo' => 'tramita', 'titulo' => mb_substr($titulo, 0, 250), 'mensagem' => $msg,
                'referencia_tipo' => $t->processo_id ? 'processo' : null, 'referencia_id' => $t->processo_id, 'lida' => false]);
        }
    }
}
