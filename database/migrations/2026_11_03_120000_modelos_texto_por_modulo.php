<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos de texto do editor (TR 4.16.11): os modelos de ofício passam a servir também a
 * memorando, circular e despacho (`modulo`, "todos" vale para qualquer um) e podem ser do
 * setor (`unidade_id`, salvos por quem escreve) ou da administração (sem setor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_oficio_modelos', function (Blueprint $t) {
            $t->string('modulo', 20)->default('oficio');   // oficio | memorando | circular | despacho | todos
            $t->foreignId('unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();
            $t->index(['modulo', 'unidade_id']);
        });
    }

    public function down(): void
    {
        Schema::table('proc_oficio_modelos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('unidade_id');
            $t->dropColumn('modulo');
        });
    }
};
