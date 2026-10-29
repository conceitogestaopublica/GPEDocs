<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manifestação do setor na etapa (TR 4.38.14): cada setor que despacha ou resolve a
 * sua parte pode se manifestar deferindo ou indeferindo; a decisão final continua
 * sendo do fluxo principal, que vê as manifestações.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_tramitacoes', function (Blueprint $t) {
            $t->string('manifestacao', 15)->nullable();   // deferido | indeferido
        });
    }

    public function down(): void
    {
        Schema::table('proc_tramitacoes', function (Blueprint $t) {
            $t->dropColumn('manifestacao');
        });
    }
};
