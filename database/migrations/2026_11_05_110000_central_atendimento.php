<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central de Atendimento (TR 4.23): registro de cada verificação de autenticidade por código
 * (4.23.14–4.23.15) e os textos do portal editados no GED — política de privacidade (4.23.5) e
 * informações sobre pedidos de acesso à informação (4.23.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_verificacoes', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 120);
            $t->string('origem', 10);                    // portal | web
            $t->boolean('encontrado');
            $t->string('tipo', 20)->nullable();          // assinatura | documento | certidao | memorando | circular | oficio
            $t->unsignedBigInteger('referencia_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 300)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tipo', 'referencia_id']);
        });
        Schema::create('ged_portal_textos', function (Blueprint $t) {
            $t->id();
            $t->string('chave', 40)->unique();           // politica_privacidade | acesso_informacao
            $t->string('titulo', 200);
            $t->text('texto')->nullable();
            $t->string('url', 500)->nullable();
            $t->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        DB::table('ged_portal_textos')->insert([
            ['chave' => 'politica_privacidade', 'titulo' => 'Política de Privacidade', 'created_at' => now(), 'updated_at' => now(),
                'texto' => "Os dados pessoais informados neste portal são usados apenas para prestar o serviço pedido e responder às suas solicitações, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018). Eles não são vendidos nem cedidos a terceiros, e você pode pedir acesso, correção ou exclusão pelo canal de atendimento."],
            ['chave' => 'acesso_informacao', 'titulo' => 'Acesso à Informação (LAI)', 'created_at' => now(), 'updated_at' => now(),
                'texto' => "Qualquer pessoa pode pedir informações públicas ao município, sem precisar dizer o motivo (Lei 12.527/2011). O prazo de resposta é de até 20 dias, prorrogável por mais 10 com justificativa. Se o pedido for negado, cabe recurso em até 10 dias."],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_portal_textos');
        Schema::dropIfExists('ged_verificacoes');
    }
};
