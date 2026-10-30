<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manifestação pelo portal (TR 4.33.3, 4.33.8, 4.33.27): o tipo e a identificação escolhidos
 * pelo cidadão vão para o processo; a chave de acesso deixa quem é anônimo acompanhar pelo
 * código sem login (o código sozinho é sequencial e daria para adivinhar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->string('manifestacao_tipo', 15)->nullable();
            $t->string('identificacao', 10)->nullable();     // aberta | sigilosa | anonima
            $t->string('chave_acesso', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_solicitacoes', fn (Blueprint $t) => $t->dropColumn(['manifestacao_tipo', 'identificacao', 'chave_acesso']));
    }
};
