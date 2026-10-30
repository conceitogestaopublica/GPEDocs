<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Plano de classificação (TR 4.27). A classificação de um item novo vem do assunto
 * (tipo de processo / tipo documental) e, se o assunto não tiver, do módulo (4.27.6–7).
 * Classificação suspensa não entra em item novo, mas fica nos que já tinham (4.27.4).
 * Toda troca vai para ged_classificacao_historico e, no processo, para a linha do tempo (4.27.8).
 */
final class Classificacao
{
    public const NIVEIS = 4;

    /** módulo => [tabela, coluna do assunto, tabela do assunto, rótulo] */
    public const MODULOS = [
        'processo'  => ['proc_processos', 'tipo_processo_id', 'proc_tipos_processo', 'Processos'],
        'memorando' => ['proc_memorandos', null, null, 'Memorandos'],
        'oficio'    => ['proc_oficios', null, null, 'Ofícios'],
        'circular'  => ['proc_circulares', null, null, 'Circulares'],
        'documento' => ['ged_documentos', 'tipo_documental_id', 'ged_tipos_documentais', 'Documentos'],
    ];

    /** Classificação de um item novo do módulo, pelo assunto e, na falta, pelo módulo. */
    public static function paraNovo(string $modulo, ?int $assuntoId): ?int
    {
        [, , $tabelaAssunto] = self::MODULOS[$modulo];
        if ($tabelaAssunto && $assuntoId) {
            $doAssunto = DB::table($tabelaAssunto)->where("{$tabelaAssunto}.id", $assuntoId)
                ->join('ged_classificacoes as c', 'c.id', '=', "{$tabelaAssunto}.classificacao_id")
                ->where('c.ativo', true)->value('c.id');
            if ($doAssunto) {
                return (int) $doAssunto;
            }
        }
        $doModulo = DB::table('ged_classificacao_modulos as m')->where('m.modulo', $modulo)
            ->join('ged_classificacoes as c', 'c.id', '=', 'm.classificacao_id')
            ->where('c.ativo', true)->value('c.id');

        return $doModulo ? (int) $doModulo : null;
    }

    /** Troca a classificação do item e registra. Devolve false se já era essa. */
    public static function reclassificar(string $modulo, int $itemId, ?int $novaId, ?string $motivo, bool $automatica = false): bool
    {
        [$tabela] = self::MODULOS[$modulo];
        $atual = DB::table($tabela)->where('id', $itemId)->value('classificacao_id');
        $atual = $atual !== null ? (int) $atual : null;
        if ($atual === $novaId) {
            return false;
        }

        DB::transaction(function () use ($modulo, $tabela, $itemId, $atual, $novaId, $motivo, $automatica) {
            DB::table($tabela)->where('id', $itemId)->update(['classificacao_id' => $novaId]);
            DB::table('ged_classificacao_historico')->insert([
                'tipo' => $modulo, 'item_id' => $itemId, 'de_id' => $atual, 'para_id' => $novaId,
                'motivo' => $motivo, 'automatica' => $automatica, 'usuario_id' => $automatica ? null : Auth::id(),
                'created_at' => now(),
            ]);
            if ($modulo === 'processo') {
                DB::table('proc_historico')->insert([
                    'processo_id' => $itemId,
                    'usuario_id'  => $automatica ? null : Auth::id(),
                    'acao'        => 'reclassificacao',
                    'detalhes'    => json_encode([
                        'de' => self::rotulo($atual), 'para' => self::rotulo($novaId),
                        'motivo' => $motivo, 'automatica' => $automatica,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]);
            }
        });

        return true;
    }

    /**
     * Classifica os itens antigos que ainda não têm classificação (TR 4.27.10).
     *
     * @return array<string,int> módulo => quantos foram classificados
     */
    public static function retroativa(?string $soModulo = null): array
    {
        $feitos = [];
        foreach (self::MODULOS as $modulo => [$tabela, $colAssunto]) {
            if ($soModulo && $soModulo !== $modulo) {
                continue;
            }
            $feitos[$modulo] = 0;
            DB::table($tabela)->whereNull('classificacao_id')->orderBy('id')
                ->select(array_filter(['id', $colAssunto]))
                ->chunkById(500, function ($itens) use ($modulo, $colAssunto, &$feitos) {
                    foreach ($itens as $item) {
                        $nova = self::paraNovo($modulo, $colAssunto ? ($item->{$colAssunto} ? (int) $item->{$colAssunto} : null) : null);
                        if ($nova && self::reclassificar($modulo, (int) $item->id, $nova, 'Classificação automática retroativa', true)) {
                            $feitos[$modulo]++;
                        }
                    }
                });
        }

        return $feitos;
    }

    /** @return array<string,int> módulo => itens sem classificação */
    public static function pendentes(): array
    {
        $r = [];
        foreach (self::MODULOS as $modulo => [$tabela]) {
            $q = DB::table($tabela)->whereNull('classificacao_id');
            if (\Illuminate\Support\Facades\Schema::hasColumn($tabela, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $r[$modulo] = $q->count();
        }

        return $r;
    }

    /** @return list<int> a classificação e todas as de baixo (para a busca, TR 4.27.9) */
    public static function comDescendentes(int $id): array
    {
        $ids = [$id];
        $fronteira = [$id];
        for ($n = 1; $n < self::NIVEIS && $fronteira; $n++) {
            $fronteira = DB::table('ged_classificacoes')->whereIn('parent_id', $fronteira)->pluck('id')->map(fn ($i) => (int) $i)->all();
            $ids = [...$ids, ...$fronteira];
        }

        return $ids;
    }

    /**
     * Lista em ordem de árvore, para os selects ("01.02 — Descrição", recuada pelo nível).
     *
     * @return list<array{id:int, codigo:string, descricao:string, nivel:int, ativo:bool, rotulo:string}>
     */
    public static function opcoes(bool $soAtivas = true): array
    {
        $todas = DB::table('ged_classificacoes')->orderBy('codigo')->get();
        $porPai = $todas->groupBy(fn ($c) => $c->parent_id ?? 0);
        $saida = [];
        $andar = function (int $pai) use (&$andar, &$saida, $porPai, $soAtivas) {
            foreach ($porPai->get($pai, collect()) as $c) {
                if ($soAtivas && ! $c->ativo) {
                    continue;
                }
                $saida[] = ['id' => (int) $c->id, 'codigo' => $c->codigo, 'descricao' => $c->descricao, 'nivel' => (int) $c->nivel,
                    'ativo' => (bool) $c->ativo, 'rotulo' => "{$c->codigo} — {$c->descricao}"];
                $andar((int) $c->id);
            }
        };
        $andar(0);

        return $saida;
    }

    /** Dados do bloco "Classificação" na tela do item (componente ClassificacaoDoItem). */
    public static function doItem(string $modulo, int $itemId): array
    {
        [$tabela] = self::MODULOS[$modulo];
        $atual = DB::table($tabela)->where('id', $itemId)->value('classificacao_id');
        $pode = (bool) Auth::user()?->can('admin.classificacao');

        return [
            'modulo'    => $modulo,
            'item_id'   => $itemId,
            'atual'     => self::rotulo($atual ? (int) $atual : null),
            'atual_id'  => $atual ? (int) $atual : null,
            'pode'      => $pode,
            'opcoes'    => $pode ? self::opcoes() : [],
            'historico' => DB::table('ged_classificacao_historico as h')
                ->leftJoin('users as u', 'u.id', '=', 'h.usuario_id')
                ->where('h.tipo', $modulo)->where('h.item_id', $itemId)->orderByDesc('h.id')
                ->get(['h.id', 'h.de_id', 'h.para_id', 'h.motivo', 'h.automatica', 'h.created_at', 'u.name as usuario'])
                ->map(fn ($h) => [
                    'id' => $h->id, 'de' => self::rotulo($h->de_id), 'para' => self::rotulo($h->para_id), 'motivo' => $h->motivo,
                    'automatica' => (bool) $h->automatica, 'usuario' => $h->usuario, 'em' => $h->created_at,
                ])->all(),
        ];
    }

    public static function rotulo(?int $id): ?string
    {
        if (! $id) {
            return null;
        }
        $c = DB::table('ged_classificacoes')->where('id', $id)->first(['codigo', 'descricao']);

        return $c ? "{$c->codigo} — {$c->descricao}" : null;
    }
}
