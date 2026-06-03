<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de referencia para endereco normalizado:
 *
 *   ufs -> municipios -> bairros -> logradouros
 *
 * Cada nivel guarda apenas seus proprios dados; o que e comum sobe na cadeia.
 * `last_user` aponta para o ultimo usuario que modificou o registro (auditoria
 * minima — nullable para permitir seeders).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ufs', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50);
            $table->timestamps();
            $table->foreignId('last_user')->nullable()->constrained('users', indexName: 'ufs_x_users_X_last_user');
        });

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->foreignId('uf_id')->constrained('ufs', indexName: 'municipios_x_ufs_X_uf_id');
            $table->string('ibge_id', 10)->nullable()->unique();
            $table->timestamps();
            $table->foreignId('last_user')->nullable()->constrained('users', indexName: 'municipios_x_users_X_last_user');

            $table->index(['uf_id', 'nome']);
        });

        Schema::create('bairros', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->foreignId('municipio_id')->constrained('municipios', indexName: 'bairros_x_municipios_X_municipio_id');
            $table->timestamps();
            $table->foreignId('last_user')->nullable()->constrained('users', indexName: 'bairros_x_users_X_last_user');

            $table->index(['municipio_id', 'nome']);
        });

        Schema::create('logradouros', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 200);
            $table->string('cep', 9)->nullable();
            $table->foreignId('bairro_id')->constrained('bairros', indexName: 'logradouros_x_bairros_X_bairro_id');
            $table->timestamps();
            $table->foreignId('last_user')->nullable()->constrained('users', indexName: 'logradouros_x_users_X_last_user');

            $table->index(['bairro_id', 'nome']);
            $table->index('cep');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logradouros');
        Schema::dropIfExists('bairros');
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('ufs');
    }
};
