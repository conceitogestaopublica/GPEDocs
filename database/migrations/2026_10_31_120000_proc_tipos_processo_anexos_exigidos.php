<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos exigidos por assunto (TR 4.32.14, 4.38.18):
 * [{nome, obrigatorio, extensoes: "pdf,jpg"}]. A abertura não passa sem os obrigatórios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->jsonb('anexos_exigidos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->dropColumn('anexos_exigidos');
        });
    }
};
