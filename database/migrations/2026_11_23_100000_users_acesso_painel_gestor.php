<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login único do app (Portal do Cidadão): o perfil "prefeito" (painéis de gestão) sai do cargo que o
 * RH traz (docs:importar-servidores → users.cargo = PREFEITO MUNICIPAL). Esta marca é a exceção
 * manual — vice-prefeito, secretário, chefe de gabinete — dada pela Prefeitura no cadastro do usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('acesso_painel_gestor')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('acesso_painel_gestor'));
    }
};
