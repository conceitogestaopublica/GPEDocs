<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcadores do editor gravados em cada documento (TR 4.16.13–4.16.18): menção (@),
 * citação de documento (#) e pedido de assinatura (!). Origem é o documento escrito
 * (memorando, circular, ofício, ou o processo, no despacho); alvo é a pessoa, o contato
 * ou o documento citado. Dá o "citado em", o acesso de quem foi mencionado e o
 * acompanhamento das assinaturas pedidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_marcacoes', function (Blueprint $t) {
            $t->id();
            $t->string('origem_tipo', 20);
            $t->unsignedBigInteger('origem_id');
            $t->string('tipo', 12);                              // mencao | referencia | assinatura
            $t->string('alvo_tipo', 20);                         // usuario | contato | memorando | circular | oficio | processo
            $t->unsignedBigInteger('alvo_id');
            $t->foreignId('documento_id')->nullable()->constrained('ged_documentos')->nullOnDelete();  // PDF levado à assinatura
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['origem_tipo', 'origem_id', 'tipo', 'alvo_tipo', 'alvo_id'], 'ged_marcacoes_unica');
            $t->index(['alvo_tipo', 'alvo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_marcacoes');
    }
};
