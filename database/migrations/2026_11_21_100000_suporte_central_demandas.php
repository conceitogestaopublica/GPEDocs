<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat de suporte ligado à Central de Demandas da Conceito: credenciais por tenant (token e segredo
 * cifrados), onde o servidor estava ao abrir (módulo, tela, UG), o vínculo com a demanda da Central,
 * mensagens da equipe Conceito e da resposta automática, e os eventos já processados (sem repetir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sup_config', function (Blueprint $t) {
            $t->boolean('central_ativo')->default(false);
            $t->string('central_url', 300)->nullable();        // https://demandas…/api/v1
            $t->text('central_token')->nullable();              // cifrado (Crypt)
            $t->text('central_segredo')->nullable();            // cifrado (Crypt)
        });
        Schema::table('sup_atendimentos', function (Blueprint $t) {
            $t->string('modulo', 30)->nullable();               // chave do MODULO_CONFIG: ged | gepsp | configuracoes
            $t->string('submodulo', 120)->nullable();           // grupo do menu da tela aberta
            $t->string('tela', 200)->nullable();
            $t->string('url_tela', 500)->nullable();
            $t->foreignId('ug_id')->nullable()->constrained('ugs')->nullOnDelete();
            $t->boolean('via_central')->default(false);         // aberto com a integração ligada: quem atende é a Central
            $t->unsignedBigInteger('central_id')->nullable();   // demanda_id na Central
            $t->string('central_numero', 30)->nullable();       // DEM-2026/000123
            $t->timestamp('central_enviado_em')->nullable();
            $t->string('central_erro', 500)->nullable();        // última falha de envio (para o administrador)
        });
        Schema::table('sup_mensagens', function (Blueprint $t) {
            $t->string('autor_externo', 150)->nullable();       // "Ana Souza (Conceito)"
            $t->boolean('automatica')->default(false);          // resposta automática da Central
            $t->timestamp('responder_ate')->nullable();         // prazo do "Isso resolveu?"
            $t->string('retorno', 15)->nullable();              // resolveu | nao_resolveu
            $t->timestamp('central_enviada_em')->nullable();    // mensagem do servidor já entregue à Central
        });
        Schema::create('sup_central_eventos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_evento')->unique();
            $t->string('evento', 30);
            $t->foreignId('atendimento_id')->nullable()->constrained('sup_atendimentos')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sup_central_eventos');
        Schema::table('sup_mensagens', fn (Blueprint $t) => $t->dropColumn(['autor_externo', 'automatica', 'responder_ate', 'retorno', 'central_enviada_em']));
        Schema::table('sup_atendimentos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('ug_id');
            $t->dropColumn(['modulo', 'submodulo', 'tela', 'url_tela', 'via_central', 'central_id', 'central_numero', 'central_enviado_em', 'central_erro']);
        });
        Schema::table('sup_config', fn (Blueprint $t) => $t->dropColumn(['central_ativo', 'central_url', 'central_token', 'central_segredo']));
    }
};
