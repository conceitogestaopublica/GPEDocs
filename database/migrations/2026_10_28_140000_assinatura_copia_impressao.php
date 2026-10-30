<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dois arquivos por documento assinado: arquivo_assinado_path continua sendo o
 * original com as assinaturas (incrementais); arquivo_impressao_path é a cópia
 * com carimbos, tarja e termo, refeita a cada assinatura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->string('arquivo_impressao_path', 500)->nullable()->after('arquivo_assinado_path');
        });
    }

    public function down(): void
    {
        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->dropColumn('arquivo_impressao_path');
        });
    }
};
