<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sobrestamento (processo parado, com motivo e prazo ou condição de retomada, sem contar prazo) e
 * vínculos entre processos: apensação (o apenso acompanha o principal até ser desapensado) e
 * anexação (definitiva: as peças vão para o principal e o anexado se encerra).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->timestamp('sobrestado_em')->nullable();
            $t->date('sobrestado_ate')->nullable();                 // retomada automática neste dia
            $t->string('sobrestamento_motivo', 1000)->nullable();
            $t->string('sobrestamento_condicao', 300)->nullable();  // condição de retomada (ex.: parecer da PGM)
            $t->foreignId('sobrestado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('apensado_a_id')->nullable()->constrained('proc_processos')->nullOnDelete();
            $t->foreignId('anexado_a_id')->nullable()->constrained('proc_processos')->nullOnDelete();
        });
        Schema::create('proc_vinculos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();    // principal
            $t->foreignId('vinculado_id')->constrained('proc_processos')->cascadeOnDelete();   // apenso ou anexado
            $t->string('tipo', 10);                                                            // apenso | anexo
            $t->string('motivo', 1000);
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('desapensado_em')->nullable();
            $t->foreignId('desapensado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->string('motivo_desapensacao', 1000)->nullable();
            $t->index(['processo_id', 'tipo']);
            $t->index('vinculado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_vinculos');
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sobrestado_por');
            $t->dropConstrainedForeignId('apensado_a_id');
            $t->dropConstrainedForeignId('anexado_a_id');
            $t->dropColumn(['sobrestado_em', 'sobrestado_ate', 'sobrestamento_motivo', 'sobrestamento_condicao']);
        });
    }
};
