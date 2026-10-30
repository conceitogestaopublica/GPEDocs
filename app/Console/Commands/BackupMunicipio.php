<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Backup;
use App\Support\IntegridadeAcervo;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rotinas de backup e integridade (TR 4.26.13), agendadas em routes/console.php:
 *   backup:executar --tipo=incremental|completo   gera o backup de cada município
 *   backup:executar --verificar                   confere os backups ainda não verificados
 *   backup:executar --acervo                      recalcula os hashes do acervo
 */
class BackupMunicipio extends Command
{
    protected $signature = 'backup:executar {--tenant=ALL : id do município ou ALL} {--tipo=incremental : completo|incremental}
        {--verificar : verifica os backups pendentes de verificação} {--acervo : verifica a integridade do acervo}';

    protected $description = 'Backup do município (banco + arquivos), verificação dos backups e integridade do acervo';

    public function handle(TenantContext $ctx): int
    {
        $falhou = false;
        foreach (Tenant::active()->get()->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant')) as $tenant) {
            $ctx->set($tenant);
            if ($this->option('acervo')) {
                $v = IntegridadeAcervo::verificar();
                $this->info("{$tenant->id}: acervo — {$v->conferidos} conferidos, {$v->divergentes} divergentes, {$v->ausentes} ausentes");
                $falhou = $falhou || $v->divergentes || $v->ausentes;
            } elseif ($this->option('verificar')) {
                foreach (DB::table('port_backups')->where('status', 'pronto')->whereNull('verificado_em')->orderBy('id')->get() as $b) {
                    $ok = Backup::verificar($b);
                    $this->info("{$tenant->id}: backup {$b->id} " . ($ok ? 'íntegro' : 'COM PROBLEMA'));
                    $falhou = $falhou || ! $ok;
                }
            } else {
                $b = Backup::executar($this->option('tipo') === 'completo' ? 'completo' : 'incremental');
                $this->info("{$tenant->id}: backup {$b->id} {$b->tipo} — {$b->status}" . ($b->erro ? " ({$b->erro})" : " ({$b->arquivos} arquivo(s), {$b->tamanho} bytes)"));
                $falhou = $falhou || $b->status !== 'pronto';
            }
        }

        return $falhou ? self::FAILURE : self::SUCCESS;
    }
}
