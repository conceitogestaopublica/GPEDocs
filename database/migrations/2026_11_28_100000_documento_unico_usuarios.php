<?php

use App\Support\DocumentoUnicoUsuarios;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Um CPF/CNPJ em uma conta só. Havendo repetidos, a migration não apaga nada nem trava o deploy:
 * avisa e deixa o índice para o comando usuarios:documentos-duplicados, depois de resolvidos.
 * O cadastro já recusa documento repetido mesmo sem o índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DocumentoUnicoUsuarios::aplicavel()) {
            return;
        }

        $repetidos = DocumentoUnicoUsuarios::duplicados();
        if ($repetidos->isEmpty()) {
            DocumentoUnicoUsuarios::criarIndice();

            return;
        }

        $aviso = sprintf(
            'Índice de documento único NÃO criado: %d conta(s) com CPF/CNPJ repetido. Veja com: php artisan usuarios:documentos-duplicados --tenant=<subdominio>',
            $repetidos->count()
        );
        Log::warning($aviso);
        echo "\n  {$aviso}\n";
    }

    public function down(): void
    {
        if (DocumentoUnicoUsuarios::aplicavel()) {
            DocumentoUnicoUsuarios::removerIndice();
        }
    }
};
