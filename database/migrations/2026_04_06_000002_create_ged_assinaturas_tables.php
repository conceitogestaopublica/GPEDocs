<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_solicitacoes_assinatura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ged_documentos', indexName: 'ged_solicitacoes_assinatura_x_ged_documentos_X_documento_id');
            $table->foreignId('solicitante_id')->constrained('users', indexName: 'ged_solicitacoes_assinatura_x_users_X_solicitante_id');
            $table->string('status', 20)->default('pendente'); // pendente, em_andamento, concluida, cancelada
            $table->text('mensagem')->nullable();
            $table->timestamp('prazo')->nullable();
            $table->timestamps();
        });

        Schema::create('ged_assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('ged_solicitacoes_assinatura', indexName: 'ged_assinaturas_x_ged_solicitacoes_assinatura_X_solicitacao_id');
            $table->foreignId('documento_id')->constrained('ged_documentos', indexName: 'ged_assinaturas_x_ged_documentos_X_documento_id');
            $table->foreignId('signatario_id')->constrained('users', indexName: 'ged_assinaturas_x_users_X_signatario_id');
            $table->integer('ordem')->default(0);
            $table->string('status', 20)->default('pendente'); // pendente, assinado, recusado
            $table->string('email_signatario', 255);
            $table->string('cpf_signatario', 14)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('geolocalizacao', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('hash_documento', 64)->nullable();
            $table->foreignId('versao_id')->nullable()->constrained('ged_versoes', indexName: 'ged_assinaturas_x_ged_versoes_X_versao_id');
            $table->text('motivo_recusa')->nullable();
            // Posicao do sigilo no PDF (jsonb com {x, y, page}) — opcional.
            $table->jsonb('signature_position')->nullable();
            $table->timestamp('assinado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_assinaturas');
        Schema::dropIfExists('ged_solicitacoes_assinatura');
    }
};
