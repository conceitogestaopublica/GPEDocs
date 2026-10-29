<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa e avaliação (TR 4.32.21, 4.32.25–27, 4.33.9, 4.33.24): onde fica a demanda
 * (coordenada, endereço e pino), avaliação por estrelas depois da conclusão e a
 * reabertura, que abre uma nova rodada de avaliação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('local_endereco', 500)->nullable();
            $t->unsignedSmallInteger('reaberturas')->default(0);
        });
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->string('localizacao', 12)->default('nao');   // nao | opcional | obrigatoria
            $t->boolean('avaliacao')->default(true);          // pede avaliação ao concluir
        });
        Schema::create('proc_avaliacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->unsignedSmallInteger('rodada')->default(0);  // = reaberturas do processo quando avaliou
            $t->unsignedSmallInteger('nota');
            $t->text('comentario')->nullable();
            $t->string('origem', 10);                         // portal | balcao
            $t->foreignId('cidadao_id')->nullable()->constrained('portal_cidadaos')->nullOnDelete();
            $t->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['processo_id', 'rodada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_avaliacoes');
        Schema::table('proc_tipos_processo', fn (Blueprint $t) => $t->dropColumn(['localizacao', 'avaliacao']));
        Schema::table('proc_processos', fn (Blueprint $t) => $t->dropColumn(['latitude', 'longitude', 'local_endereco', 'reaberturas']));
    }
};
