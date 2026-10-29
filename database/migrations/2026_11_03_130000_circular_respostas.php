<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circular (TR 4.46): prioridade urgente, respostas abertas (todos veem), restritas (só o
 * remetente) ou nenhuma (meramente informativa), setores do organograma (ids), e para cada
 * servidor: arquivar para si e silenciar as respostas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_circulares', function (Blueprint $t) {
            $t->boolean('urgente')->default(false);
            $t->string('respostas', 10)->default('abertas');   // abertas | restritas | nenhuma
            $t->jsonb('destino_unidades')->nullable();          // ids do organograma (destino_setores guarda os nomes)
        });
        Schema::table('proc_circular_destinatarios', function (Blueprint $t) {
            $t->timestamp('arquivado_em')->nullable();
            $t->boolean('silenciado')->default(false);
        });
        Schema::create('proc_circular_respostas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('circular_id')->constrained('proc_circulares')->cascadeOnDelete();
            $t->foreignId('usuario_id')->constrained('users');
            $t->text('conteudo');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_circular_respostas');
        Schema::table('proc_circular_destinatarios', fn (Blueprint $t) => $t->dropColumn(['arquivado_em', 'silenciado']));
        Schema::table('proc_circulares', fn (Blueprint $t) => $t->dropColumn(['urgente', 'respostas', 'destino_unidades']));
    }
};
