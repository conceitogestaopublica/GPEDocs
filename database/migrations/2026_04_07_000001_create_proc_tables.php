<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tipos de Processo
        Schema::create('proc_tipos_processo', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->string('sigla', 10); // PA, LIC, CTR, REQ
            $table->string('categoria', 50)->default('administrativo');
            $table->jsonb('schema_formulario')->nullable();
            $table->jsonb('templates_despacho')->nullable();
            $table->integer('sla_padrao_horas')->default(72);
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->constrained('users');
            $table->timestamps();
        });

        // 2. Etapas do Workflow por Tipo
        Schema::create('proc_tipo_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_processo_id')->constrained('proc_tipos_processo')->cascadeOnDelete();
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->integer('ordem');
            $table->string('tipo', 30)->default('analise'); // analise, parecer, aprovacao, assinatura, despacho, arquivamento
            $table->string('setor_destino', 150)->nullable();
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('sla_horas')->nullable();
            $table->text('template_texto')->nullable();
            $table->boolean('obrigatorio')->default(true);
            $table->timestamps();

            $table->index(['tipo_processo_id', 'ordem']);
        });

        // 3. Processos (instancias)
        Schema::create('proc_processos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_protocolo', 30)->unique();
            $table->foreignId('tipo_processo_id')->constrained('proc_tipos_processo');
            $table->string('assunto', 500);
            $table->text('descricao')->nullable();
            $table->jsonb('dados_formulario')->nullable();
            $table->string('requerente_nome', 255)->nullable();
            $table->string('requerente_cpf', 14)->nullable();
            $table->string('requerente_email', 255)->nullable();
            $table->string('requerente_telefone', 20)->nullable();
            $table->string('setor_origem', 150)->nullable();
            $table->unsignedBigInteger('etapa_atual_id')->nullable();
            $table->string('status', 30)->default('aberto');
            $table->string('prioridade', 20)->default('normal');
            $table->foreignId('aberto_por')->constrained('users');
            $table->foreignId('concluido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('concluido_em')->nullable();
            $table->text('observacao_conclusao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo_processo_id');
            $table->index('status');
            $table->index('aberto_por');
        });

        // 4. Tramitacoes (movimentacoes)
        Schema::create('proc_tramitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $table->foreignId('tipo_etapa_id')->nullable()->constrained('proc_tipo_etapas')->nullOnDelete();
            $table->integer('ordem');
            $table->string('setor_origem', 150)->nullable();
            $table->string('setor_destino', 150);
            $table->foreignId('remetente_id')->constrained('users');
            $table->foreignId('destinatario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recebido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pendente');
            $table->text('despacho')->nullable();
            $table->text('parecer')->nullable();
            $table->integer('sla_horas')->nullable();
            $table->timestamp('prazo')->nullable();
            $table->timestamp('recebido_em')->nullable();
            $table->timestamp('despachado_em')->nullable();
            $table->timestamps();

            $table->index(['processo_id', 'ordem']);
            $table->index(['destinatario_id', 'status']);
            $table->index('prazo');
        });

        // FK etapa_atual_id
        Schema::table('proc_processos', function (Blueprint $table) {
            $table->foreign('etapa_atual_id')->references('id')->on('proc_tramitacoes')->nullOnDelete();
        });

        // 5. Anexos
        Schema::create('proc_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $table->foreignId('tramitacao_id')->nullable()->constrained('proc_tramitacoes')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->string('arquivo_path', 500);
            $table->bigInteger('tamanho');
            $table->string('mime_type', 100);
            $table->string('hash_sha256', 64)->nullable();
            $table->foreignId('enviado_por')->constrained('users');
            $table->timestamps();

            $table->index('processo_id');
        });

        // 6. Comentarios
        Schema::create('proc_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $table->foreignId('tramitacao_id')->nullable()->constrained('proc_tramitacoes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->text('texto');
            $table->boolean('interno')->default(false);
            $table->timestamps();

            $table->index('processo_id');
        });

        // 7. Historico (audit trail)
        Schema::create('proc_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 50);
            $table->jsonb('detalhes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['processo_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('proc_processos', function (Blueprint $table) {
            $table->dropForeign(['etapa_atual_id']);
        });
        Schema::dropIfExists('proc_historico');
        Schema::dropIfExists('proc_comentarios');
        Schema::dropIfExists('proc_anexos');
        Schema::dropIfExists('proc_tramitacoes');
        Schema::dropIfExists('proc_processos');
        Schema::dropIfExists('proc_tipo_etapas');
        Schema::dropIfExists('proc_tipos_processo');
    }
};
