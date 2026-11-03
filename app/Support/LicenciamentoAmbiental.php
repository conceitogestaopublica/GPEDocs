<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\DecisaoManifestacao;
use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\Notificacao;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoHistorico;
use App\Models\Processo\Tramitacao;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use App\Models\Versao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Licenciamento ambiental sobre o motor de processos.
 *
 * Enquadramento: a atividade tem potencial poluidor (P, M, G) e faixas de porte; o porte informado dá
 * P, M ou G e a matriz porte × potencial dá a classe (1 a 6, referência DN COPAM nº 217/2017). A classe
 * e a modalidade dizem os documentos exigidos; a licença simplificada (LAS) só cabe nas classes 1 e 2.
 * Requerimento (portal ou balcão) → análise no processo (pareceres, exigências) → emissão com
 * condicionantes e validade, PDF com QR assinado pelo emissor — ou indeferimento → vencimento avisado ao
 * empreendedor (120 dias antes, prazo da renovação da LO) e renovação pelo portal.
 */
final class LicenciamentoAmbiental
{
    public const MODALIDADES = ['LP' => 'Licença Prévia', 'LI' => 'Licença de Instalação', 'LO' => 'Licença de Operação',
        'LAS' => 'Licença Ambiental Simplificada', 'LOC' => 'Licença de Operação Corretiva (regularização)', 'RENOVACAO' => 'Renovação da Licença de Operação'];

    /** Validade padrão em anos (Res. CONAMA nº 237/1997, art. 18). */
    public const VALIDADE = ['LP' => 5, 'LI' => 6, 'LO' => 10, 'LAS' => 10, 'LOC' => 10, 'RENOVACAO' => 10];

    public const PORTES = ['P' => 'Pequeno', 'M' => 'Médio', 'G' => 'Grande'];

    public const POTENCIAIS = ['P' => 'Pequeno', 'M' => 'Médio', 'G' => 'Grande'];

    /** Matriz porte (linha) × potencial poluidor (coluna) → classe. */
    private const CLASSES = ['P' => ['P' => 1, 'M' => 2, 'G' => 4], 'M' => ['P' => 1, 'M' => 3, 'G' => 5], 'G' => ['P' => 1, 'M' => 4, 'G' => 6]];

    public const STATUS = ['em_analise' => 'Em análise', 'emitida' => 'Emitida', 'indeferida' => 'Indeferida', 'vencida' => 'Vencida', 'renovada' => 'Renovada'];

    private const FUSO = 'America/Sao_Paulo';

    /** Enquadramento e documentos exigidos. Lança RuntimeException quando a modalidade não cabe. */
    public static function enquadrar(int $atividadeId, float $porteValor, string $modalidade): array
    {
        $a = DB::table('amb_atividades')->where('id', $atividadeId)->where('ativo', true)->first();
        if (! $a) {
            throw new RuntimeException('Atividade não encontrada.');
        }
        if (! isset(self::MODALIDADES[$modalidade])) {
            throw new RuntimeException('Modalidade inválida.');
        }
        $porte = $porteValor <= (float) $a->porte_pequeno_ate ? 'P' : ($porteValor <= (float) $a->porte_medio_ate ? 'M' : 'G');
        $classe = self::CLASSES[$porte][$a->potencial];
        if ($modalidade === 'LAS' && $classe > 2) {
            throw new RuntimeException("A licença simplificada (LAS) cabe só nas classes 1 e 2; este empreendimento é classe {$classe}.");
        }
        $docs = DB::table('amb_documentos')->where('ativo', true)->where('classe_minima', '<=', $classe)->orderBy('ordem')->orderBy('id')->get()
            ->filter(fn ($d) => in_array($modalidade, json_decode((string) $d->modalidades, true) ?: [], true))->values();

        return ['atividade' => ['id' => $a->id, 'codigo' => $a->codigo, 'descricao' => $a->descricao, 'unidade' => $a->unidade],
            'porte' => $porte, 'porte_rotulo' => self::PORTES[$porte], 'potencial' => $a->potencial, 'potencial_rotulo' => self::POTENCIAIS[$a->potencial],
            'classe' => $classe, 'modalidade' => $modalidade, 'modalidade_rotulo' => self::MODALIDADES[$modalidade],
            'documentos' => $docs->map(fn ($d) => ['id' => $d->id, 'nome' => $d->nome, 'obrigatorio' => (bool) $d->obrigatorio, 'extensoes' => $d->extensoes])->all()];
    }

    /** Registra o requerimento ligado ao processo (vindo do portal ou do balcão). */
    public static function registrar(Processo $p, array $enq, float $porteValor, array $dados, ?int $solicitacaoId = null, ?int $anteriorId = null): int
    {
        if (DB::table('amb_licencas')->where('processo_id', $p->id)->exists()) {
            throw new RuntimeException('Este processo já tem requerimento de licença.');
        }

        return DB::table('amb_licencas')->insertGetId([
            'ug_id' => $p->ug_id, 'processo_id' => $p->id, 'solicitacao_id' => $solicitacaoId, 'modalidade' => $enq['modalidade'],
            'atividade_id' => $enq['atividade']['id'], 'porte_valor' => $porteValor, 'porte' => $enq['porte'], 'potencial' => $enq['potencial'],
            'classe' => $enq['classe'], 'empreendedor_nome' => $dados['empreendedor_nome'], 'empreendedor_documento' => $dados['empreendedor_documento'] ?? null,
            'empreendedor_email' => $dados['empreendedor_email'] ?? null, 'empreendimento_nome' => $dados['empreendimento_nome'] ?? null,
            'empreendimento_endereco' => $dados['empreendimento_endereco'] ?? null, 'licenca_anterior_id' => $anteriorId,
            'status' => 'em_analise', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Emite a licença: número, validade, condicionantes, PDF com QR de verificação e solicitação de assinatura
     * para quem emitiu; encerra o processo como deferido e avisa o empreendedor.
     *
     * @param  list<array{descricao:string,prazo?:?string}>  $condicionantes
     */
    public static function emitir(object $l, User $u, array $condicionantes, int $validadeAnos): void
    {
        if ($l->status !== 'em_analise') {
            throw new RuntimeException('Só requerimento em análise pode ser emitido.');
        }
        $p = Processo::findOrFail($l->processo_id);
        $ano = now(self::FUSO)->year;
        DB::select('select pg_advisory_xact_lock(?)', [crc32("amb-licenca-{$ano}")]);
        $ultimo = (int) DB::table('amb_licencas')->where('numero', 'like', "LA-{$ano}/%")->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));
        $numero = "LA-{$ano}/" . str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
        $validaAte = now(self::FUSO)->addYears($validadeAnos)->toDateString();
        $codigo = (string) Str::uuid();
        DB::table('amb_licencas')->where('id', $l->id)->update(['numero' => $numero, 'status' => 'emitida', 'emitida_em' => now(), 'emitida_por' => $u->id,
            'valida_ate' => $validaAte, 'codigo_verificacao' => $codigo, 'updated_at' => now()]);
        foreach ($condicionantes as $c) {
            if (trim((string) ($c['descricao'] ?? '')) !== '') {
                DB::table('amb_condicionantes')->insert(['licenca_id' => $l->id, 'descricao' => trim($c['descricao']), 'prazo' => $c['prazo'] ?? null,
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if ($l->licenca_anterior_id) {
            DB::table('amb_licencas')->where('id', $l->licenca_anterior_id)->update(['status' => 'renovada', 'updated_at' => now()]);
        }
        $l = DB::table('amb_licencas')->find($l->id);
        $documento = self::gerarPdf($l, $p, $u);
        DB::table('amb_licencas')->where('id', $l->id)->update(['documento_id' => $documento->id]);
        $solicitacao = SolicitacaoAssinatura::create(['documento_id' => $documento->id, 'solicitante_id' => $u->id, 'status' => 'pendente',
            'mensagem' => "Licença ambiental {$numero} — assinar para tornar oficial."]);
        Assinatura::create(['solicitacao_id' => $solicitacao->id, 'documento_id' => $documento->id, 'signatario_id' => $u->id, 'ordem' => 1,
            'status' => 'pendente', 'email_signatario' => $u->email]);

        $texto = self::MODALIDADES[$l->modalidade] . " {$numero} emitida, válida até " . Carbon::parse($validaAte)->format('d/m/Y') . '.'
            . (count($condicionantes) ? ' Atenção às condicionantes da licença.' : '');
        self::encerrarProcesso($p, $u, 'deferido', $texto);
    }

    public static function indeferir(object $l, User $u, string $motivo): void
    {
        if ($l->status !== 'em_analise') {
            throw new RuntimeException('Só requerimento em análise pode ser indeferido.');
        }
        DB::table('amb_licencas')->where('id', $l->id)->update(['status' => 'indeferida', 'motivo_indeferimento' => $motivo, 'updated_at' => now()]);
        self::encerrarProcesso(Processo::findOrFail($l->processo_id), $u, 'indeferido', "Requerimento de licença ambiental indeferido. {$motivo}");
    }

    public static function cumprirCondicionante(object $c, User $u, string $cumprimento): void
    {
        DB::table('amb_condicionantes')->where('id', $c->id)->update(['cumprida_em' => now(), 'cumprimento' => $cumprimento,
            'cumprida_registrada_por' => $u->id, 'updated_at' => now()]);
    }

    /** Rotina diária: vencidas, aviso de vencimento (renovação) e de condicionantes perto do prazo. */
    public static function avisar(): array
    {
        $cfg = DB::table('amb_config')->first();
        $diasLic = (int) ($cfg->dias_aviso_vencimento ?? 120);
        $diasCond = (int) ($cfg->dias_aviso_condicionante ?? 15);
        $hoje = now(self::FUSO)->toDateString();
        $vencidas = DB::table('amb_licencas')->where('status', 'emitida')->where('valida_ate', '<', $hoje)->update(['status' => 'vencida', 'updated_at' => now()]);
        $avisos = 0;
        foreach (DB::table('amb_licencas')->where('status', 'emitida')->whereNull('aviso_vencimento_em')
            ->where('valida_ate', '<=', now(self::FUSO)->addDays($diasLic)->toDateString())->get() as $l) {
            $quando = Carbon::parse($l->valida_ate)->format('d/m/Y');
            self::avisarEmpreendedor($l, "A licença ambiental {$l->numero} vence em {$quando}. Peça a renovação pelo portal com antecedência mínima de {$diasLic} dias do vencimento.");
            self::avisarSetor($l, "Licença {$l->numero} vence em {$quando}", "Empreendimento: {$l->empreendimento_nome} ({$l->empreendedor_nome}).");
            DB::table('amb_licencas')->where('id', $l->id)->update(['aviso_vencimento_em' => now()]);
            $avisos++;
        }
        foreach (DB::table('amb_condicionantes as c')->join('amb_licencas as l', 'l.id', '=', 'c.licenca_id')->where('l.status', 'emitida')
            ->whereNull('c.cumprida_em')->whereNull('c.aviso_em')->whereNotNull('c.prazo')->where('c.prazo', '<=', now(self::FUSO)->addDays($diasCond)->toDateString())
            ->get(['c.*', 'l.numero', 'l.processo_id', 'l.empreendedor_email', 'l.empreendimento_nome', 'l.empreendedor_nome']) as $c) {
            $quando = Carbon::parse($c->prazo)->format('d/m/Y');
            $l = DB::table('amb_licencas')->find($c->licenca_id);
            self::avisarEmpreendedor($l, "Condicionante da licença {$l->numero} com prazo em {$quando}: {$c->descricao}");
            self::avisarSetor($l, "Condicionante da licença {$l->numero} vence em {$quando}", mb_substr($c->descricao, 0, 200));
            DB::table('amb_condicionantes')->where('id', $c->id)->update(['aviso_em' => now()]);
            $avisos++;
        }

        return ['vencidas' => $vencidas, 'avisos' => $avisos];
    }

    /** Página pública de verificação (QR do PDF). */
    public static function verificar(string $codigo): ?array
    {
        $l = DB::table('amb_licencas')->where('codigo_verificacao', $codigo)->first();
        if (! $l) {
            return null;
        }
        $a = DB::table('amb_atividades')->find($l->atividade_id);
        $assinada = $l->documento_id && DB::table('ged_assinaturas')->where('documento_id', $l->documento_id)->where('status', 'assinado')->exists();

        return ['numero' => $l->numero, 'modalidade' => self::MODALIDADES[$l->modalidade], 'situacao' => self::STATUS[$l->status] ?? $l->status,
            'valida' => $l->status === 'emitida' && $l->valida_ate >= now(self::FUSO)->toDateString(),
            'emitida_em' => Carbon::parse($l->emitida_em)->setTimezone(self::FUSO)->format('d/m/Y'), 'valida_ate' => Carbon::parse($l->valida_ate)->format('d/m/Y'),
            'empreendedor' => $l->empreendedor_nome, 'empreendimento' => $l->empreendimento_nome, 'endereco' => $l->empreendimento_endereco,
            'atividade' => $a ? "{$a->codigo} — {$a->descricao}" : null, 'classe' => (int) $l->classe, 'assinada' => $assinada,
            'condicionantes' => DB::table('amb_condicionantes')->where('licenca_id', $l->id)->orderBy('id')->pluck('descricao'),
            'orgao' => \App\Models\Ug::withoutGlobalScopes()->find($l->ug_id)?->nome];
    }

    /** Para a tela do processo: requerimento, licença, condicionantes e o que o usuário pode fazer. */
    public static function doProcesso(Processo $p, User $u): ?array
    {
        $l = DB::table('amb_licencas')->where('processo_id', $p->id)->first();
        $cfg = DB::table('amb_config')->first();
        $doServico = $cfg?->servico_id && DB::table('portal_servicos')->where('id', $cfg->servico_id)->value('tipo_processo_id') === $p->tipo_processo_id;
        if (! $l && ! $doServico) {
            return null;
        }
        $gere = $u->super_admin || $u->can('ambiental.gerir');
        $dados = ['pode_gerir' => $gere, 'modalidades' => self::MODALIDADES, 'validade' => self::VALIDADE];
        if (! $l) {
            return $dados + ['licenca' => null, 'atividades' => DB::table('amb_atividades')->where('ativo', true)->orderBy('codigo')->get(['id', 'codigo', 'descricao', 'unidade'])];
        }
        $a = DB::table('amb_atividades')->find($l->atividade_id);

        return $dados + ['licenca' => [
            'id' => $l->id, 'numero' => $l->numero, 'modalidade' => $l->modalidade, 'modalidade_rotulo' => self::MODALIDADES[$l->modalidade],
            'atividade' => $a ? "{$a->codigo} — {$a->descricao}" : null, 'porte_valor' => (float) $l->porte_valor, 'unidade' => $a?->unidade,
            'porte' => self::PORTES[$l->porte], 'potencial' => self::POTENCIAIS[$l->potencial], 'classe' => (int) $l->classe,
            'empreendedor' => $l->empreendedor_nome, 'documento' => $l->empreendedor_documento ? DocumentoPessoa::mascarar($l->empreendedor_documento) : null,
            'email' => $l->empreendedor_email, 'empreendimento' => $l->empreendimento_nome, 'endereco' => $l->empreendimento_endereco,
            'status' => $l->status, 'status_rotulo' => self::STATUS[$l->status] ?? $l->status,
            'emitida_em' => $l->emitida_em ? Carbon::parse($l->emitida_em)->toIso8601String() : null, 'valida_ate' => $l->valida_ate,
            'motivo_indeferimento' => $l->motivo_indeferimento, 'documento_id' => $l->documento_id,
            'verificacao_url' => $l->codigo_verificacao ? url("/ambiental/licencas/{$l->codigo_verificacao}") : null,
            'anterior' => $l->licenca_anterior_id ? DB::table('amb_licencas')->where('id', $l->licenca_anterior_id)->value('numero') : null,
            'condicionantes' => DB::table('amb_condicionantes')->where('licenca_id', $l->id)->orderBy('id')->get(['id', 'descricao', 'prazo', 'cumprida_em', 'cumprimento']),
        ]];
    }

    // ---------------------------------------------------------------- apoio

    private static function gerarPdf(object $l, Processo $p, User $u): Documento
    {
        $url = url("/ambiental/licencas/{$l->codigo_verificacao}");
        $pdf = Relatorio::pdf('pdf.licenca-ambiental', ['l' => $l, 'p' => $p, 'url' => $url,
            'atividade' => DB::table('amb_atividades')->find($l->atividade_id), 'modalidade' => self::MODALIDADES[$l->modalidade],
            'condicionantes' => DB::table('amb_condicionantes')->where('licenca_id', $l->id)->orderBy('id')->get(),
            'ug' => \App\Models\Ug::find($p->ug_id), 'emissor' => $u, 'portes' => self::PORTES, 'potenciais' => self::POTENCIAIS])->output();
        $path = \App\Tenant\TenantStorage::pasta('documentos') . '/' . date('Y/m') . '/' . uniqid() . '-licenca-' . str_replace('/', '-', $l->numero) . '.pdf';
        Storage::disk('documentos')->put($path, $pdf);
        $doc = Documento::create(['nome' => "Licença ambiental {$l->numero}", 'descricao' => self::MODALIDADES[$l->modalidade] . " — {$l->empreendedor_nome}",
            'tipo_documental_id' => TiposDocumentais::id('Licença Ambiental', 'Licença ambiental emitida pelo município'), 'pasta_id' => null, 'versao_atual' => 1,
            'tamanho' => strlen($pdf), 'mime_type' => 'application/pdf', 'autor_id' => $u->id, 'status' => 'rascunho',
            'ocr_texto' => implode(' | ', array_filter([$l->numero, self::MODALIDADES[$l->modalidade], $l->empreendedor_nome, $l->empreendimento_nome, $p->numero_protocolo]))]);
        Versao::create(['documento_id' => $doc->id, 'versao' => 1, 'arquivo_path' => $path, 'tamanho' => strlen($pdf), 'hash_sha256' => hash('sha256', $pdf),
            'autor_id' => $u->id, 'comentario' => "Licença ambiental {$l->numero} gerada no processo {$p->numero_protocolo}"]);

        return $doc;
    }

    private static function encerrarProcesso(Processo $p, User $u, string $decisao, string $texto): void
    {
        Tramitacao::where('processo_id', $p->id)->whereIn('status', ['pendente', 'recebido'])
            ->update(['status' => 'concluido', 'recebido_em' => DB::raw('COALESCE(recebido_em, NOW())')]);
        $p->update(['status' => 'concluido', 'concluido_por' => $u->id, 'concluido_em' => now(), 'decisao' => $decisao, 'observacao_conclusao' => $texto,
            'decisao_final' => $texto, 'decisao_final_em' => now()]);
        ProcessoHistorico::create(['processo_id' => $p->id, 'usuario_id' => $u->id, 'acao' => $decisao === 'deferido' ? 'licenca_emitida' : 'licenca_indeferida',
            'detalhes' => ['texto' => $texto], 'ip' => request()->ip(), 'user_agent' => request()->userAgent()]);
        Ouvidoria::avisarPortal($p, $texto, 'atendida');
        RespostaManifestante::enviarDecisao($p, $texto);
    }

    private static function avisarEmpreendedor(object $l, string $texto): void
    {
        $p = Processo::withoutGlobalScopes()->find($l->processo_id);
        if ($p) {
            Ouvidoria::avisarPortal($p, $texto, null);
        }
        if ($p && $l->empreendedor_email && filter_var($l->empreendedor_email, FILTER_VALIDATE_EMAIL)) {
            RastreioEmail::enviar(new DecisaoManifestacao($p, $texto), $l->empreendedor_email, 'cidadao', "Licença ambiental {$l->numero}", 'processo', $p->id, null);
        }
    }

    private static function avisarSetor(object $l, string $titulo, string $mensagem): void
    {
        $setor = DB::table('portal_servicos')->where('id', DB::table('amb_config')->value('servico_id'))->value('setor_responsavel_id');
        foreach ($setor ? Setores::servidores((int) $setor) : [] as $uid) {
            Notificacao::create(['usuario_id' => $uid, 'tipo' => 'processo', 'titulo' => $titulo, 'mensagem' => $mensagem,
                'referencia_tipo' => 'processo', 'referencia_id' => $l->processo_id, 'lida' => false]);
        }
    }
}
