<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ugs', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nome', 200);
            $table->string('cnpj', 18)->nullable();
            $table->string('nivel_1_label', 60)->default('Órgão');
            $table->string('nivel_2_label', 60)->default('Unidade');
            $table->string('nivel_3_label', 60)->default('Setor');
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('ativo');
        });

        Schema::create('ug_organograma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ug_id')->constrained('ugs')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ug_organograma')->cascadeOnDelete();
            $table->tinyInteger('nivel'); // 1, 2, 3
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 200);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['ug_id', 'nivel']);
            $table->index(['ug_id', 'parent_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('tipo', 10)->default('interno')->after('cpf');
            // interno: vinculado a unidade do organograma
            // externo: cadastrado pelo Portal de Servicos, sem unidade

            $table->foreignId('ug_id')->nullable()->after('tipo')
                ->constrained('ugs')->nullOnDelete();

            $table->foreignId('unidade_id')->nullable()->after('ug_id')
                ->constrained('ug_organograma')->nullOnDelete();

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ug_id']);
            $table->dropForeign(['unidade_id']);
            $table->dropColumn(['tipo', 'ug_id', 'unidade_id']);
        });

        Schema::dropIfExists('ug_organograma');
        Schema::dropIfExists('ugs');
    }
};
