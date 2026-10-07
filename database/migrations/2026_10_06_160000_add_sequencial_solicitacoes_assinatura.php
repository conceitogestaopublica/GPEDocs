<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura em ordem: com `sequencial`, cada signatário só assina depois que todos de
 * ordem menor assinaram. Desligado por padrão — as solicitações existentes e as que
 * chegam pela integração sem o campo continuam em paralelo, como sempre foram.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ged_solicitacoes_assinatura', function (Blueprint $table) {
            $table->boolean('sequencial')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ged_solicitacoes_assinatura', function (Blueprint $table) {
            $table->dropColumn('sequencial');
        });
    }
};
