<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\TenantAware;
use App\Support\DocumentoPessoa;
use App\Support\DocumentoUnicoUsuarios;
use Illuminate\Console\Command;

/**
 * Lista as contas com o mesmo CPF/CNPJ e, com o banco limpo, cria o índice que impede repetição.
 * Não altera nem apaga conta: a decisão de qual fica é de quem administra, caso a caso.
 *
 *   php artisan usuarios:documentos-duplicados --tenant=paraguacu
 *   php artisan usuarios:documentos-duplicados --tenant=ALL
 */
class UsuariosDocumentosDuplicados extends Command
{
    use TenantAware;

    protected $signature = 'usuarios:documentos-duplicados
        {--tenant= : Tenant: subdomínio, sub.domínio ou ID (ALL para todos)}';

    protected $description = 'Lista contas com CPF/CNPJ repetido e, sem repetidos, cria o índice de documento único';

    public function handle(): int
    {
        return $this->runForTenant(fn () => $this->verificar());
    }

    private function verificar(): int
    {
        if (! DocumentoUnicoUsuarios::aplicavel()) {
            $this->error('Este comando só funciona em PostgreSQL.');

            return self::FAILURE;
        }

        $repetidos = DocumentoUnicoUsuarios::duplicados();

        if ($repetidos->isEmpty()) {
            DocumentoUnicoUsuarios::criarIndice();
            $this->info('Nenhum CPF/CNPJ repetido. Índice de documento único ativo.');

            return self::SUCCESS;
        }

        $this->table(['Documento', 'ID', 'Nome', 'E-mail', 'Tipo'], $repetidos->map(fn ($c) => [
            DocumentoPessoa::mascarar($c->documento), $c->id, $c->name, $c->email, $c->tipo,
        ])->all());

        $this->warn(sprintf(
            '%d conta(s) em %d documento(s) repetido(s). Índice NÃO criado: resolva os casos e rode este comando de novo.',
            $repetidos->count(),
            $repetidos->pluck('documento')->unique()->count()
        ));

        return self::FAILURE;
    }
}
