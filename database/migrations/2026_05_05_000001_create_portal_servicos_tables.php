<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_categorias_servicos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs')->cascadeOnDelete();
            $t->string('nome', 120);
            $t->string('slug', 140);
            $t->string('icone', 60)->nullable();
            $t->string('cor', 20)->nullable();
            $t->text('descricao')->nullable();
            $t->integer('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['ug_id', 'slug']);
            $t->index(['ug_id', 'ativo', 'ordem']);
        });

        Schema::create('portal_servicos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs')->cascadeOnDelete();
            $t->foreignId('categoria_id')->nullable()->constrained('portal_categorias_servicos')->nullOnDelete();
            $t->string('titulo');
            $t->string('slug', 200);
            $t->string('publico_alvo', 20)->default('cidadao');
            $t->text('descricao_curta')->nullable();
            $t->longText('descricao_completa')->nullable();
            $t->text('requisitos')->nullable();
            $t->json('documentos_necessarios')->nullable();
            $t->string('prazo_entrega')->nullable();
            $t->string('custo')->nullable();
            $t->json('canais')->nullable();
            $t->string('orgao_responsavel')->nullable();
            $t->text('legislacao')->nullable();
            $t->json('palavras_chave')->nullable();
            $t->string('icone', 60)->nullable();
            $t->boolean('publicado')->default(false);
            $t->unsignedInteger('visualizacoes')->default(0);
            $t->integer('ordem')->default(0);
            $t->timestamps();
            $t->unique(['ug_id', 'slug']);
            $t->index(['ug_id', 'publicado', 'categoria_id']);
            $t->index(['ug_id', 'publicado', 'publico_alvo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_servicos');
        Schema::dropIfExists('portal_categorias_servicos');
    }
};
