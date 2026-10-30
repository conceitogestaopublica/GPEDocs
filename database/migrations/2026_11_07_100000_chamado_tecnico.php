<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chamado técnico (TR 4.35): número de patrimônio do bem, servidor em nome de quem o chamado
 * foi aberto e descrição com formatação e tabelas (o texto simples continua em `descricao`
 * para a busca).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->string('patrimonio', 60)->nullable()->index();
            $t->foreignId('solicitante_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('descricao_html')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('solicitante_user_id');
            $t->dropColumn(['patrimonio', 'descricao_html']);
        });
    }
};
