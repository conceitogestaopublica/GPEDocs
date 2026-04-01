<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ged_tipos_documentais
        Schema::create('ged_tipos_documentais', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->jsonb('schema_metadados')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // 2. ged_pastas
        Schema::create('ged_pastas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->text('descricao')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('ged_pastas')->nullOnDelete();
            $table->text('path');
            $table->foreignId('criado_por')->constrained('users');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('path');
        });

        // 3. ged_documentos
        Schema::create('ged_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->text('descricao')->nullable();
            $table->foreignId('tipo_documental_id')->nullable()->constrained('ged_tipos_documentais')->nullOnDelete();
            $table->foreignId('pasta_id')->nullable()->constrained('ged_pastas')->nullOnDelete();
            $table->integer('versao_atual')->default(1);
            $table->bigInteger('tamanho');
            $table->string('mime_type', 100);
            $table->foreignId('autor_id')->constrained('users');
            $table->string('status', 20)->default('rascunho');
            $table->string('classificacao', 20)->default('publico');
            $table->text('ocr_texto')->nullable();
            $table->foreignId('check_out_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('check_out_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo_documental_id');
            $table->index('pasta_id');
            $table->index('autor_id');
            $table->index('status');
        });

        // 4. ged_versoes
        Schema::create('ged_versoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ged_documentos')->cascadeOnDelete();
            $table->integer('versao');
            $table->string('arquivo_path', 500);
            $table->bigInteger('tamanho');
            $table->string('hash_sha256', 64)->nullable();
            $table->foreignId('autor_id')->constrained('users');
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->unique(['documento_id', 'versao']);
        });

        // 5. ged_metadados
        Schema::create('ged_metadados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ged_documentos')->cascadeOnDelete();
            $table->string('chave', 100);
            $table->text('valor')->nullable();
            $table->timestamps();

            $table->index(['documento_id', 'chave']);
        });

        // 6. ged_tags
        Schema::create('ged_tags', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50)->unique();
            $table->string('cor', 7)->nullable();
            $table->timestamps();
        });

        // 7. ged_documento_tags
        Schema::create('ged_documento_tags', function (Blueprint $table) {
            $table->foreignId('documento_id')->constrained('ged_documentos')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('ged_tags')->cascadeOnDelete();

            $table->primary(['documento_id', 'tag_id']);
        });

        // 8. ged_compartilhamentos
        Schema::create('ged_compartilhamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ged_documentos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('permissao', 20)->default('visualizar');
            $table->foreignId('criado_por')->constrained('users');
            $table->timestamps();

            $table->unique(['documento_id', 'usuario_id']);
        });

        // 9. ged_fluxos
        Schema::create('ged_fluxos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->jsonb('definicao');
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->constrained('users');
            $table->timestamps();
        });

        // 10. ged_fluxo_instancias
        Schema::create('ged_fluxo_instancias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fluxo_id')->constrained('ged_fluxos');
            $table->foreignId('documento_id')->constrained('ged_documentos');
            $table->string('status', 20)->default('pendente');
            $table->string('etapa_atual', 100)->nullable();
            $table->foreignId('iniciado_por')->constrained('users');
            $table->timestamps();
        });

        // 11. ged_fluxo_etapas
        Schema::create('ged_fluxo_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instancia_id')->constrained('ged_fluxo_instancias')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('tipo', 20);
            $table->integer('ordem')->default(0);
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pendente');
            $table->timestamp('prazo')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();
        });

        // 12. ged_audit_logs
        Schema::create('ged_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->nullable()->constrained('ged_documentos')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 50);
            $table->jsonb('detalhes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // 13. ged_buscas_salvas
        Schema::create('ged_buscas_salvas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->jsonb('filtros');
            $table->timestamps();
        });

        // 14. ged_roles
        Schema::create('ged_roles', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50)->unique();
            $table->text('descricao')->nullable();
            $table->timestamps();
        });

        // 15. ged_permissions
        Schema::create('ged_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->text('descricao')->nullable();
            $table->timestamps();
        });

        // 16. ged_role_permissions
        Schema::create('ged_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('ged_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('ged_permissions')->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        // 17. ged_user_roles
        Schema::create('ged_user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('ged_roles')->cascadeOnDelete();

            $table->primary(['user_id', 'role_id']);
        });

        // 18. ged_notificacoes
        Schema::create('ged_notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 50);
            $table->string('titulo', 255);
            $table->text('mensagem')->nullable();
            $table->string('referencia_tipo', 50)->nullable();
            $table->bigInteger('referencia_id')->nullable();
            $table->boolean('lida')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_notificacoes');
        Schema::dropIfExists('ged_user_roles');
        Schema::dropIfExists('ged_role_permissions');
        Schema::dropIfExists('ged_permissions');
        Schema::dropIfExists('ged_roles');
        Schema::dropIfExists('ged_buscas_salvas');
        Schema::dropIfExists('ged_audit_logs');
        Schema::dropIfExists('ged_fluxo_etapas');
        Schema::dropIfExists('ged_fluxo_instancias');
        Schema::dropIfExists('ged_fluxos');
        Schema::dropIfExists('ged_compartilhamentos');
        Schema::dropIfExists('ged_documento_tags');
        Schema::dropIfExists('ged_tags');
        Schema::dropIfExists('ged_metadados');
        Schema::dropIfExists('ged_versoes');
        Schema::dropIfExists('ged_documentos');
        Schema::dropIfExists('ged_pastas');
        Schema::dropIfExists('ged_tipos_documentais');
    }
};
