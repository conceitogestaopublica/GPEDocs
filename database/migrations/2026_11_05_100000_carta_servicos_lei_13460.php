<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carta de Serviços completa (Lei 13.460/2017, TR 4.30): características, etapas do
 * processamento, prioridades de atendimento, tempo de espera, prazo em dias, forma de
 * prestação, locais e formas de manifestação, comunicação, consulta do andamento e os perfis
 * de quem pode pedir (vários por serviço).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_servicos', function (Blueprint $t) {
            $t->text('caracteristicas')->nullable();
            $t->jsonb('etapas')->nullable();                   // [{titulo, descricao}]
            $t->jsonb('perfis')->nullable();                   // chaves de Servico::PERFIS
            $t->text('prioridades')->nullable();
            $t->string('tempo_espera', 200)->nullable();
            $t->unsignedSmallInteger('prazo_dias')->nullable();
            $t->text('forma_prestacao')->nullable();
            $t->text('locais_manifestacao')->nullable();
            $t->text('mecanismos_comunicacao')->nullable();
            $t->text('procedimento_manifestacoes')->nullable();
            $t->text('mecanismos_consulta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_servicos', fn (Blueprint $t) => $t->dropColumn(['caracteristicas', 'etapas', 'perfis', 'prioridades', 'tempo_espera',
            'prazo_dias', 'forma_prestacao', 'locais_manifestacao', 'mecanismos_comunicacao', 'procedimento_manifestacoes', 'mecanismos_consulta']));
    }
};
