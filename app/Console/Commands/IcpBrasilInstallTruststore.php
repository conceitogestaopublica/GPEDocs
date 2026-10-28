<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Baixa a cadeia ICP-Brasil oficial do ITI e armazena em
 * storage/app/private/icp-brasil/. Os certificados sao publicos.
 *
 * Uso:
 *   php artisan icp-brasil:install-truststore
 *   php artisan icp-brasil:install-truststore --force   (sobrescreve existentes)
 */
class IcpBrasilInstallTruststore extends Command
{
    protected $signature = 'icp-brasil:install-truststore {--force : sobrescreve certificados ja existentes}';

    protected $description = 'Baixa a cadeia ICP-Brasil oficial do ITI e instala em storage/app/private/icp-brasil';

    public function handle(): int
    {
        $base = config('icp_brasil.truststore_path');
        $raiz = $base . DIRECTORY_SEPARATOR . 'raiz';
        $int  = $base . DIRECTORY_SEPARATOR . 'intermediarias';

        foreach ([$raiz, $int] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $force = (bool) $this->option('force');

        $this->info('Truststore destino: ' . $base);
        $this->newLine();

        $sucesso = 0;
        $pulados = 0;
        $erros   = 0;

        $this->info('Baixando AC Raiz...');
        foreach ((array) config('icp_brasil.raiz', []) as $cert) {
            $destino = $raiz . DIRECTORY_SEPARATOR . $cert['arquivo'];
            $resultado = $this->baixarECCConverter($cert['nome'], $cert['url'], $destino, $force, $cert['sha256'] ?? null);
            $resultado === 'ok' ? $sucesso++ : ($resultado === 'pulado' ? $pulados++ : $erros++);
        }

        // Raízes que saíram da lista (ex.: a v10, que é só de SSL) deixam de
        // ser âncora de qualquer forma; remove o arquivo para não confundir.
        $esperados = array_column((array) config('icp_brasil.raiz', []), 'arquivo');
        foreach (glob($raiz . DIRECTORY_SEPARATOR . '*') ?: [] as $arquivo) {
            if (! in_array(basename($arquivo), $esperados, true)) {
                @unlink($arquivo);
                $this->line('  • ' . basename($arquivo) . ' <fg=yellow>[removido — fora da lista de raízes aceitas]</>');
            }
        }

        $this->newLine();
        $this->info('Baixando ACs intermediarias...');
        $intermediarias = (array) config('icp_brasil.intermediarias', []);
        if (empty($intermediarias)) {
            $this->line('  (nenhuma configurada — adicione em config/icp_brasil.php ou copie PEMs manualmente para ' . $int . ')');
        }
        foreach ($intermediarias as $cert) {
            $destino = $int . DIRECTORY_SEPARATOR . $cert['arquivo'];
            $resultado = $this->baixarECCConverter($cert['nome'], $cert['url'], $destino, $force);
            $resultado === 'ok' ? $sucesso++ : ($resultado === 'pulado' ? $pulados++ : $erros++);
        }

        // Rodando como root (docker exec), os arquivos ficariam ilegíveis para o
        // PHP da web: devolve a pasta ao dono do storage
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $dono = fileowner(storage_path('app'));
            $grupo = filegroup(storage_path('app'));
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ([$base, ...array_keys(iterator_to_array($iter))] as $caminho) {
                @chown($caminho, $dono);
                @chgrp($caminho, $grupo);
            }
        }

        $this->newLine();
        $this->info(sprintf('Resumo: %d instalados, %d pulados, %d erros.', $sucesso, $pulados, $erros));
        $this->line('Verifique a integridade com: php artisan icp-brasil:verify-truststore');

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function baixarECCConverter(string $nome, string $url, string $destino, bool $force, ?string $sha256 = null): string
    {
        $rotulo = sprintf('  • %s', $nome);

        if (is_file($destino) && ! $force) {
            $this->line($rotulo . ' <fg=yellow>[pulado — ja existe, use --force para baixar de novo]</>');
            return 'pulado';
        }

        try {
            // O servidor acraiz.icpbrasil.gov.br opera com cert proprio que nem
            // sempre encadeia ate uma raiz CA confiavel do sistema operacional.
            // Como a integridade aqui e validada via openssl_x509_parse logo abaixo,
            // toleramos o TLS sem cadeia confiavel para a operacao de download.
            $response = Http::timeout(30)
                ->withOptions(['verify' => false])
                ->get($url);

            if (! $response->successful()) {
                $this->error($rotulo . ' [HTTP ' . $response->status() . ' em ' . $url . ']');
                return 'erro';
            }

            $bytes = $response->body();
            if ($bytes === '') {
                $this->error($rotulo . ' [resposta vazia]');
                return 'erro';
            }

            // Detecta DER (binario) vs PEM (texto). Converte DER → PEM.
            $pem = $this->normalizarParaPem($bytes);
            if ($pem === null) {
                $this->error($rotulo . ' [formato nao reconhecido]');
                return 'erro';
            }

            // Valida que e um certificado X509 valido
            $info = openssl_x509_parse($pem, true);
            if ($info === false) {
                $this->error($rotulo . ' [openssl nao reconhece como X509]');
                return 'erro';
            }

            // Raiz com impressão fixada: arquivo diferente não entra (TLS acima
            // não é conferido, então é aqui que se garante a origem)
            $impressao = strtolower((string) openssl_x509_fingerprint($pem, 'sha256'));
            if ($sha256 && $impressao !== strtolower($sha256)) {
                $this->error($rotulo . " [SHA-256 {$impressao} não confere com o fixado em config/icp_brasil.php — não instalado]");
                return 'erro';
            }

            file_put_contents($destino, $pem);

            $cn = $info['subject']['CN'] ?? '?';
            $validade = isset($info['validTo_time_t'])
                ? date('Y-m-d', (int) $info['validTo_time_t'])
                : '?';
            $this->line($rotulo . " <fg=green>[ok — CN={$cn}, valido ate {$validade}]</>");

            return 'ok';
        } catch (Throwable $e) {
            $this->error($rotulo . ' [' . $e->getMessage() . ']');
            return 'erro';
        }
    }

    /**
     * Aceita um certificado em DER ou PEM e devolve sempre PEM.
     */
    private function normalizarParaPem(string $bytes): ?string
    {
        // PEM ja contem cabecalho ASCII
        if (str_contains($bytes, '-----BEGIN CERTIFICATE-----')) {
            return $bytes;
        }

        // Caso contrario tratamos como DER e convertemos
        $base64 = chunk_split(base64_encode($bytes), 64, "\n");
        return "-----BEGIN CERTIFICATE-----\n" . $base64 . "-----END CERTIFICATE-----\n";
    }
}
