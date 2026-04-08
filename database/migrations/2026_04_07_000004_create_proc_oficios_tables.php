<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_oficios', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique(); // OF-2026/000001
            $table->string('assunto', 500);
            $table->text('conteudo');
            $table->foreignId('remetente_id')->constrained('users');
            $table->string('setor_origem', 150)->nullable();
            // Destinatario externo
            $table->string('destinatario_nome', 255);
            $table->string('destinatario_email', 255);
            $table->string('destinatario_cargo', 150)->nullable();
            $table->string('destinatario_orgao', 255)->nullable();
            $table->string('status', 20)->default('rascunho'); // rascunho, enviado, entregue, lido, respondido, arquivado
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('entregue_em')->nullable();
            $table->timestamp('lido_em')->nullable();
            $table->timestamp('arquivado_em')->nullable();
            $table->string('rastreio_token', 64)->nullable()->unique(); // token para rastrear abertura
            $table->uuid('qr_code_token')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index('remetente_id');
            $table->index('status');
        });

        Schema::create('proc_oficio_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oficio_id')->constrained('proc_oficios')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->string('arquivo_path', 500);
            $table->bigInteger('tamanho');
            $table->string('mime_type', 100);
            $table->boolean('solicitar_assinatura')->default(false);
            $table->foreignId('enviado_por')->constrained('users');
            $table->timestamps();

            $table->index('oficio_id');
        });

        Schema::create('proc_oficio_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oficio_id')->constrained('proc_oficios')->cascadeOnDelete();
            $table->string('respondente_nome', 255)->nullable();
            $table->string('respondente_email', 255)->nullable();
            $table->text('conteudo');
            $table->boolean('externo')->default(false); // true = resposta do destinatario externo
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('oficio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_oficio_respostas');
        Schema::dropIfExists('proc_oficio_anexos');
        Schema::dropIfExists('proc_oficios');
    }
};
