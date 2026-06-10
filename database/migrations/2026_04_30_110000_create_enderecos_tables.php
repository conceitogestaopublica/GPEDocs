<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de referencia para endereco normalizado (singular):
 *
 *   uf -> municipio -> bairro -> logradouro
 *
 * Cada nivel guarda apenas seus proprios dados; o que e comum sobe na cadeia.
 * `last_user` aponta para o ultimo usuario que modificou o registro (auditoria
 * minima — nullable para permitir seeders).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uf', function (Blueprint $table) {
            $table->char('id', 2)->primary();
            $table->string('nome', 50);
            $table->timestamps();
        });

        Schema::create('municipio', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->char('uf_id', 2);
            $table->foreign('uf_id', 'municipio_x_uf_X_uf_id')->references('id')->on('uf');
            $table->string('ibge_id', 10)->nullable()->unique();
            $table->timestamps();

            $table->index(['uf_id', 'nome']);
        });

        Schema::create('bairro', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->foreignId('municipio_id')->constrained('municipio', indexName: 'bairro_x_municipio_X_municipio_id');
            $table->timestamps();

            $table->index(['municipio_id', 'nome']);
        });

        Schema::create('logradouro', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 200);
            $table->string('cep', 9)->nullable();
            $table->foreignId('bairro_id')->constrained('bairro', indexName: 'logradouro_x_bairro_X_bairro_id');
            $table->timestamps();

            $table->index(['bairro_id', 'nome']);
            $table->index('cep');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logradouro');
        Schema::dropIfExists('bairro');
        Schema::dropIfExists('municipio');
        Schema::dropIfExists('uf');
    }
};
