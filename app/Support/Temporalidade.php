<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Tabela de temporalidade e destinação (CONARQ) e eliminação de documentos (Resolução CONARQ nº 40/2014).
 *
 * Cada código do plano de classificação diz quanto tempo o item fica na fase corrente e na intermediária
 * (em anos ou por condição), a partir de que evento (produção ou conclusão) e o destino final. Daí:
 *   em_guarda       ainda na fase corrente
 *   transferencia   cumpriu a fase corrente: vai ao arquivo intermediário
 *   recolhimento    cumpriu a guarda e o destino é a guarda permanente
 *   eliminacao      cumpriu a guarda e o destino é a eliminação
 *   condicional     prazo por condição (texto): a avaliação é do arquivista
 *
 * Eliminação: listagem → aprovação → edital de ciência (prazo para manifestações) → eliminação com termo.
 * Na eliminação os arquivos são apagados (só os que nenhum item que fica também usa) e o item mantém
 * metadados, classificação e histórico, marcado como eliminado.
 */
final class Temporalidade
{
    public const SITUACOES = ['em_guarda' => 'Em guarda (fase corrente)', 'transferencia' => 'Para transferência ao arquivo intermediário',
        'recolhimento' => 'Para recolhimento à guarda permanente', 'eliminacao' => 'Para eliminação', 'condicional' => 'Prazo condicional (avaliar)'];

    public const STATUS = ['rascunho' => 'Em elaboração', 'aprovada' => 'Aprovada', 'edital' => 'Edital publicado', 'eliminada' => 'Eliminada', 'cancelada' => 'Cancelada'];

    /** módulo => [tabela, coluna de identificação, coluna de assunto, coluna da data de conclusão] */
    private const ITENS = [
        'processo'  => ['proc_processos', 'numero_protocolo', 'assunto', 'concluido_em'],
        'memorando' => ['proc_memorandos', 'numero', 'assunto', null],
        'oficio'    => ['proc_oficios', 'numero', 'assunto', null],
        'circular'  => ['proc_circulares', 'numero', 'assunto', null],
        'documento' => ['ged_documentos', 'nome', 'descricao', null],
    ];

    public const ROTULOS = ['processo' => 'Processo', 'memorando' => 'Memorando', 'oficio' => 'Ofício', 'circular' => 'Circular', 'documento' => 'Documento'];

    /** Itens classificados em código com temporalidade, com a situação de cada um. */
    public static function avaliar(?int $ugId, ?string $situacao = null, int $limite = 1000): Collection
    {
        $codigos = DB::table('ged_classificacoes')->where(fn ($w) => $w->whereNotNull('ttd_destinacao')
            ->orWhereNotNull('ttd_corrente_anos')->orWhereNotNull('ttd_corrente_texto'))->get()->keyBy('id');
        if ($codigos->isEmpty()) {
            return collect();
        }
        $emListagem = DB::table('ged_eliminacao_itens as i')->join('ged_eliminacoes as e', 'e.id', '=', 'i.eliminacao_id')
            ->whereIn('e.status', ['rascunho', 'aprovada', 'edital'])->get(['i.tipo', 'i.item_id', 'e.numero'])
            ->mapWithKeys(fn ($r) => ["{$r->tipo}-{$r->item_id}" => $r->numero]);
        $hoje = now('America/Sao_Paulo')->startOfDay();
        $saida = collect();
        foreach (self::ITENS as $tipo => [$tabela, $ident, $assunto, $conclusao]) {
            $q = DB::table($tabela)->whereIn('classificacao_id', $codigos->keys())->whereNull('eliminado_em')
                ->when($ugId, fn ($w) => $w->where('ug_id', $ugId))
                ->select(['id', 'classificacao_id', "{$ident} as identificacao", "{$assunto} as assunto", 'created_at'])
                ->when($conclusao, fn ($w) => $w->addSelect("{$conclusao} as concluido_em"))
                ->when($tipo === 'processo', fn ($w) => $w->addSelect('status'))
                ->when($tipo === 'documento', fn ($w) => $w->whereNull('deleted_at'));
            foreach ($q->orderBy('id')->limit($limite)->get() as $r) {
                $c = $codigos[$r->classificacao_id];
                $item = self::situacao($tipo, $r, $c, $hoje);
                if (! $item || ($situacao && $item['situacao'] !== $situacao)) {
                    continue;
                }
                $saida->push($item + ['listagem' => $emListagem["{$tipo}-{$r->id}"] ?? null]);
            }
        }

        return $saida->sortBy('cumpre_em')->values();
    }

    /** Contagem por situação, para o painel. */
    public static function resumo(?int $ugId): array
    {
        $todos = self::avaliar($ugId, null, 20000);

        return collect(self::SITUACOES)->map(fn ($r, $k) => ['rotulo' => $r, 'total' => $todos->where('situacao', $k)->count()])->all();
    }

    private static function situacao(string $tipo, object $r, object $c, Carbon $hoje): ?array
    {
        // Evento de contagem: conclusão (processo concluído; os demais itens contam da produção) ou produção
        $base = $c->ttd_evento === 'conclusao' && $tipo === 'processo' ? ($r->concluido_em ?? null) : $r->created_at;
        $aberto = $tipo === 'processo' && ! in_array($r->status ?? '', ['concluido', 'cancelado'], true);
        $comum = ['tipo' => $tipo, 'tipo_rotulo' => self::ROTULOS[$tipo], 'id' => (int) $r->id, 'identificacao' => $r->identificacao,
            'assunto' => $r->assunto ? mb_substr(strip_tags((string) $r->assunto), 0, 200) : null, 'codigo' => $c->codigo,
            'classificacao' => $c->descricao, 'classificacao_id' => (int) $c->id, 'destinacao' => $c->ttd_destinacao,
            'data_base' => $base ? Carbon::parse($base)->toDateString() : null];
        if (! $base || $aberto) {
            // processo ainda em andamento (ou sem data de conclusão) segue na fase corrente
            return $comum + ['situacao' => 'em_guarda', 'cumpre_em' => null, 'corrente_ate' => null];
        }
        if ($c->ttd_corrente_anos === null || ($c->ttd_intermediaria_anos === null && $c->ttd_intermediaria_texto)) {
            return $comum + ['situacao' => 'condicional', 'cumpre_em' => null, 'corrente_ate' => null,
                'prazo' => trim(($c->ttd_corrente_texto ?: '') . ' / ' . ($c->ttd_intermediaria_texto ?: ''), ' /')];
        }
        $inicio = Carbon::parse($base)->startOfDay();
        $correnteAte = $inicio->copy()->addYears((int) $c->ttd_corrente_anos);
        $guardaAte = $correnteAte->copy()->addYears((int) ($c->ttd_intermediaria_anos ?? 0));
        $situacao = match (true) {
            $hoje->lt($correnteAte) => 'em_guarda',
            $hoje->lt($guardaAte) => 'transferencia',
            $c->ttd_destinacao === 'eliminacao' => 'eliminacao',
            $c->ttd_destinacao === 'guarda_permanente' => 'recolhimento',
            default => 'transferencia',
        };

        return $comum + ['situacao' => $situacao, 'corrente_ate' => $correnteAte->toDateString(), 'cumpre_em' => $guardaAte->toDateString()];
    }

    // ---------------------------------------------------------------- listagem de eliminação

    /** @param  list<array{tipo:string,id:int}>  $itens */
    public static function criarListagem(?int $ugId, User $u, array $itens, ?string $observacao): int
    {
        $elegiveis = self::avaliar($ugId, null, 20000)->filter(fn ($i) => in_array($i['situacao'], ['eliminacao', 'condicional'], true) && ! $i['listagem'])
            ->keyBy(fn ($i) => "{$i['tipo']}-{$i['id']}");
        $escolhidos = collect($itens)->map(fn ($i) => $elegiveis["{$i['tipo']}-{$i['id']}"] ?? null)->filter();
        if ($escolhidos->isEmpty()) {
            throw new RuntimeException('Nenhum dos itens escolhidos pode ser listado para eliminação (prazo não cumprido, destino diferente ou já em outra listagem).');
        }

        return DB::transaction(function () use ($ugId, $u, $escolhidos, $observacao) {
            $ano = now('America/Sao_Paulo')->year;
            DB::select('select pg_advisory_xact_lock(?)', [crc32("eliminacao-{$ano}")]);
            $ultimo = (int) DB::table('ged_eliminacoes')->where('numero', 'like', "LE-{$ano}/%")->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));
            $id = DB::table('ged_eliminacoes')->insertGetId(['ug_id' => $ugId, 'numero' => "LE-{$ano}/" . str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT),
                'status' => 'rascunho', 'observacao' => $observacao, 'criada_por' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($escolhidos as $i) {
                DB::table('ged_eliminacao_itens')->insert(['eliminacao_id' => $id, 'tipo' => $i['tipo'], 'item_id' => $i['id'], 'classificacao_id' => $i['classificacao_id'],
                    'identificacao' => mb_substr((string) $i['identificacao'], 0, 255), 'assunto' => $i['assunto'], 'data_inicial' => $i['data_base'],
                    'data_final' => $i['cumpre_em'] ?? $i['data_base'], 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
    }

    public static function aprovar(object $l, User $u): void
    {
        self::exigirStatus($l, 'rascunho');
        DB::table('ged_eliminacoes')->where('id', $l->id)->update(['status' => 'aprovada', 'aprovada_por' => $u->id, 'aprovada_em' => now(), 'updated_at' => now()]);
    }

    public static function publicarEdital(object $l, int $prazoDias): void
    {
        self::exigirStatus($l, 'aprovada');
        DB::table('ged_eliminacoes')->where('id', $l->id)->update(['status' => 'edital', 'edital_em' => now(), 'edital_prazo_dias' => $prazoDias, 'updated_at' => now()]);
    }

    /** Data a partir da qual a eliminação pode ser feita (fim do prazo do edital). */
    public static function liberadaEm(object $l): ?Carbon
    {
        return $l->edital_em ? Carbon::parse($l->edital_em)->addDays((int) $l->edital_prazo_dias) : null;
    }

    /** Elimina: apaga os arquivos dos itens (os que nenhum item que fica usa) e marca os itens. */
    public static function eliminar(object $l, User $u): int
    {
        self::exigirStatus($l, 'edital');
        if (now()->lt(self::liberadaEm($l))) {
            throw new RuntimeException('O prazo do edital de ciência ainda não terminou: a eliminação fica liberada em ' . self::liberadaEm($l)->setTimezone('America/Sao_Paulo')->format('d/m/Y') . '.');
        }
        $itens = DB::table('ged_eliminacao_itens')->where('eliminacao_id', $l->id)->get();
        $saem = $itens->groupBy('tipo')->map(fn ($g) => $g->pluck('item_id')->map(fn ($v) => (int) $v)->all())->all();
        // documentos que pertencem aos itens (decisão do processo, PDF do memorando…) também saem
        $docs = array_merge($saem['documento'] ?? [], self::documentosDosItens($saem));
        $saem['documento'] = array_values(array_unique($docs));
        $arquivos = self::arquivosDosItens($saem);

        $apagados = 0;
        DB::transaction(function () use ($l, $u, $saem, $itens) {
            foreach ($saem as $tipo => $ids) {
                $tabela = self::ITENS[$tipo][0];
                DB::table($tabela)->whereIn('id', $ids)->update(['eliminado_em' => now(), 'eliminacao_id' => $l->id]);
                if ($tipo === 'processo') {
                    foreach ($ids as $pid) {
                        DB::table('proc_historico')->insert(['processo_id' => $pid, 'usuario_id' => $u->id, 'acao' => 'eliminacao',
                            'detalhes' => json_encode(['listagem' => $l->numero], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
                    }
                }
            }
            // o documento eliminado sai do repositório e da lixeira (não volta)
            DB::table('ged_documentos')->whereIn('id', $saem['documento'])->whereNull('deleted_at')->update(['deleted_at' => now()]);
            DB::table('ged_eliminacoes')->where('id', $l->id)->update(['status' => 'eliminada', 'eliminada_por' => $u->id, 'eliminada_em' => now(), 'updated_at' => now()]);
        });
        foreach ($arquivos as $path) {
            if (! self::usadoPorQuemFica($path) && Storage::disk('documentos')->exists($path)) {
                Storage::disk('documentos')->delete($path);
                $apagados++;
            }
        }
        DB::table('ged_eliminacoes')->where('id', $l->id)->update(['arquivos_apagados' => $apagados]);

        return $apagados;
    }

    public static function cancelar(object $l, User $u, string $motivo): void
    {
        if (in_array($l->status, ['eliminada', 'cancelada'], true)) {
            throw new RuntimeException('Esta listagem já está encerrada.');
        }
        DB::table('ged_eliminacoes')->where('id', $l->id)->update(['status' => 'cancelada', 'cancelada_por' => $u->id, 'motivo_cancelamento' => $motivo, 'updated_at' => now()]);
    }

    private static function exigirStatus(object $l, string $status): void
    {
        if ($l->status !== $status) {
            throw new RuntimeException('A listagem está "' . (self::STATUS[$l->status] ?? $l->status) . '": esta etapa não se aplica agora.');
        }
    }

    // ---------------------------------------------------------------- arquivos

    /** @param  array<string, list<int>>  $saem */
    private static function documentosDosItens(array $saem): array
    {
        $ids = [];
        foreach (['processo' => ['proc_processos', 'documento_decisao_id'], 'memorando' => ['proc_memorandos', 'documento_id'],
            'oficio' => ['proc_oficios', 'documento_id'], 'circular' => ['proc_circulares', 'documento_id']] as $tipo => [$tabela, $col]) {
            if (! empty($saem[$tipo])) {
                $ids = array_merge($ids, DB::table($tabela)->whereIn('id', $saem[$tipo])->whereNotNull($col)->pluck($col)->map(fn ($v) => (int) $v)->all());
            }
        }

        return $ids;
    }

    /** Onde ficam os arquivos de cada tipo de item: [tabela, coluna do arquivo, coluna do dono, tipo do dono] */
    private const ARQUIVOS = [
        ['ged_versoes', 'arquivo_path', 'documento_id', 'documento'],
        ['ged_assinaturas', 'arquivo_assinado_path', 'documento_id', 'documento'],
        ['ged_assinaturas', 'arquivo_impressao_path', 'documento_id', 'documento'],
        ['proc_anexos', 'arquivo_path', 'processo_id', 'processo'],
        ['proc_arvores', 'arquivo_path', 'processo_id', 'processo'],
        ['proc_memorando_anexos', 'arquivo_path', 'memorando_id', 'memorando'],
        ['proc_oficio_anexos', 'arquivo_path', 'oficio_id', 'oficio'],
        ['proc_circular_anexos', 'arquivo_path', 'circular_id', 'circular'],
    ];

    private static function arquivosDosItens(array $saem): array
    {
        $paths = [];
        foreach (self::ARQUIVOS as [$tabela, $col, $dono, $tipo]) {
            if (! empty($saem[$tipo])) {
                $paths = array_merge($paths, DB::table($tabela)->whereIn($dono, $saem[$tipo])->whereNotNull($col)->pluck($col)->all());
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /** O arquivo também é usado por item que NÃO foi eliminado (ex.: peça anexada a outro processo)? */
    private static function usadoPorQuemFica(string $path): bool
    {
        foreach (self::ARQUIVOS as [$tabela, $col, $dono, $tipo]) {
            $donos = DB::table($tabela)->where($col, $path)->pluck($dono)->filter()->unique()->all();
            if ($donos && DB::table(self::ITENS[$tipo][0])->whereIn('id', $donos)->whereNull('eliminado_em')->exists()) {
                return true;
            }
        }

        return false;
    }
}
