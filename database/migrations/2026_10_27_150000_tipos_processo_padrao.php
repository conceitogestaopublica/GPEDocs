<?php

declare(strict_types=1);

use App\Support\TiposProcessoPadrao;
use Illuminate\Database\Migrations\Migration;

/**
 * Tipos de processo padrão (REQ, RH, LIC, OUV, LAI, TRIB) em todo município — roda
 * no tenant:migrate de cada um, inclusive os novos. Não mexe em tipo que já existe.
 * Banco sem usuário ainda: rodar depois `php artisan docs:tipos-processo-padrao`.
 */
return new class extends Migration
{
    public function up(): void
    {
        TiposProcessoPadrao::garantir();
    }

    public function down(): void
    {
        // Dados de configuração: não apaga (podem ter processos ligados).
    }
};
