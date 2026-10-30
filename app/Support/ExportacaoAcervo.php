<?php

declare(strict_types=1);

namespace App\Support;

use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Exportação completa do município (TR 4.26.14–16), para o fim do contrato ou a qualquer momento:
 *   LEIA-ME.html                     — o que há em cada pasta e como validar as assinaturas
 *   dicionario/dicionario-de-dados.* — tabelas, colunas, tipos, chaves e descrições
 *   dados/<tabela>.csv               — todas as tabelas (ids preservados: os vínculos continuam valendo)
 *   arquivos/...                     — os arquivos originais (versões, anexos, PDFs assinados), sem conversão
 *   arquivos/indice.csv              — que registro (tabela, id, coluna) aponta para cada arquivo
 *   assinaturas/evidencias.csv       — quem assinou o quê, quando, de onde, com o hash do documento
 *   manifesto.json                   — SHA-256 de cada item do pacote
 * Segredos (senhas, segredos de 2FA, tokens) não saem; o resto sai como está no banco.
 */
final class ExportacaoAcervo
{
    /** Colunas que nunca saem do sistema. */
    private const SEGREDOS = ['password', 'senha', 'remember_token', 'dois_fatores_segredo', 'dois_fatores_recuperacao', 'api_token_hash', 'certificado_senha',
        'convite_token', 'convite_token_hash', 'token_hash', 'codigo_hash', 'token_verificacao', 'email_novo_token', 'senha_pfx', 'pfx_senha', 'assinatura_pkcs7'];

    /** Tabelas de infraestrutura que não são dados do município. */
    private const FORA = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'tramita_simulador'];

    /** Descrição das principais tabelas no dicionário. */
    private const DESCRICOES = [
        'users' => 'Servidores e usuários do sistema', 'ug_organograma' => 'Organograma: órgãos, unidades e setores', 'ugs' => 'Unidades gestoras',
        'ged_documentos' => 'Documentos do GED', 'ged_versoes' => 'Versões dos documentos (arquivo e hash de cada versão)', 'ged_pastas' => 'Pastas do repositório',
        'ged_assinaturas' => 'Assinaturas (signatário, data, IP, hash, tipo)', 'ged_solicitacoes_assinatura' => 'Pedidos de assinatura',
        'ged_audit_logs' => 'Auditoria dos documentos', 'ged_eventos' => 'Trilha de eventos (acessos, cadastros, configurações)',
        'proc_processos' => 'Processos e protocolos', 'proc_tramitacoes' => 'Movimentações (tramitação) dos processos', 'proc_anexos' => 'Anexos dos processos',
        'proc_historico' => 'Histórico dos processos', 'proc_tipos_processo' => 'Assuntos (tipos de processo)', 'proc_memorandos' => 'Memorandos',
        'proc_circulares' => 'Circulares', 'proc_oficios' => 'Ofícios', 'proc_pareceres' => 'Pareceres', 'proc_atos' => 'Atos oficiais',
        'dof_edicoes' => 'Edições do Diário Oficial', 'portal_solicitacoes' => 'Solicitações do portal do cidadão', 'portal_cidadaos' => 'Cidadãos cadastrados no portal',
        'contatos' => 'Contatos (pessoas e empresas)', 'tramita_tramites' => 'Trâmites com outros entes (Tramita GOV.BR)', 'sup_atendimentos' => 'Atendimentos de suporte',
    ];

    public static function gerar(int $exportacaoId): void
    {
        $tmp = sys_get_temp_dir() . '/ged-export-' . $exportacaoId . '-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0700, true);
        DB::table('port_exportacoes')->where('id', $exportacaoId)->update(['status' => 'gerando', 'updated_at' => now()]);
        try {
            $zipPath = "{$tmp}/export.zip";
            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $manifesto = [];
            $add = function (string $origem, string $destino) use ($zip, &$manifesto) {
                $zip->addFile($origem, $destino);
                $manifesto[] = ['caminho' => $destino, 'sha256' => hash_file('sha256', $origem), 'tamanho' => filesize($origem)];
            };
            $texto = function (string $conteudo, string $destino) use ($tmp, $add) {
                $f = $tmp . '/' . md5($destino);
                file_put_contents($f, $conteudo);
                $add($f, $destino);
            };

            // dados
            $tabelas = self::tabelas();
            foreach ($tabelas as $t) {
                $f = "{$tmp}/{$t}.csv";
                self::csvTabela($t, $f);
                $add($f, "dados/{$t}.csv");
            }
            // dicionário
            [$csv, $html] = self::dicionario($tabelas);
            $texto($csv, 'dicionario/dicionario-de-dados.csv');
            $texto($html, 'dicionario/dicionario-de-dados.html');
            // arquivos + índice de vínculos
            $docs = Storage::disk('documentos');
            $prefixo = TenantStorage::prefix();
            $locais = [];
            $n = 0;
            foreach ($docs->allFiles($prefixo) as $arq) {
                $add(Backup::paraLocal($docs, $arq, $tmp, $locais), 'arquivos/' . ltrim(substr($arq, strlen($prefixo)), '/'));
                $n++;
            }
            $texto(self::indiceArquivos($prefixo), 'arquivos/indice.csv');
            $texto(self::evidencias(), 'assinaturas/evidencias.csv');
            $texto(self::leiame(count($tabelas), $n), 'LEIA-ME.html');
            $zip->addFromString('manifesto.json', json_encode(['gerado_em' => now()->toIso8601String(), 'municipio' => DB::connection()->getDatabaseName(),
                'itens' => $manifesto], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->close();
            foreach ($locais as $l) {
                @unlink($l);
            }
            $nome = DB::connection()->getDatabaseName() . '/exportacao-' . now('America/Sao_Paulo')->format('Y-m-d_His') . "-{$exportacaoId}.zip";
            $h = fopen($zipPath, 'rb');
            Storage::disk('backup')->writeStream($nome, $h);
            fclose($h);
            DB::table('port_exportacoes')->where('id', $exportacaoId)->update(['status' => 'pronto', 'arquivo' => $nome, 'tamanho' => filesize($zipPath),
                'tabelas' => count($tabelas), 'arquivos' => $n, 'sha256' => hash_file('sha256', $zipPath), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('port_exportacoes')->where('id', $exportacaoId)->update(['status' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now()]);
            throw $e;
        } finally {
            Backup::limpar($tmp);
        }
    }

    public static function tabelas(): array
    {
        return DB::table('information_schema.tables')->where('table_schema', 'public')->where('table_type', 'BASE TABLE')
            ->whereNotIn('table_name', self::FORA)->orderBy('table_name')->pluck('table_name')->all();
    }

    private static function colunas(string $tabela): array
    {
        return DB::table('information_schema.columns')->where('table_schema', 'public')->where('table_name', $tabela)->orderBy('ordinal_position')
            ->pluck('column_name')->reject(fn ($c) => in_array($c, self::SEGREDOS, true))->values()->all();
    }

    private static function csvTabela(string $tabela, string $arquivo): void
    {
        $cols = self::colunas($tabela);
        $out = fopen($arquivo, 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $cols, ';');
        $q = DB::table($tabela)->select(array_map(fn ($c) => DB::raw('"' . str_replace('"', '""', $c) . '"'), $cols));
        $ordem = in_array('id', $cols, true) ? 'id' : $cols[0];
        foreach ($q->orderBy($ordem)->cursor() as $l) {
            fputcsv($out, array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, array_values((array) $l)), ';');
        }
        fclose($out);
    }

    /** @return array{0: string, 1: string} csv e html */
    public static function dicionario(array $tabelas): array
    {
        $fks = collect(DB::select("select tc.table_name, kcu.column_name, ccu.table_name as alvo, ccu.column_name as alvo_coluna
            from information_schema.table_constraints tc join information_schema.key_column_usage kcu on kcu.constraint_name = tc.constraint_name and kcu.table_schema = tc.table_schema
            join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema
            where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public'"))->keyBy(fn ($r) => "{$r->table_name}.{$r->column_name}");
        $cols = DB::table('information_schema.columns')->where('table_schema', 'public')->whereIn('table_name', $tabelas)->orderBy('table_name')->orderBy('ordinal_position')
            ->get(['table_name', 'column_name', 'data_type', 'character_maximum_length', 'is_nullable', 'column_default']);
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['tabela', 'descricao_tabela', 'coluna', 'tipo', 'tamanho', 'obrigatoria', 'padrao', 'referencia', 'exportada'], ';');
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Dicionário de dados</title><style>body{font-family:Arial;font-size:12px;margin:24px}'
            . 'table{border-collapse:collapse;width:100%;margin-bottom:20px}td,th{border:1px solid #ccc;padding:3px 6px;text-align:left}th{background:#eef}h2{font-size:15px;margin:18px 0 4px}</style></head><body>'
            . '<h1>Dicionário de dados</h1><p>Gerado em ' . now('America/Sao_Paulo')->format('d/m/Y H:i') . '. Cada tabela corresponde a dados/&lt;tabela&gt;.csv (separador ;, UTF-8).</p>';
        foreach ($cols->groupBy('table_name') as $t => $lista) {
            $html .= '<h2 id="' . e($t) . '">' . e($t) . '</h2>' . (isset(self::DESCRICOES[$t]) ? '<p>' . e(self::DESCRICOES[$t]) . '</p>' : '')
                . '<table><tr><th>Coluna</th><th>Tipo</th><th>Obrigatória</th><th>Padrão</th><th>Referência</th></tr>';
            foreach ($lista as $c) {
                $fk = $fks["{$t}.{$c->column_name}"] ?? null;
                $ref = $fk ? "{$fk->alvo}.{$fk->alvo_coluna}" : '';
                $exportada = in_array($c->column_name, self::SEGREDOS, true) ? 'não (segredo)' : 'sim';
                fputcsv($out, [$t, self::DESCRICOES[$t] ?? '', $c->column_name, $c->data_type, $c->character_maximum_length, $c->is_nullable === 'NO' ? 'sim' : 'não',
                    $c->column_default, $ref, $exportada], ';');
                $html .= '<tr><td>' . e($c->column_name) . ($exportada !== 'sim' ? ' <i>(não exportada)</i>' : '') . '</td><td>' . e($c->data_type . ($c->character_maximum_length ? "({$c->character_maximum_length})" : ''))
                    . '</td><td>' . ($c->is_nullable === 'NO' ? 'sim' : 'não') . '</td><td>' . e((string) $c->column_default) . '</td><td>' . ($ref ? '<a href="#' . e($fk->alvo) . '">' . e($ref) . '</a>' : '') . '</td></tr>';
            }
            $html .= '</table>';
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return [$csv, $html . '</body></html>'];
    }

    private static function indiceArquivos(string $prefixo): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['tabela', 'id', 'coluna', 'arquivo_no_pacote', 'nome_original', 'sha256_gravado'], ';');
        $colunasPath = DB::table('information_schema.columns')->where('table_schema', 'public')
            ->where(fn ($q) => $q->where('column_name', 'like', '%_path')->orWhere('column_name', 'path'))->whereNotIn('table_name', self::FORA)->get(['table_name', 'column_name']);
        foreach ($colunasPath as $c) {
            $temNome = DB::table('information_schema.columns')->where('table_name', $c->table_name)->where('column_name', 'nome')->exists();
            $temHash = DB::table('information_schema.columns')->where('table_name', $c->table_name)->where('column_name', 'hash_sha256')->exists();
            $temId = DB::table('information_schema.columns')->where('table_name', $c->table_name)->where('column_name', 'id')->exists();
            if (! $temId) {
                continue;
            }
            $sel = array_values(array_filter(['id', $c->column_name, $temNome ? 'nome' : null, $temHash ? 'hash_sha256' : null]));
            foreach (DB::table($c->table_name)->select($sel)->whereNotNull($c->column_name)->orderBy('id')->cursor()->map(fn ($l) => (array) $l) as $l) {
                $p = (string) $l[$c->column_name];
                if ($p === '' || ! str_starts_with($p, $prefixo)) {
                    continue;
                }
                fputcsv($out, [$c->table_name, $l['id'], $c->column_name, 'arquivos/' . ltrim(substr($p, strlen($prefixo)), '/'), $l['nome'] ?? '', $l['hash_sha256'] ?? ''], ';');
            }
        }
        rewind($out);
        $s = stream_get_contents($out);
        fclose($out);

        return $s;
    }

    /** Evidências das assinaturas (4.26.16): valem sem o sistema, junto com o hash do documento. */
    private static function evidencias(): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['assinatura_id', 'documento_id', 'tipo', 'signatario', 'cpf', 'email', 'assinado_em_utc', 'ip', 'geolocalizacao', 'hash_documento', 'algoritmo',
            'politica', 'arquivo_assinado_no_pacote', 'codigo_verificacao'], ';');
        $prefixo = TenantStorage::prefix();
        foreach (DB::table('ged_assinaturas as a')->leftJoin('users as u', 'u.id', '=', 'a.signatario_id')->where('a.status', 'assinado')->orderBy('a.id')
            ->select(['a.*', 'u.name as usuario'])->cursor() as $a) {
            fputcsv($out, [$a->id, $a->documento_id, $a->tipo_assinatura, $a->usuario ?? $a->nome_signatario, $a->cpf_signatario, $a->email_signatario, $a->assinado_em,
                $a->ip, $a->geolocalizacao, $a->hash_documento, $a->algoritmo_hash, $a->politica_assinatura,
                $a->arquivo_assinado_path && str_starts_with($a->arquivo_assinado_path, $prefixo) ? 'arquivos/' . ltrim(substr($a->arquivo_assinado_path, strlen($prefixo)), '/') : '',
                $a->codigo_verificacao], ';');
        }
        rewind($out);
        $s = stream_get_contents($out);
        fclose($out);

        return $s;
    }

    private static function leiame(int $tabelas, int $arquivos): string
    {
        $q = fn ($t) => DB::table($t)->count();

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>LEIA-ME — exportação do acervo</title><style>body{font-family:Arial;max-width:860px;margin:24px auto;line-height:1.5}code{background:#eee;padding:1px 4px}</style></head><body>'
            . '<h1>Exportação completa do acervo</h1><p>Gerada em ' . now('America/Sao_Paulo')->format('d/m/Y H:i') . ". {$tabelas} tabelas e {$arquivos} arquivos. "
            . 'Processos: ' . $q('proc_processos') . ' · tramitações: ' . $q('proc_tramitacoes') . ' · documentos do GED: ' . $q('ged_documentos') . ' · assinaturas: ' . $q('ged_assinaturas') . '.</p>'
            . '<h2>Pastas</h2><ul>'
            . '<li><code>dados/</code> — uma planilha CSV por tabela (separador <code>;</code>, UTF-8). Os <b>ids</b> são os do sistema: as colunas <code>*_id</code> apontam para o <code>id</code> da tabela indicada no dicionário, preservando os relacionamentos (processo → tramitações → anexos, documento → versões → assinaturas etc.).</li>'
            . '<li><code>dicionario/</code> — o dicionário de dados (tabela, coluna, tipo, obrigatoriedade, referência) em HTML e CSV.</li>'
            . '<li><code>arquivos/</code> — os arquivos <b>originais</b>, sem conversão (PDFs assinados preservam a assinatura). <code>arquivos/indice.csv</code> diz qual registro aponta para cada arquivo.</li>'
            . '<li><code>assinaturas/evidencias.csv</code> — quem assinou, quando (UTC), IP, hash do documento e o arquivo assinado.</li>'
            . '<li><code>manifesto.json</code> — SHA-256 de cada item, para conferir a integridade do pacote.</li></ul>'
            . '<h2>Validar as assinaturas sem o sistema</h2><ul>'
            . '<li><b>ICP-Brasil (PAdES)</b>: abra o PDF assinado de <code>arquivos/</code> no validador do ITI (validar.iti.gov.br) ou em leitor de PDF com a cadeia ICP-Brasil.</li>'
            . '<li><b>Assinatura eletrônica simples / por link</b>: a prova é o registro em <code>assinaturas/evidencias.csv</code> + o hash do documento — calcule o SHA-256 do arquivo e compare com <code>hash_documento</code>.</li></ul>'
            . '<h2>Não exportado</h2><p>Senhas, segredos de verificação em duas etapas e tokens de acesso (colunas marcadas como "não exportada" no dicionário).</p></body></html>';
    }
}
