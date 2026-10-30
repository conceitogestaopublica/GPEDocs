<?php

declare(strict_types=1);

namespace App\Support;

use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Backup do município (TR 4.26.13): um .zip com o banco (pg_dump, formato custom), os arquivos do acervo
 * (todos no completo; no incremental, só os novos ou alterados desde o backup anterior) e o manifesto com o
 * SHA-256 de cada item. Vai para o disco "backup" (em produção, outro ambiente). verificar() confere cada
 * hash do manifesto e se o dump abre no pg_restore. Restauração: docs/PORTABILIDADE_BACKUP.md.
 */
final class Backup
{
    public static function config(): object
    {
        return DB::table('port_config')->first() ?? (object) ['somente_leitura' => false, 'somente_leitura_motivo' => null, 'retencao_dias' => 35];
    }

    public static function executar(string $tipo = 'incremental'): object
    {
        $base = $tipo === 'incremental' ? DB::table('port_backups')->where('status', 'pronto')->orderByDesc('id')->first() : null;
        if ($tipo === 'incremental' && ! $base) {
            $tipo = 'completo';   // sem backup anterior, o primeiro é completo
        }
        $id = DB::table('port_backups')->insertGetId(['tipo' => $tipo, 'base_id' => $base?->id, 'status' => 'gerando', 'destino' => (string) config('filesystems.disks.backup.driver'),
            'inicio_em' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $tmp = sys_get_temp_dir() . '/ged-backup-' . $id . '-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0700, true);

        try {
            $banco = "{$tmp}/banco.dump";
            self::dump($banco);
            $docs = Storage::disk('documentos');
            $prefixo = TenantStorage::prefix();
            $desde = $base ? \Illuminate\Support\Carbon::parse($base->inicio_em, 'UTC')->getTimestamp() : 0;
            $zipPath = "{$tmp}/backup.zip";
            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFile($banco, 'banco.dump');
            $manifesto = ['municipio' => DB::connection()->getDatabaseName(), 'tipo' => $tipo, 'base' => $base?->id, 'gerado_em' => now()->toIso8601String(),
                'banco' => ['arquivo' => 'banco.dump', 'sha256' => hash_file('sha256', $banco), 'tamanho' => filesize($banco)], 'arquivos' => []];
            $locais = [];
            foreach ($docs->allFiles($prefixo) as $f) {
                if ($desde && $docs->lastModified($f) < $desde) {
                    continue;
                }
                $rel = ltrim(substr($f, strlen($prefixo)), '/');
                $origem = self::paraLocal($docs, $f, $tmp, $locais);
                $zip->addFile($origem, "arquivos/{$rel}");
                $manifesto['arquivos'][] = ['caminho' => "arquivos/{$rel}", 'sha256' => hash_file('sha256', $origem), 'tamanho' => filesize($origem)];
            }
            $zip->addFromString('manifesto.json', json_encode($manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->close();
            foreach ($locais as $l) {
                @unlink($l);
            }

            $nome = $manifesto['municipio'] . '/' . now('America/Sao_Paulo')->format('Y-m-d_His') . "-{$tipo}-{$id}.zip";
            $h = fopen($zipPath, 'rb');
            Storage::disk('backup')->writeStream($nome, $h);
            fclose($h);
            DB::table('port_backups')->where('id', $id)->update(['status' => 'pronto', 'arquivo' => $nome, 'tamanho' => filesize($zipPath),
                'arquivos' => count($manifesto['arquivos']), 'sha256' => hash_file('sha256', $zipPath), 'banco_sha256' => $manifesto['banco']['sha256'],
                'fim_em' => now(), 'updated_at' => now()]);
            self::reter();
        } catch (\Throwable $e) {
            DB::table('port_backups')->where('id', $id)->update(['status' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 2000), 'fim_em' => now(), 'updated_at' => now()]);
        } finally {
            self::limpar($tmp);
        }

        return DB::table('port_backups')->find($id);
    }

    /** Confere o .zip do backup: hash do pacote, de cada item do manifesto e se o dump abre. */
    public static function verificar(object $b): bool
    {
        $tmp = sys_get_temp_dir() . '/ged-verif-' . $b->id . '-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0700, true);
        $ok = false;
        $erro = null;
        try {
            $zipPath = "{$tmp}/b.zip";
            $out = fopen($zipPath, 'wb');
            stream_copy_to_stream(Storage::disk('backup')->readStream($b->arquivo), $out);
            fclose($out);
            if (hash_file('sha256', $zipPath) !== $b->sha256) {
                throw new \RuntimeException('O hash do pacote mudou desde a gravação.');
            }
            $zip = new \ZipArchive();
            $zip->open($zipPath);
            $m = json_decode((string) $zip->getFromName('manifesto.json'), true);
            foreach (array_merge([['caminho' => 'banco.dump', 'sha256' => $m['banco']['sha256']]], $m['arquivos']) as $item) {
                $s = $zip->getStream($item['caminho']);
                if (! $s) {
                    throw new \RuntimeException("Falta no pacote: {$item['caminho']}");
                }
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $s);
                fclose($s);
                if (hash_final($ctx) !== $item['sha256']) {
                    throw new \RuntimeException("Hash diferente: {$item['caminho']}");
                }
            }
            $zip->extractTo($tmp, 'banco.dump');
            $zip->close();
            $p = new Process(['pg_restore', '--list', "{$tmp}/banco.dump"]);
            $p->setTimeout(600)->run();
            if (! $p->isSuccessful()) {
                throw new \RuntimeException('O dump do banco não abre no pg_restore: ' . mb_substr($p->getErrorOutput(), 0, 300));
            }
            $ok = true;
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        } finally {
            self::limpar($tmp);
        }
        DB::table('port_backups')->where('id', $b->id)->update(['verificado_em' => now(), 'verificado_ok' => $ok, 'erro' => $ok ? $b->erro : $erro, 'updated_at' => now()]);

        return $ok;
    }

    /** Apaga backups mais velhos que a retenção; o último completo pronto sempre fica. */
    public static function reter(): int
    {
        $dias = (int) (self::config()->retencao_dias ?: 35);
        $ultimoCompleto = DB::table('port_backups')->where('tipo', 'completo')->where('status', 'pronto')->max('id');
        $n = 0;
        foreach (DB::table('port_backups')->where('created_at', '<', now()->subDays($dias))->where('id', '<>', $ultimoCompleto ?? 0)->get() as $b) {
            if ($b->arquivo) {
                Storage::disk('backup')->delete($b->arquivo);
            }
            DB::table('port_backups')->where('base_id', $b->id)->update(['base_id' => null]);
            DB::table('port_backups')->where('id', $b->id)->delete();
            $n++;
        }

        return $n;
    }

    /** pg_dump do banco do município (a conexão do tenant já está no contexto). */
    public static function dump(string $destino): void
    {
        $c = config('database.connections.' . DB::getDefaultConnection());
        $p = new Process(['pg_dump', '-Fc', '--no-owner', '--no-privileges', '-h', (string) $c['host'], '-p', (string) ($c['port'] ?? 5432),
            '-U', (string) $c['username'], '-d', (string) $c['database'], '-f', $destino], null, ['PGPASSWORD' => (string) $c['password']]);
        $p->setTimeout(3600)->run();
        if (! $p->isSuccessful()) {
            throw new \RuntimeException('pg_dump falhou: ' . mb_substr($p->getErrorOutput(), 0, 500));
        }
    }

    /** Caminho local do arquivo (disco local) ou cópia temporária (S3). */
    public static function paraLocal($disco, string $f, string $tmp, array &$locais): string
    {
        $adapter = config('filesystems.disks.documentos.driver');
        if ($adapter === 'local') {
            return $disco->path($f);
        }
        $l = $tmp . '/t-' . count($locais) . '-' . basename($f);
        $o = fopen($l, 'wb');
        stream_copy_to_stream($disco->readStream($f), $o);
        fclose($o);

        return $locais[] = $l;
    }

    public static function limpar(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
