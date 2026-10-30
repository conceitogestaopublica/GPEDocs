<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas dos relatórios da trilha (TR 4.25.4, 4.25.12–14, 4.25.16): eventos (a trilha nova mais
 * os eventos de documento que já existiam em ged_audit_logs) e acessos diários por usuário. As
 * mesmas consultas servem à tela, ao CSV na hora e ao CSV gerado em segundo plano.
 */
class RelatorioEventos
{
    public const FUSO = 'America/Sao_Paulo';

    public const TIPOS = ['eventos' => 'Relatório de eventos', 'acessos' => 'Acessos diários'];

    private const ACOES_DOC = ['visualizacao' => 'Visualizou', 'download' => 'Baixou', 'captura' => 'Capturou', 'upload' => 'Enviou',
        'solicitacao_assinatura' => 'Pediu assinatura de', 'assinatura_qualificada_icp' => 'Assinou (ICP-Brasil)', 'assinatura_lote' => 'Assinou em lote',
        'exclusao' => 'Excluiu', 'restauracao' => 'Restaurou', 'edicao' => 'Editou', 'nova_versao' => 'Nova versão de'];

    /** Filtros aceitos, já normalizados. Período padrão: últimos 30 dias. */
    public static function filtros(array $in): array
    {
        $ate = ! empty($in['ate']) ? Carbon::parse($in['ate'], self::FUSO) : now(self::FUSO);
        $de = ! empty($in['de']) ? Carbon::parse($in['de'], self::FUSO) : $ate->copy()->subDays(29);

        return array_filter([
            'de' => $de->toDateString(), 'ate' => $ate->toDateString(),
            'categoria' => $in['categoria'] ?? null, 'codigo' => $in['codigo'] ?? null, 'busca' => trim((string) ($in['busca'] ?? '')) ?: null,
            'unidade_id' => ! empty($in['unidade_id']) ? (int) $in['unidade_id'] : null, 'usuario_id' => ! empty($in['usuario_id']) ? (int) $in['usuario_id'] : null,
            'sucesso' => isset($in['sucesso']) && $in['sucesso'] !== '' ? (string) $in['sucesso'] : null,
        ], fn ($v) => $v !== null);
    }

    /** Limites do período em UTC (as colunas são gravadas em UTC). */
    private static function periodo(array $f): array
    {
        return [Carbon::parse($f['de'], self::FUSO)->startOfDay()->utc(), Carbon::parse($f['ate'], self::FUSO)->endOfDay()->utc()];
    }

    public static function eventos(array $f)
    {
        [$de, $ate] = self::periodo($f);
        $trilha = DB::table('ged_eventos as e')->whereBetween('e.created_at', [$de, $ate])
            ->select(['e.id', DB::raw("'E' as origem"), 'e.codigo', 'e.categoria', 'e.descricao', 'e.usuario_id', 'e.unidade_id', 'e.login', 'e.sucesso',
                'e.ip', 'e.so', 'e.dispositivo', 'e.navegador', 'e.user_agent', 'e.created_at']);
        $documentos = DB::table('ged_audit_logs as a')->leftJoin('users as au', 'au.id', '=', 'a.usuario_id')->leftJoin('ged_documentos as d', 'd.id', '=', 'a.documento_id')
            ->whereBetween('a.created_at', [$de, $ate])
            ->select(['a.id', DB::raw("'A' as origem"), DB::raw("'DOC.' || upper(a.acao) as codigo"), DB::raw("'documento' as categoria"),
                DB::raw("a.acao || coalesce(' — ' || d.nome, '') as descricao"), 'a.usuario_id', 'au.unidade_id', DB::raw('null as login'), DB::raw('true as sucesso'),
                'a.ip', DB::raw('null as so'), DB::raw('null as dispositivo'), DB::raw('null as navegador'), 'a.user_agent', 'a.created_at']);

        $q = DB::query()->fromSub($trilha->unionAll($documentos), 'x')
            ->leftJoin('users as u', 'u.id', '=', 'x.usuario_id')->leftJoin('ug_organograma as s', 's.id', '=', 'x.unidade_id')
            ->select(['x.*', 'u.name as usuario', 's.nome as setor', 's.sigla as setor_sigla']);
        if (! empty($f['categoria'])) {
            $q->where('x.categoria', $f['categoria']);
        }
        if (! empty($f['codigo'])) {
            $q->where('x.codigo', 'ilike', $f['codigo'] . '%');
        }
        if (! empty($f['unidade_id'])) {
            $q->whereIn('x.unidade_id', Organograma::comDescendentes($f['unidade_id']));
        }
        if (! empty($f['usuario_id'])) {
            $q->where('x.usuario_id', $f['usuario_id']);
        }
        if (isset($f['sucesso'])) {
            $q->where('x.sucesso', $f['sucesso'] === '1');
        }
        if (! empty($f['busca'])) {
            $b = '%' . $f['busca'] . '%';
            $q->where(fn ($w) => $w->where('x.descricao', 'ilike', $b)->orWhere('x.login', 'ilike', $b)->orWhere('x.ip', 'ilike', $b)->orWhere('u.name', 'ilike', $b));
        }

        return $q->orderByDesc('x.created_at')->orderByDesc('x.id');
    }

    /** Linha pronta para tela/CSV: data local, SO/dispositivo/navegador e descrição dos eventos de documento. */
    public static function linhaEvento(object $r): array
    {
        if (! $r->so && $r->user_agent) {
            [$r->so, $r->dispositivo, $r->navegador] = AgenteUsuario::analisar($r->user_agent);
        }
        $descricao = $r->descricao;
        if ($r->origem === 'A') {
            [$acao, $doc] = array_pad(explode(' — ', (string) $r->descricao, 2), 2, null);
            $descricao = (self::ACOES_DOC[$acao] ?? ucfirst(str_replace('_', ' ', $acao))) . ($doc ? " documento \"{$doc}\"" : ' documento');
        }
        $quando = Carbon::parse($r->created_at, 'UTC')->setTimezone(self::FUSO);

        return ['id' => $r->origem . $r->id, 'codigo' => $r->codigo, 'categoria' => Eventos::CATEGORIAS[$r->categoria] ?? $r->categoria,
            'data' => $quando->format('d/m/Y'), 'hora' => $quando->format('H:i:s'), 'quando' => $quando->toIso8601String(),
            'usuario' => $r->usuario ?: ($r->login ? "({$r->login})" : '—'), 'setor' => $r->setor_sigla ?: $r->setor,
            'descricao' => $descricao, 'sucesso' => (bool) $r->sucesso, 'ip' => $r->ip, 'so' => $r->so, 'dispositivo' => $r->dispositivo, 'navegador' => $r->navegador];
    }

    /** Acessos por usuário e dia (TR 4.25.4). */
    public static function acessos(array $f)
    {
        [$de, $ate] = self::periodo($f);
        $dia = "(e.created_at AT TIME ZONE 'UTC' AT TIME ZONE '" . self::FUSO . "')";
        $q = DB::table('ged_eventos as e')->join('users as u', 'u.id', '=', 'e.usuario_id')->leftJoin('ug_organograma as s', 's.id', '=', 'e.unidade_id')
            ->where('e.codigo', 'ACE.ENTRADA')->whereBetween('e.created_at', [$de, $ate])
            ->groupBy(DB::raw("{$dia}::date"), 'e.usuario_id', 'u.name', 'u.email', 's.nome', 's.sigla')
            ->select([DB::raw("{$dia}::date as dia"), 'e.usuario_id', 'u.name as usuario', 'u.email', 's.nome as setor', 's.sigla as setor_sigla',
                DB::raw('count(*) as entradas'), DB::raw("min({$dia}) as primeira"), DB::raw("max({$dia}) as ultima"),
                DB::raw('count(distinct e.ip) as ips'), DB::raw("string_agg(distinct coalesce(e.dispositivo, '?'), ', ') as dispositivos")]);
        if (! empty($f['unidade_id'])) {
            $q->whereIn('e.unidade_id', Organograma::comDescendentes($f['unidade_id']));
        }
        if (! empty($f['usuario_id'])) {
            $q->where('e.usuario_id', $f['usuario_id']);
        }
        if (! empty($f['busca'])) {
            $q->where(fn ($w) => $w->where('u.name', 'ilike', '%' . $f['busca'] . '%')->orWhere('u.email', 'ilike', '%' . $f['busca'] . '%'));
        }

        return $q->orderByDesc('dia')->orderBy('u.name');
    }

    public static function linhaAcesso(object $r): array
    {
        return ['id' => $r->dia . '-' . $r->usuario_id, 'dia' => Carbon::parse($r->dia)->format('d/m/Y'), 'usuario' => $r->usuario, 'email' => $r->email,
            'setor' => $r->setor_sigla ?: $r->setor, 'entradas' => (int) $r->entradas,
            'primeira' => Carbon::parse($r->primeira)->format('H:i'), 'ultima' => Carbon::parse($r->ultima)->format('H:i'),
            'ips' => (int) $r->ips, 'dispositivos' => $r->dispositivos];
    }

    public static function contar(string $tipo, array $f): int
    {
        return $tipo === 'acessos'
            ? DB::query()->fromSub(self::acessos($f)->reorder(), 'c')->count()
            : DB::query()->fromSub(self::eventos($f)->reorder(), 'c')->count();
    }

    /** Escreve o CSV (separador ;, UTF-8 com BOM para abrir certo no Excel). Devolve o nº de linhas. */
    public static function csv(string $tipo, array $f, $saida): int
    {
        fwrite($saida, "\xEF\xBB\xBF");
        $n = 0;
        if ($tipo === 'acessos') {
            fputcsv($saida, ['Data', 'Usuário', 'E-mail', 'Setor', 'Entradas', 'Primeira', 'Última', 'IPs distintos', 'Dispositivos'], ';');
            foreach (self::acessos($f)->cursor() as $r) {
                fputcsv($saida, array_values(array_diff_key(self::linhaAcesso($r), ['id' => 1])), ';');
                $n++;
            }

            return $n;
        }
        fputcsv($saida, ['Código', 'Categoria', 'Data', 'Hora', 'Usuário', 'Setor', 'Descrição', 'Situação', 'IP', 'Sistema operacional', 'Dispositivo', 'Navegador'], ';');
        foreach (self::eventos($f)->cursor() as $r) {
            $l = self::linhaEvento($r);
            fputcsv($saida, [$l['codigo'], $l['categoria'], $l['data'], $l['hora'], $l['usuario'], $l['setor'], $l['descricao'],
                $l['sucesso'] ? 'Concluído' : 'Não concluído', $l['ip'], $l['so'], $l['dispositivo'], $l['navegador']], ';');
            $n++;
        }

        return $n;
    }

    /** Texto curto dos filtros, para a lista de relatórios gerados e o e-mail. */
    public static function resumoFiltros(array $f): string
    {
        $p = [isset($f['de'], $f['ate']) ? Carbon::parse($f['de'])->format('d/m/Y') . ' a ' . Carbon::parse($f['ate'])->format('d/m/Y') : null];
        if (! empty($f['categoria'])) {
            $p[] = Eventos::CATEGORIAS[$f['categoria']] ?? $f['categoria'];
        }
        if (! empty($f['codigo'])) {
            $p[] = "código {$f['codigo']}";
        }
        if (! empty($f['unidade_id'])) {
            $p[] = 'setor ' . (DB::table('ug_organograma')->where('id', $f['unidade_id'])->value('sigla') ?: DB::table('ug_organograma')->where('id', $f['unidade_id'])->value('nome'));
        }
        if (! empty($f['usuario_id'])) {
            $p[] = DB::table('users')->where('id', $f['usuario_id'])->value('name');
        }
        if (! empty($f['busca'])) {
            $p[] = "\"{$f['busca']}\"";
        }
        if (isset($f['sucesso'])) {
            $p[] = $f['sucesso'] === '1' ? 'concluídos' : 'não concluídos';
        }

        return implode(' · ', array_filter($p));
    }
}
