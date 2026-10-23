<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexo de processo enviado para assinatura vira documento do GED (o arquivo assinado fica
 * sempre no GED) — o vínculo leva da lista de anexos ao documento e às assinaturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_anexos', function (Blueprint $table) {
            $table->foreignId('documento_id')->nullable()
                ->constrained('ged_documentos', indexName: 'proc_anexos_x_ged_documentos_X_documento_id');
        });
    }

    public function down(): void
    {
        Schema::table('proc_anexos', function (Blueprint $table) {
            $table->dropForeign('proc_anexos_x_ged_documentos_X_documento_id');
            $table->dropColumn('documento_id');
        });
    }
};
