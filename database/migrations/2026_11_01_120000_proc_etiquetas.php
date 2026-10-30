<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas do setor para processo, memorando e ofício (TR 4.19): cada setor cria as
 * suas (cor da letra e do fundo, hierarquia), só os servidores do setor veem e usam,
 * várias por item, aplicadas em lote na caixa e usadas como gaveta/filtro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_etiquetas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('unidade_id')->constrained('ug_organograma', indexName: 'proc_etiquetas_x_ug_organograma_X_unidade_id');
            $t->foreignId('parent_id')->nullable()->constrained('proc_etiquetas', indexName: 'proc_etiquetas_x_proc_etiquetas_X_parent_id');
            $t->string('nome', 60);
            $t->string('cor_texto', 7)->default('#1f2937');
            $t->string('cor_fundo', 7)->default('#e5e7eb');
            $t->foreignId('criado_por')->constrained('users', indexName: 'proc_etiquetas_x_users_X_criado_por');
            $t->timestamps();
            $t->unique(['unidade_id', 'parent_id', 'nome']);
        });

        Schema::create('proc_etiquetados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('etiqueta_id')->constrained('proc_etiquetas', indexName: 'proc_etiquetados_x_proc_etiquetas_X_etiqueta_id')->cascadeOnDelete();
            $t->string('tipo', 20);                   // processo | memorando | oficio
            $t->unsignedBigInteger('item_id');
            $t->foreignId('etiquetado_por')->constrained('users', indexName: 'proc_etiquetados_x_users_X_etiquetado_por');
            $t->timestamps();
            $t->unique(['etiqueta_id', 'tipo', 'item_id']);
            $t->index(['tipo', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_etiquetados');
        Schema::dropIfExists('proc_etiquetas');
    }
};
