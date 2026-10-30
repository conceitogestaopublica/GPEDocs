<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projeto e o externo (TR 4.48.20, 4.48.28, 4.48.30, 4.48.31): participantes do processo
 * (proprietário, arquiteto, engenheiro, responsável técnico) com convite para autocadastro;
 * o que de cada anexo o externo vê (nunca, sempre, só depois do deferimento); e o registro de
 * quando o externo viu o processo ou baixou um anexo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_participantes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->string('papel', 25);                  // proprietario | arquiteto | engenheiro | responsavel_tecnico | requerente | outro
            $t->string('nome', 200);
            $t->string('cpf_cnpj', 14)->nullable();
            $t->string('registro', 60)->nullable();   // CREA / CAU / ART / RRT
            $t->string('email', 150)->nullable();
            $t->string('telefone', 30)->nullable();
            $t->uuid('convite_token')->nullable()->unique();
            $t->timestamp('convite_enviado_em')->nullable();
            $t->timestamp('confirmado_em')->nullable();
            $t->foreignId('adicionado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('cpf_cnpj');
        });
        Schema::table('proc_anexos', function (Blueprint $t) {
            $t->string('externo', 12)->default('nao');   // nao | sempre | ao_deferir
        });
        Schema::create('proc_acessos_externos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->foreignId('anexo_id')->nullable()->constrained('proc_anexos')->nullOnDelete();
            $t->string('acao', 12);                       // visualizou | baixou
            $t->string('quem', 200)->nullable();          // nome do cidadão ou participante
            $t->string('documento', 14)->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['processo_id', 'acao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_acessos_externos');
        Schema::table('proc_anexos', fn (Blueprint $t) => $t->dropColumn('externo'));
        Schema::dropIfExists('proc_participantes');
    }
};
