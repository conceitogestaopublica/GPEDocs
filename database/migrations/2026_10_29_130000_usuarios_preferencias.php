<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferências do servidor (TR 4.7.16, 4.7.17, 4.7.19): editor básico ou
 * avançado, como abrir anexos e quais avisos chegam por e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->jsonb('preferencias')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('preferencias');
        });
    }
};
