<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prazos e atividades lançados à mão no processo, além do prazo automático da etapa
 * (TR 4.13.1, 4.13.2, 4.13.6–4.13.8, 4.32.16, 4.32.17, 4.38.16): vários por processo,
 * para todos, para um setor ou só para quem criou; realizar, editar e excluir ficam no
 * histórico (excluído fica guardado); alerta individual N dias antes, no sistema ou
 * também por e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_prazos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos', indexName: 'proc_prazos_x_proc_processos_X_processo_id');
            $t->string('titulo', 150);
            $t->text('descricao')->nullable();
            $t->timestamp('vence_em');
            $t->string('escopo', 10)->default('todos');            // todos | setor | pessoal
            $t->foreignId('unidade_id')->nullable()->constrained('ug_organograma', indexName: 'proc_prazos_x_ug_organograma_X_unidade_id');
            $t->foreignId('criado_por')->constrained('users', indexName: 'proc_prazos_x_users_X_criado_por');
            $t->timestamp('concluido_em')->nullable();
            $t->foreignId('concluido_por')->nullable()->constrained('users', indexName: 'proc_prazos_x_users_X_concluido_por');
            $t->unsignedSmallInteger('alerta_dias')->nullable();    // avisar N dias antes
            $t->boolean('alerta_email')->default(false);            // além do aviso no sistema
            $t->timestamp('alertado_em')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['processo_id', 'concluido_em']);
            $t->index(['vence_em', 'concluido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_prazos');
    }
};
