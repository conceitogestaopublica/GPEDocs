<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Memorandos
        Schema::create('proc_memorandos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique(); // MEM-2026/000001
            $table->string('assunto', 500);
            $table->text('conteudo');
            $table->foreignId('remetente_id')->constrained('users');
            $table->string('setor_origem', 150)->nullable();
            $table->boolean('confidencial')->default(false);
            $table->string('status', 20)->default('rascunho'); // rascunho, enviado, arquivado
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('arquivado_em')->nullable();
            $table->date('data_arquivamento_auto')->nullable();
            $table->uuid('qr_code_token')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index('remetente_id');
            $table->index('status');
        });

        // Destinatarios do memorando
        Schema::create('proc_memorando_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorando_id')->constrained('proc_memorandos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('setor_destino', 150)->nullable();
            $table->boolean('lido')->default(false);
            $table->timestamp('lido_em')->nullable();
            $table->timestamps();

            $table->index(['memorando_id', 'usuario_id']);
        });

        // Anexos do memorando
        Schema::create('proc_memorando_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorando_id')->constrained('proc_memorandos')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->string('arquivo_path', 500);
            $table->bigInteger('tamanho');
            $table->string('mime_type', 100);
            $table->foreignId('enviado_por')->constrained('users');
            $table->timestamps();

            $table->index('memorando_id');
        });

        // Respostas/tramitacoes do memorando
        Schema::create('proc_memorando_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorando_id')->constrained('proc_memorandos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->text('conteudo');
            $table->timestamps();

            $table->index('memorando_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_memorando_respostas');
        Schema::dropIfExists('proc_memorando_anexos');
        Schema::dropIfExists('proc_memorando_destinatarios');
        Schema::dropIfExists('proc_memorandos');
    }
};
