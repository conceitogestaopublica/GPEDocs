<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interação na Central (TR 4.23.3, 4.23.9, 4.32.22–23, 4.32.28): setor e sigla de quem
 * interagiu em cada evento, pessoas envolvidas que acompanham a solicitação pela Central e o
 * recebimento de documento físico no processo, com comprovante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_solicitacao_eventos', function (Blueprint $t) {
            $t->string('setor_nome', 200)->nullable();
            $t->string('setor_sigla', 20)->nullable();
        });
        Schema::create('portal_solicitacao_envolvidos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('solicitacao_id')->constrained('portal_solicitacoes')->cascadeOnDelete();
            $t->string('nome', 200);
            $t->string('cpf', 11);
            $t->string('email', 255)->nullable();
            $t->string('papel', 60)->nullable();             // ex.: coproprietário, responsável técnico
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['solicitacao_id', 'cpf']);
            $t->index('cpf');
        });
        Schema::create('proc_recebimentos_fisicos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->string('codigo', 20)->unique();
            $t->text('descricao');
            $t->unsignedSmallInteger('folhas')->nullable();
            $t->string('entregue_por_nome', 200);
            $t->string('entregue_por_documento', 30)->nullable();
            $t->foreignId('recebido_por')->constrained('users');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_recebimentos_fisicos');
        Schema::dropIfExists('portal_solicitacao_envolvidos');
        Schema::table('portal_solicitacao_eventos', fn (Blueprint $t) => $t->dropColumn(['setor_nome', 'setor_sigla']));
    }
};
