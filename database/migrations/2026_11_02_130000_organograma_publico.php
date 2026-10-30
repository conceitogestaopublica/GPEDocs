<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organograma (TR 4.12): sigla e contatos do setor para a lista de telefones e a
 * busca por sigla, e a versão pública que a prefeitura incorpora no site (link
 * com chave própria, que pode ser trocada ou desligada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ug_organograma', function (Blueprint $t) {
            $t->string('sigla', 20)->nullable();
            $t->string('telefone', 30)->nullable();
            $t->string('ramal', 10)->nullable();
            $t->string('email', 150)->nullable();
        });
        Schema::table('ugs', function (Blueprint $t) {
            $t->boolean('organograma_publico')->default(false);
            $t->string('organograma_token', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('ugs', fn (Blueprint $t) => $t->dropColumn(['organograma_publico', 'organograma_token']));
        Schema::table('ug_organograma', fn (Blueprint $t) => $t->dropColumn(['sigla', 'telefone', 'ramal', 'email']));
    }
};
