<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codigo da solicitacao deve ser unico DENTRO de uma UG.
 * Cada UG tem sua propria sequencia (SOL-2026-00001, SOL-2026-00002, etc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->dropUnique('portal_solicitacoes_codigo_unique');
            $t->unique(['ug_id', 'codigo'], 'portal_solicitacoes_ug_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->dropUnique('portal_solicitacoes_ug_codigo_unique');
            $t->unique('codigo', 'portal_solicitacoes_codigo_unique');
        });
    }
};
