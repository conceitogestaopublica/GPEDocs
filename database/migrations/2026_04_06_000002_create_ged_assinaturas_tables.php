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
            $table->foreignId('documento_id')->constrained('ged_documentos')->cascadeOnDelete();
            $table->foreignId('solicitante_id')->constrained('users');
            $table->string('status', 20)->default('pendente'); // pendente, em_andamento, concluida, cancelada
            $table->text('mensagem')->nullable();
            $table->timestamp('prazo')->nullable();
            $table->timestamps();
        });

        Schema::create('ged_assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('ged_solicitacoes_assinatura')->cascadeOnDelete();
            $table->foreignId('documento_id')->constrained('ged_documentos')->cascadeOnDelete();
            $table->foreignId('signatario_id')->constrained('users');
            $table->integer('ordem')->default(0);
            $table->string('status', 20)->default('pendente'); // pendente, assinado, recusado
            $table->string('email_signatario', 255);
            $table->string('cpf_signatario', 14)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('geolocalizacao', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('hash_documento', 64)->nullable();
            $table->foreignId('versao_id')->nullable()->constrained('ged_versoes')->nullOnDelete();
            $table->text('motivo_recusa')->nullable();
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
