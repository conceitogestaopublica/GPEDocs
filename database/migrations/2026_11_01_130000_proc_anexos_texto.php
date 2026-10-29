<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Texto dos anexos do processo para a busca (TR 4.15.1, 4.15.4) — extraído ou por OCR, na fila. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_anexos', function (Blueprint $t) {
            $t->text('texto_indexado')->nullable();
            $t->timestamp('indexado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proc_anexos', function (Blueprint $t) {
            $t->dropColumn(['texto_indexado', 'indexado_em']);
        });
    }
};
