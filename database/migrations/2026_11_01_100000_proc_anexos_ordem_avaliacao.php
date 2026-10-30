<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos do processo (TR 4.14.5, 4.20.3, 4.38.24, 4.38.26): ordem definida por quem
 * trata o processo, avaliação (aprovado/reprovado com motivo) e contagem de downloads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_anexos', function (Blueprint $t) {
            $t->unsignedInteger('ordem')->nullable();
            $t->string('avaliacao', 12)->nullable();          // aprovado | reprovado
            $t->text('avaliacao_motivo')->nullable();
            $t->foreignId('avaliado_por')->nullable()->constrained('users', indexName: 'proc_anexos_x_users_X_avaliado_por');
            $t->timestamp('avaliado_em')->nullable();
            $t->unsignedInteger('downloads')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('proc_anexos', function (Blueprint $t) {
            $t->dropForeign('proc_anexos_x_users_X_avaliado_por');
            $t->dropColumn(['ordem', 'avaliacao', 'avaliacao_motivo', 'avaliado_por', 'avaliado_em', 'downloads']);
        });
    }
};
