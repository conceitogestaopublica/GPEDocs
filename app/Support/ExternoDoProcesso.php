<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * O que o externo (requerente e participantes) vê do processo e o rastro do que ele viu e baixou
 * (TR 4.48.20, 4.48.28, 4.48.30): anexos marcados "sempre" ou "ao deferir" (estes só depois de
 * deferido ou deferido em parte), acesso pelo CPF/CNPJ do requerente ou de um participante.
 */
final class ExternoDoProcesso
{
    public const VISIBILIDADE = ['nao' => 'Só interno', 'sempre' => 'Externo vê', 'ao_deferir' => 'Externo vê após o deferimento'];

    public const PAPEIS = ['proprietario' => 'Proprietário', 'arquiteto' => 'Arquiteto(a)', 'engenheiro' => 'Engenheiro(a)',
        'responsavel_tecnico' => 'Responsável técnico', 'requerente' => 'Requerente', 'outro' => 'Outro'];

    public static function deferido(Processo $p): bool
    {
        return in_array($p->decisao, ['deferido', 'parcial'], true);
    }

    /** Processos em que o documento é requerente ou participante. */
    public static function doDocumento(Builder $q, string $documento): Builder
    {
        $doc = preg_replace('/\D/', '', $documento);

        return $q->where(fn ($w) => $w->whereRaw("regexp_replace(coalesce(requerente_cpf, ''), '\\D', '', 'g') = ?", [$doc])
            ->orWhereExists(fn ($e) => $e->from('proc_participantes as pp')->whereColumn('pp.processo_id', 'proc_processos.id')->where('pp.cpf_cnpj', $doc)));
    }

    /** @return \Illuminate\Support\Collection<int, ProcessoAnexo> */
    public static function anexosVisiveis(Processo $p)
    {
        $deferido = self::deferido($p);

        return ProcessoAnexo::where('processo_id', $p->id)
            ->where(fn ($q) => $q->where('externo', 'sempre')->when($deferido, fn ($w) => $w->orWhere('externo', 'ao_deferir')))
            ->orderBy('id')->get(['id', 'nome', 'tamanho', 'mime_type', 'created_at', 'externo']);
    }

    public static function registrar(Processo $p, string $acao, ?int $anexoId, ?string $quem, ?string $documento): void
    {
        DB::table('proc_acessos_externos')->insert(['processo_id' => $p->id, 'anexo_id' => $anexoId, 'acao' => $acao, 'quem' => $quem ? mb_substr($quem, 0, 200) : null,
            'documento' => $documento ? preg_replace('/\D/', '', $documento) : null, 'ip' => request()?->ip(), 'created_at' => now()]);
    }

    /** Para o card no GED: e-mails ao externo (recebeu, abriu) e o que ele viu/baixou no portal. */
    public static function painel(Processo $p): array
    {
        return [
            'emails' => DB::table('ged_emails_rastreados')->where('referencia_tipo', 'processo')->where('referencia_id', $p->id)->orderByDesc('id')->limit(20)
                ->get(['destinatario', 'assunto', 'status', 'enviado_em', 'entregue_em', 'aberto_em', 'clicado_em']),
            'acessos' => DB::table('proc_acessos_externos as a')->leftJoin('proc_anexos as x', 'x.id', '=', 'a.anexo_id')->where('a.processo_id', $p->id)
                ->orderByDesc('a.id')->limit(30)->get(['a.acao', 'a.quem', 'a.created_at', 'x.nome as anexo']),
        ];
    }
}