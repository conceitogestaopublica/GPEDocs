<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_circulares', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique(); // CIR-2026/000001
            $table->string('assunto', 500);
            $table->text('conteudo');
            $table->foreignId('remetente_id')->constrained('users');
            $table->string('setor_origem', 150)->nullable();
            $table->string('destino_tipo', 20)->default('todos'); // todos, setores, usuarios
            $table->jsonb('destino_setores')->nullable(); // lista de setores quando destino_tipo='setores'
            $table->string('status', 20)->default('rascunho');
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('arquivado_em')->nullable();
            $table->date('data_arquivamento_auto')->nullable();
            $table->uuid('qr_code_token')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index('remetente_id');
            $table->index('status');
        });

        Schema::create('proc_circular_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circular_id')->constrained('proc_circulares')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('lido')->default(false);
            $table->timestamp('lido_em')->nullable();
            $table->timestamps();

            $table->index(['circular_id', 'usuario_id']);
        });

        Schema::create('proc_circular_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circular_id')->constrained('proc_circulares')->cascadeOnDelete();
            $table->string('nome', 255);
            $table->string('arquivo_path', 500);
            $table->bigInteger('tamanho');
            $table->string('mime_type', 100);
            $table->foreignId('enviado_por')->constrained('users');
            $table->timestamps();

            $table->index('circular_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_circular_anexos');
        Schema::dropIfExists('proc_circular_destinatarios');
        Schema::dropIfExists('proc_circulares');
    }
};
