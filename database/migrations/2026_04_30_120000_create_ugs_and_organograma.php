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
            $table->foreignId('logradouro_id')->nullable()->constrained('logradouro', indexName: 'ugs_x_logradouro_X_logradouro_id');
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('site')->nullable();
            $table->string('email_institucional')->unique();
            $table->string('telefone', 50)->nullable();
            $table->string('nivel_1_label', 60)->default('Órgão');
            $table->string('nivel_2_label', 60)->default('Unidade');
            $table->string('nivel_3_label', 60)->default('Setor');
            $table->boolean('ativo')->default(true);
            $table->boolean('banner_ativo')->default(true);
            $table->string('brasao_path', 255)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('ativo');
        });

        Schema::create('ug_organograma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ug_id')->constrained('ugs', indexName: 'ug_organograma_x_ugs_X_ug_id');
            $table->foreignId('parent_id')->nullable()->constrained('ug_organograma', indexName: 'ug_organograma_x_ug_organograma_X_parent_id');
            $table->tinyInteger('nivel'); // 1, 2, 3
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 200);
            $table->boolean('ativo')->default(true);
            // Quando endereco_proprio = false, o no herda o endereco da UG.
            // Quando true, usa logradouro_id/numero/complemento proprios.
            $table->boolean('endereco_proprio')->default(false);
            $table->foreignId('logradouro_id')->nullable()
                ->constrained('logradouro', indexName: 'ug_organograma_x_logradouro_X_logradouro_id');
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->timestamps();

            $table->index(['ug_id', 'nivel']);
            $table->index(['ug_id', 'parent_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('tipo', 10)->default('interno')->after('cpf');
            // interno: vinculado a unidade do organograma
            // externo: cadastrado pelo Portal de Servicos, sem unidade

            $table->foreignId('ug_id')->nullable()->after('tipo')
                ->constrained('ugs', indexName: 'users_x_ugs_X_ug_id');

            $table->foreignId('unidade_id')->nullable()->after('ug_id')
                ->constrained('ug_organograma', indexName: 'users_x_ug_organograma_X_unidade_id');

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_x_ugs_X_ug_id');
            $table->dropForeign('users_x_ug_organograma_X_unidade_id');
            $table->dropColumn(['tipo', 'ug_id', 'unidade_id']);
        });

        Schema::dropIfExists('ug_organograma');
        Schema::dropIfExists('ugs');
    }
};
