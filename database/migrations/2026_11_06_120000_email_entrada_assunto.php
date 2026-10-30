<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-mail setorial que vira manifestação (TR 4.33.4): o assunto ganha um endereço de entrada e o
 * setor que recebe; o e-mail que chega nesse endereço abre o processo com canal "e-mail".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->string('email_entrada', 150)->nullable()->unique();
            $t->foreignId('email_unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->dropConstrainedForeignId('email_unidade_id');
            $t->dropColumn('email_entrada');
        });
    }
};
