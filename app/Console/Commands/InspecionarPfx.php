<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CertificadoService;
use Illuminate\Console\Command;

/**
 * Diagnostica um arquivo PFX/P12: imprime metadados, OIDs ICP-Brasil,
 * extensoes (AIA, SAN), e tenta validar a cadeia.
 *
 * Uso:
 *   php artisan icp-brasil:inspecionar-pfx C:\caminho\cert.pfx senha
 */
class InspecionarPfx extends Command
{
    protected $signature = 'icp-brasil:inspecionar-pfx
        {arquivo : Caminho absoluto do arquivo .pfx ou .p12}
        {senha   : Senha do certificado}';

    protected $description = 'Inspeciona um PFX para diagnosticar problemas de validacao ICP-Brasil';

    public function handle(CertificadoService $svc): int
    {
        $arquivo = $this->argument('arquivo');
        $senha   = $this->argument('senha');

        if (! is_file($arquivo)) {
            $this->error('Arquivo nao encontrado: ' . $arquivo);
            return self::FAILURE;
        }

        $bytes = (string) file_get_contents($arquivo);
        $this->info('Arquivo: ' . $arquivo . ' (' . strlen($bytes) . ' bytes)');

        try {
            $material = $svc->abrirPfx($bytes, $senha);
        } catch (\Throwable $e) {
            $this->error('Falha ao abrir PFX: ' . $e->getMessage());
            return self::FAILURE;
        }

        $cert = $material['cert'];
        $extras = $material['extracerts'] ?? [];

        $this->info('PFX aberto OK. Cert do titular: ' . strlen($cert) . ' bytes, ' . count($extras) . ' cert(s) extra(s) na cadeia.');
        $this->newLine();

        // 1) Metadados basicos
        $this->info('=== Metadados ===');
        $info = openssl_x509_parse($cert, true);
        $this->line('  Subject CN : ' . ($info['subject']['CN'] ?? '?'));
        $this->line('  Issuer  CN : ' . ($info['issuer']['CN'] ?? '?'));
        $this->line('  Serial     : ' . strtoupper((string) ($info['serialNumberHex'] ?? $info['serialNumber'] ?? '?')));
        $this->line('  Valido de  : ' . date('Y-m-d H:i:s', (int) ($info['validFrom_time_t'] ?? 0)));
        $this->line('  Valido ate : ' . date('Y-m-d H:i:s', (int) ($info['validTo_time_t'] ?? 0)));
        $this->line('  Thumbprint : ' . openssl_x509_fingerprint($cert, 'sha256'));
        $this->newLine();

        // 2) Output do openssl x509 -text (so a parte de extensoes)
        $this->info('=== Extensoes (openssl x509 -text) ===');
        $tmp = tempnam(sys_get_temp_dir(), 'pfx_');
        file_put_contents($tmp, $cert);
        $textOut = shell_exec('openssl x509 -in ' . escapeshellarg($tmp) . ' -noout -text 2>&1');
        @unlink($tmp);

        // Mostra apenas o bloco de extensoes
        if (preg_match('/X509v3 extensions:.*$/s', (string) $textOut, $m)) {
            $linhas = explode("\n", $m[0]);
            foreach (array_slice($linhas, 0, 60) as $l) {
                $this->line('  ' . $l);
            }
        } else {
            $this->line('  (nao foi possivel extrair extensoes)');
        }
        $this->newLine();

        // 3) Subject Alternative Name (raw)
        $this->info('=== Subject Alternative Name ===');
        $this->line('  ' . ($info['extensions']['subjectAltName'] ?? '(nao tem)'));
        $this->newLine();

        // 4) Authority Information Access (AIA)
        $this->info('=== Authority Information Access (AIA) ===');
        $this->line('  ' . ($info['extensions']['authorityInfoAccess'] ?? '(nao tem)'));
        $this->newLine();

        // 5) Tentativa de extrair CPF
        $cpf = $svc->extrairCpf($cert);
        $cnpj = $svc->extrairCnpj($cert);
        $this->info('=== Identificadores ICP-Brasil ===');
        $this->line('  CPF (OID 2.16.76.1.3.1): ' . ($cpf ?? '(NAO EXTRAIDO)'));
        $this->line('  CNPJ (OID 2.16.76.1.3.3): ' . ($cnpj ?? '(NAO EXTRAIDO)'));
        $this->newLine();

        // 6) Cadeia que veio no PFX
        $this->info('=== Cadeia anexada ao PFX ===');
        if (empty($extras)) {
            $this->warn('  (PFX nao contem certs intermediarios)');
        } else {
            foreach ($extras as $i => $pem) {
                $ie = openssl_x509_parse($pem, true);
                $this->line(sprintf('  [%d] %s (issuer: %s)', $i + 1, $ie['subject']['CN'] ?? '?', $ie['issuer']['CN'] ?? '?'));
            }
        }
        $this->newLine();

        // 7) Tentativa de validar cadeia
        $this->info('=== Validacao de cadeia ICP-Brasil ===');
        $ehIcp = $svc->ehIcpBrasil($cert);
        $this->line('  Heuristica ehIcpBrasil: ' . ($ehIcp ? 'SIM' : 'NAO'));

        $ok = $svc->validarCadeiaIcpBrasil($cert, $extras);
        $this->line('  validarCadeiaIcpBrasil: ' . ($ok ? '<fg=green>OK</>' : '<fg=red>FALHOU</>'));

        if (! $ok) {
            $this->newLine();
            $this->info('=== Diagnostico do openssl verify ===');
            $base = config('icp_brasil.truststore_path');
            $bundle = $this->montarBundle($base);
            if ($bundle) {
                $certFile = tempnam(sys_get_temp_dir(), 'cert_');
                file_put_contents($certFile, $cert);

                $extrasFile = null;
                if (! empty($extras)) {
                    $extrasFile = tempnam(sys_get_temp_dir(), 'extras_');
                    file_put_contents($extrasFile, implode("\n", $extras));
                }

                $cmd = sprintf(
                    'openssl verify -CAfile %s%s %s 2>&1',
                    escapeshellarg($bundle),
                    $extrasFile ? ' -untrusted ' . escapeshellarg($extrasFile) : '',
                    escapeshellarg($certFile),
                );
                $this->line('  cmd: ' . $cmd);
                $this->line('  saida:');
                $this->line($this->indent((string) shell_exec($cmd)));

                @unlink($certFile);
                @unlink($bundle);
                if ($extrasFile) @unlink($extrasFile);
            } else {
                $this->error('  Truststore vazia ou inexistente. Rode: php artisan icp-brasil:install-truststore');
            }
        }

        return self::SUCCESS;
    }

    private function montarBundle(string $base): ?string
    {
        $bundle = '';
        foreach (['raiz', 'intermediarias'] as $sub) {
            $path = $base . DIRECTORY_SEPARATOR . $sub;
            if (! is_dir($path)) continue;
            foreach (scandir($path) as $f) {
                if (preg_match('/\.(pem|crt|cer)$/i', $f)) {
                    $bundle .= file_get_contents($path . DIRECTORY_SEPARATOR . $f) . "\n";
                }
            }
        }
        if ($bundle === '') return null;
        $tmp = tempnam(sys_get_temp_dir(), 'bundle_');
        file_put_contents($tmp, $bundle);
        return $tmp;
    }

    private function indent(string $s): string
    {
        return preg_replace('/^/m', '    ', $s);
    }
}
