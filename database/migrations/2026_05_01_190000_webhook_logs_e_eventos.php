<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logs de webhooks enviados para sistemas externos. Registra TUDO:
 * tentativas, retries, respostas, erros. Util pra debug + reenvio manual.
 *
 * Tambem habilita configurar quais eventos cada sistema quer receber
 * (campo `eventos_assinatura` JSON, default todos):
 *   - assinatura.individual (cada signatario individual assinou)
 *   - assinatura.recusada (signatario recusou)
 *   - assinatura.todas_concluidas (todas as assinaturas concluidas — JA SUPORTADO)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sistema_origem', 50);
            $table->foreignId('documento_id')->constrained('ged_documentos')->cascadeOnDelete();
            $table->string('evento', 50);                  // 'assinatura.todas_concluidas', etc
            $table->string('callback_url', 500);
            $table->json('payload');                        // JSON enviado
            $table->string('signature_header', 100)->nullable(); // sha256=...
            $table->boolean('sucesso')->default(false);
            $table->integer('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('erro')->nullable();
            $table->integer('tentativas')->default(1);     // quantas vezes tentou (HTTP retry)
            $table->integer('duracao_ms')->nullable();
            $table->timestamp('enviado_em');
            $table->timestamps();

            $table->index(['sistema_origem', 'documento_id']);
            $table->index('evento');
            $table->index('sucesso');
        });

        Schema::table('ged_sistemas_integrados', function (Blueprint $table) {
            // Eventos que o sistema quer receber. Null = todos.
            // Exemplo: ["assinatura.todas_concluidas", "assinatura.recusada"]
            $table->json('eventos_assinatura')->nullable()->after('webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('ged_sistemas_integrados', function (Blueprint $table) {
            $table->dropColumn('eventos_assinatura');
        });
        Schema::dropIfExists('ged_webhook_logs');
    }
};
