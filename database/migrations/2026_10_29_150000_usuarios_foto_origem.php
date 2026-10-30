<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De onde veio a foto do servidor: null = enviada no GED (Meu perfil ou
 * administrador); "rh:<documento>" = foto do RH do gpe2, que ela guarda no
 * próprio GED. A importação só troca foto que veio do RH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('foto_origem', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('foto_origem');
        });
    }
};
