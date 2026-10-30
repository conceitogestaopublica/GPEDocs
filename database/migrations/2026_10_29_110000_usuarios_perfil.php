<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meu perfil do servidor (TR 4.7.10, 4.7.14, 4.7.18): cargo, sexo, data de
 * nascimento, foto e e-mail secundário para notificações.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('cargo', 120)->nullable();
            $t->string('sexo', 1)->nullable();          // F | M | O (outro / prefere não informar)
            $t->date('data_nascimento')->nullable();
            $t->string('foto_path', 500)->nullable();
            $t->string('email_secundario', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['cargo', 'sexo', 'data_nascimento', 'foto_path', 'email_secundario']);
        });
    }
};
