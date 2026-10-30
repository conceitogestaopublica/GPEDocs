<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Árvore do processo (TR 4.9.16, 4.38.27–4.38.42): PDF compilado e paginado, gerado em
 * segundo plano. Cada geração fica guardada (várias árvores do mesmo processo; a última
 * de cada configuração para baixar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_arvores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos', indexName: 'proc_arvores_x_proc_processos_X_processo_id');
            $t->string('configuracao', 15);            // completa | assinaturas | personalizada | comprimida | fracionada
            $t->jsonb('opcoes')->nullable();           // personalizada: o que entra
            $t->string('status', 10)->default('na_fila');   // na_fila | gerando | pronta | erro
            $t->string('arquivo_path')->nullable();
            $t->string('arquivo_tipo', 10)->nullable();     // pdf | zip (fracionada)
            $t->unsignedBigInteger('tamanho')->nullable();
            $t->unsignedInteger('paginas')->nullable();
            $t->unsignedSmallInteger('partes')->nullable();
            $t->text('observacao')->nullable();        // anexos não convertidos, páginas acima de 500 KB…
            $t->text('erro')->nullable();
            $t->foreignId('solicitado_por')->constrained('users', indexName: 'proc_arvores_x_users_X_solicitado_por');
            $t->timestamp('gerada_em')->nullable();
            $t->timestamps();
            $t->index(['processo_id', 'configuracao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_arvores');
    }
};
