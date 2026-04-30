<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Lista os certificados ICP-Brasil instalados em storage/app/private/icp-brasil
 * e valida o formato de cada um. Util para confirmar que a truststore esta
 * pronta para receber assinaturas qualificadas.
 */
class IcpBrasilVerifyTruststore extends Command
{
    protected $signature = 'icp-brasil:verify-truststore';

    protected $description = 'Lista e valida os certificados ICP-Brasil instalados na truststore';

    public function handle(): int
    {
        $base = config('icp_brasil.truststore_path');
        $raiz = $base . DIRECTORY_SEPARATOR . 'raiz';
        $int  = $base . DIRECTORY_SEPARATOR . 'intermediarias';

        $this->info('Truststore: ' . $base);
        $this->newLine();

        $totalRaiz = $this->listar('AC Raiz', $raiz);
        $this->newLine();
        $totalInt  = $this->listar('ACs Intermediarias', $int);
        $this->newLine();

        $total = $totalRaiz + $totalInt;
        if ($total === 0) {
            $this->warn('Nenhum certificado instalado.');
            $this->line('Execute: php artisan icp-brasil:install-truststore');
            return self::FAILURE;
        }

        $this->info(sprintf('Total: %d certificados validos.', $total));

        if ($totalRaiz === 0) {
            $this->warn('AVISO: nenhuma AC Raiz instalada — assinaturas qualificadas serao rejeitadas.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function listar(string $titulo, string $diretorio): int
    {
        $this->info($titulo . ':');
        if (! is_dir($diretorio)) {
            $this->line('  (diretorio nao existe)');
            return 0;
        }

        $arquivos = collect(scandir($diretorio))
            ->filter(fn ($f) => preg_match('/\.(pem|crt|cer)$/i', $f))
            ->values();

        if ($arquivos->isEmpty()) {
            $this->line('  (vazio)');
            return 0;
        }

        $rows = [];
        $validos = 0;

        foreach ($arquivos as $arquivo) {
            $caminho = $diretorio . DIRECTORY_SEPARATOR . $arquivo;
            $pem = (string) file_get_contents($caminho);
            $info = openssl_x509_parse($pem, true);

            if ($info === false) {
                $rows[] = [$arquivo, '<fg=red>INVALIDO</>', '-', '-'];
                continue;
            }

            $cn = $info['subject']['CN'] ?? '?';
            $validade = isset($info['validTo_time_t'])
                ? date('Y-m-d', (int) $info['validTo_time_t'])
                : '?';

            $expirado = isset($info['validTo_time_t']) && $info['validTo_time_t'] < time();
            $statusValidade = $expirado ? '<fg=red>EXPIRADO</>' : '<fg=green>OK</>';

            $rows[] = [$arquivo, $cn, $validade, $statusValidade];
            if (! $expirado) {
                $validos++;
            }
        }

        $this->table(['Arquivo', 'Common Name', 'Valido ate', 'Status'], $rows);

        return $validos;
    }
}
