<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SistemaIntegrado;
use Illuminate\Console\Command;

/**
 * Cadastra um sistema externo autorizado a integrar com o GPE Docs e
 * gera o API token. O token e exibido APENAS uma vez — guarde-o.
 *
 * Exemplos:
 *   php artisan ged:sistema-cadastrar
 *   php artisan ged:sistema-cadastrar gpe "GPE - Sistema de Gestao Publica"
 *   php artisan ged:sistema-cadastrar gpe --regenerar-token
 */
class SistemaIntegradoCadastrar extends Command
{
    protected $signature = 'ged:sistema-cadastrar
        {codigo? : Codigo unico do sistema (ex: gpe, rh, patrimonio)}
        {nome? : Nome amigavel}
        {--descricao= : Descricao livre}
        {--regenerar-token : Regenera o token (invalidando o antigo) se o sistema ja existir}';

    protected $description = 'Cadastra um sistema externo e gera API token de integracao';

    public function handle(): int
    {
        $codigo = $this->argument('codigo') ?: $this->ask('Codigo do sistema (ex: gpe, rh)');
        $codigo = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $codigo));

        if (! $codigo) {
            $this->error('Codigo invalido.');
            return self::FAILURE;
        }

        $existente = SistemaIntegrado::where('codigo', $codigo)->first();

        if ($existente && ! $this->option('regenerar-token')) {
            $this->error("Sistema '{$codigo}' ja existe. Use --regenerar-token para gerar novo token.");
            return self::FAILURE;
        }

        $sistema = $existente ?? new SistemaIntegrado([
            'codigo'    => $codigo,
            'nome'      => $this->argument('nome') ?: $this->ask('Nome amigavel'),
            'descricao' => $this->option('descricao'),
            'ativo'     => true,
        ]);

        $tokenPuro = $sistema->gerarToken();
        $sistema->save();

        $this->newLine();
        $this->info('=== Sistema integrado ' . ($existente ? 'atualizado' : 'cadastrado') . ' ===');
        $this->line('Codigo: ' . $sistema->codigo);
        $this->line('Nome:   ' . $sistema->nome);
        $this->newLine();
        $this->warn('TOKEN (guarde agora — nao sera exibido novamente):');
        $this->line($tokenPuro);
        $this->newLine();
        $this->line('Uso no client:');
        $this->line('  Authorization: Bearer ' . $tokenPuro);
        $this->newLine();
        $this->line('Endpoints:');
        $this->line('  POST ' . url('/api/integracoes/documentos'));
        $this->line('  GET  ' . url('/api/integracoes/documentos/{numero_externo}'));

        return self::SUCCESS;
    }
}
