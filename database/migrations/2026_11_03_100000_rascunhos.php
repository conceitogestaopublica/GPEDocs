<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rascunho automático do editor (TR 4.16.6–4.16.9): guarda tudo o que está na tela
 * (campos, destinatários, anexos), fica com o setor de quem começou — qualquer um do
 * setor continua — e só um edita por vez (trava que expira se a pessoa sair).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_rascunhos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ug_id')->nullable()->index();
            $t->string('modulo', 20);                                   // memorando | circular | oficio
            $t->string('titulo', 300)->nullable();
            $t->jsonb('dados');
            $t->foreignId('unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();
            $t->foreignId('autor_id')->constrained('users');
            $t->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('travado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('travado_ate')->nullable();
            $t->timestamps();
            $t->index(['unidade_id', 'modulo']);
        });
        Schema::create('ged_rascunho_anexos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rascunho_id')->constrained('ged_rascunhos')->cascadeOnDelete();
            $t->string('nome', 255);
            $t->string('arquivo_path', 500);
            $t->unsignedBigInteger('tamanho')->default(0);
            $t->string('mime_type', 150)->nullable();
            $t->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_rascunho_anexos');
        Schema::dropIfExists('ged_rascunhos');
    }
};
