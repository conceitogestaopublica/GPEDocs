<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ugs', function (Blueprint $table) {
            $table->string('cep', 9)->nullable()->after('cnpj');
            $table->string('logradouro', 200)->nullable()->after('cep');
            $table->string('numero', 20)->nullable()->after('logradouro');
            $table->string('complemento', 100)->nullable()->after('numero');
            $table->string('bairro', 100)->nullable()->after('complemento');
            $table->string('cidade', 100)->nullable()->after('bairro');
            $table->char('uf', 2)->nullable()->after('cidade');
        });

        Schema::table('ug_organograma', function (Blueprint $table) {
            // Quando false, o no herda o endereco da UG. Quando true, usa os
            // campos abaixo. Mantemos os campos sempre nullable para nao
            // forcar preenchimento quando herdando.
            $table->boolean('endereco_proprio')->default(false)->after('ativo');
            $table->string('cep', 9)->nullable()->after('endereco_proprio');
            $table->string('logradouro', 200)->nullable()->after('cep');
            $table->string('numero', 20)->nullable()->after('logradouro');
            $table->string('complemento', 100)->nullable()->after('numero');
            $table->string('bairro', 100)->nullable()->after('complemento');
            $table->string('cidade', 100)->nullable()->after('bairro');
            $table->char('uf', 2)->nullable()->after('cidade');
        });
    }

    public function down(): void
    {
        Schema::table('ug_organograma', function (Blueprint $table) {
            $table->dropColumn(['endereco_proprio', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf']);
        });

        Schema::table('ugs', function (Blueprint $table) {
            $table->dropColumn(['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf']);
        });
    }
};
