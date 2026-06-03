<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_oficio_modelos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ug_id')->nullable()->constrained('ugs', indexName: 'proc_oficio_modelos_x_ugs_X_ug_id');
            $table->string('nome', 200);
            $table->string('categoria', 80)->nullable();
            $table->text('descricao')->nullable();
            $table->text('conteudo');
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->constrained('users', indexName: 'proc_oficio_modelos_x_users_X_criado_por');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ug_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_oficio_modelos');
    }
};
