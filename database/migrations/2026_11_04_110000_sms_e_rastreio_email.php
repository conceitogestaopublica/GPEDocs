<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS (TR 4.11.1, 4.47.2) e rastreio dos e-mails enviados para fora (TR 4.11.7–4.11.14):
 * cada SMS fica registrado com a situação; cada e-mail externo ganha um código para saber se
 * foi entregue, aberto, clicado ou falhou (com o motivo) e de que dispositivo foi aberto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_sms', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 20);
            $t->string('texto', 480);
            $t->string('status', 12);                  // enviado | falhou | simulado
            $t->string('erro', 300)->nullable();
            $t->string('referencia_tipo', 30)->nullable();
            $t->unsignedBigInteger('referencia_id')->nullable();
            $t->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();   // servidor destinatário
            $t->timestamp('created_at')->useCurrent();
            $t->index(['referencia_tipo', 'referencia_id']);
        });
        Schema::create('ged_emails_rastreados', function (Blueprint $t) {
            $t->id();
            $t->string('token', 40)->unique();
            $t->string('modulo', 20);                  // oficio | mencao | assinatura | cidadao
            $t->string('referencia_tipo', 30)->nullable();
            $t->unsignedBigInteger('referencia_id')->nullable();
            $t->foreignId('remetente_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('destinatario', 255);
            $t->string('assunto', 300)->nullable();
            $t->string('status', 12)->default('enviado');   // enviado | entregue | aberto | clicado | falhou
            $t->timestamp('enviado_em')->nullable();
            $t->timestamp('entregue_em')->nullable();
            $t->timestamp('aberto_em')->nullable();
            $t->timestamp('clicado_em')->nullable();
            $t->string('falha_motivo', 300)->nullable();
            $t->string('dispositivo', 200)->nullable();
            $t->timestamps();
            $t->index(['referencia_tipo', 'referencia_id']);
        });
        Schema::table('proc_oficios', function (Blueprint $t) {
            $t->string('destinatario_celular', 20)->nullable();
            $t->boolean('avisar_sms')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('proc_oficios', fn (Blueprint $t) => $t->dropColumn(['destinatario_celular', 'avisar_sms']));
        Schema::dropIfExists('ged_emails_rastreados');
        Schema::dropIfExists('ged_sms');
    }
};
