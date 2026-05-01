<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que um usuario tenha "acesso geral" a sua UG ativa — ele ve a
 * Caixa Entrada Setor da UG inteira (todas unidades), em vez de apenas a
 * unidade onde esta lotado. Util para chefes de gabinete, secretarios,
 * gestores que precisam acompanhar tudo da UG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('acesso_geral_ug')->default(false)->after('unidade_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('acesso_geral_ug');
        });
    }
};
