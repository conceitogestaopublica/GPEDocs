<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setores de verdade em cada etapa do tipo de processo (TR 4.38.2, 4.38.20, 4.38.21):
 * lista de ids do organograma — um, vários ou nenhum (etapa livre). O campo texto
 * setor_destino fica só como referência dos cadastros antigos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_tipo_etapas', function (Blueprint $t) {
            $t->jsonb('unidades')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proc_tipo_etapas', function (Blueprint $t) {
            $t->dropColumn('unidades');
        });
    }
};
