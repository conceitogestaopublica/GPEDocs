<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa retirada de um tipo de processo que já tem tramitações não pode ser apagada
 * (a tramitação guarda tipo_etapa_id): passa a ficar inativa, fora do encadeamento,
 * e o histórico continua apontando para ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_tipo_etapas', function (Blueprint $table) {
            $table->boolean('ativo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('proc_tipo_etapas', function (Blueprint $table) {
            $table->dropColumn('ativo');
        });
    }
};
