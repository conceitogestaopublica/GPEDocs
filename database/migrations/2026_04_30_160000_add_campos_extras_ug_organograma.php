<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ug_organograma', function (Blueprint $table) {
            // Vigencia (datas) — uteis em todos os niveis para historico
            $table->date('dt_inicio')->nullable()->after('ativo');
            $table->date('dt_encerramento')->nullable()->after('dt_inicio');

            // Tipo do orgao (nivel 1) — Prefeitura, Camara, Fundo, Autarquia, etc.
            $table->string('tipo_orgao', 50)->nullable()->after('dt_encerramento');

            // Tipo de fundo (nivel 2 — unidade orcamentaria) — FUNDEB, Tesouro, etc.
            $table->string('tipo_fundo', 50)->nullable()->after('tipo_orgao');

            // Codigo TCE (qualquer nivel) — usado para integracao com TCE/SICOM
            $table->string('codigo_tce', 20)->nullable()->after('tipo_fundo');
            $table->boolean('suprimir_tce')->default(false)->after('codigo_tce');

            // Responsavel pela unidade (mais relevante no nivel 3, mas valido em todos)
            $table->foreignId('responsavel_id')->nullable()->after('suprimir_tce')
                ->constrained('users')->nullOnDelete();

            // Recebe protocolos externos? (nivel 3 — usado pelo Portal de Servicos)
            $table->boolean('protocolo_externo')->default(false)->after('responsavel_id');
        });
    }

    public function down(): void
    {
        Schema::table('ug_organograma', function (Blueprint $table) {
            $table->dropForeign(['responsavel_id']);
            $table->dropColumn([
                'dt_inicio',
                'dt_encerramento',
                'tipo_orgao',
                'tipo_fundo',
                'codigo_tce',
                'suprimir_tce',
                'responsavel_id',
                'protocolo_externo',
            ]);
        });
    }
};
