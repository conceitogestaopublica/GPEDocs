<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão de PDFs e plantas (TR 4.48.16–4.48.18, 4.48.39, 4.51.5): anotações (texto, formas,
 * marca-texto, caneta, selos) e medições calibradas pela escala, guardadas sobre o anexo; ao
 * concluir (aceito/recusado) gera um novo anexo com as anotações, preservando o original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_anexo_revisoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('anexo_id')->constrained('proc_anexos')->cascadeOnDelete();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->foreignId('autor_id')->constrained('users');
            $t->jsonb('anotacoes')->nullable();           // [{id, pagina, tipo, x, y, w, h, pontos, texto, cor, espessura, selo, mm, mm2}]
            $t->jsonb('escala')->nullable();              // {pagina: metros por mm do papel}
            $t->unsignedTinyInteger('precisao')->default(2);   // casas decimais das medidas (0 a 4)
            $t->string('resultado', 10)->nullable();      // aceito | recusado (null = rascunho)
            $t->text('motivo')->nullable();
            $t->foreignId('anexo_gerado_id')->nullable()->constrained('proc_anexos')->nullOnDelete();
            $t->timestamp('concluida_em')->nullable();
            $t->timestamps();
            $t->index(['anexo_id', 'resultado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_anexo_revisoes');
    }
};
