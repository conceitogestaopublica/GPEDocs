<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_cidadaos', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 200);
            $t->string('email', 150)->unique();
            $t->string('cpf', 14)->nullable()->index();
            $t->string('telefone', 30)->nullable();
            $t->string('senha');
            $t->timestamp('email_verificado_em')->nullable();
            $t->string('token_verificacao', 64)->nullable();
            $t->boolean('ativo')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('portal_solicitacoes', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 30)->unique();
            $t->foreignId('ug_id')->constrained('ugs')->cascadeOnDelete();
            $t->foreignId('servico_id')->constrained('portal_servicos')->cascadeOnDelete();
            $t->foreignId('cidadao_id')->constrained('portal_cidadaos')->cascadeOnDelete();
            $t->string('status', 20)->default('aberta'); // aberta, em_atendimento, atendida, recusada, cancelada
            $t->text('descricao'); // o que o cidadao escreveu ao solicitar
            $t->string('telefone_contato', 30)->nullable();
            $t->string('email_contato', 150)->nullable();
            $t->foreignId('atendente_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('resposta')->nullable(); // resposta do atendente
            $t->timestamp('respondida_em')->nullable();
            $t->timestamps();
            $t->index(['ug_id', 'status']);
            $t->index(['cidadao_id', 'status']);
        });

        Schema::create('portal_solicitacao_eventos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('solicitacao_id')->constrained('portal_solicitacoes')->cascadeOnDelete();
            $t->string('tipo', 30); // criada, status_alterado, comentario, atendida, recusada
            $t->string('autor_tipo', 20); // cidadao, atendente, sistema
            $t->string('autor_nome')->nullable();
            $t->foreignId('autor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('autor_cidadao_id')->nullable()->constrained('portal_cidadaos')->nullOnDelete();
            $t->string('status_anterior', 20)->nullable();
            $t->string('status_novo', 20)->nullable();
            $t->text('mensagem')->nullable();
            $t->timestamps();
            $t->index('solicitacao_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_solicitacao_eventos');
        Schema::dropIfExists('portal_solicitacoes');
        Schema::dropIfExists('portal_cidadaos');
    }
};
