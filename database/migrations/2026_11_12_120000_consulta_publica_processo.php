<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Link de consulta do processo sem login, com andamento e pareceres (TR 4.48.27). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_processos', fn (Blueprint $t) => $t->uuid('consulta_token')->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('proc_processos', fn (Blueprint $t) => $t->dropColumn('consulta_token'));
    }
};