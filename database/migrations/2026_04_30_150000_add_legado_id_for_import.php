<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ugs', function (Blueprint $table) {
            $table->unsignedBigInteger('legado_orgao_id')->nullable()->after('codigo')->index();
        });
        Schema::table('ug_organograma', function (Blueprint $table) {
            $table->unsignedBigInteger('legado_id')->nullable()->after('codigo')->index();
            // Tipo de origem: 'unidade' (legado) | 'departamento' | 'setor'
            $table->string('legado_tipo', 20)->nullable()->after('legado_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('legado_usuario_id')->nullable()->after('unidade_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('legado_usuario_id');
        });
        Schema::table('ug_organograma', function (Blueprint $table) {
            $table->dropColumn(['legado_id', 'legado_tipo']);
        });
        Schema::table('ugs', function (Blueprint $table) {
            $table->dropColumn('legado_orgao_id');
        });
    }
};
