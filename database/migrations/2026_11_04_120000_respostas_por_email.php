<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resposta por e-mail vira despacho (TR 4.7.23, 4.8.8–4.8.9, 4.47.3): cada e-mail ligado a um
 * documento sai com um endereço de resposta próprio (resposta+código@domínio). O código diz
 * o documento e de quem se espera a resposta; cada e-mail recebido fica registrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_respostas_email', function (Blueprint $t) {
            $t->id();
            $t->string('token', 32)->unique();
            $t->string('referencia_tipo', 30);           // processo | memorando | oficio | solicitacao
            $t->unsignedBigInteger('referencia_id');
            $t->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();   // servidor que responde
            $t->string('email_esperado', 255);
            $t->timestamp('expira_em')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['referencia_tipo', 'referencia_id', 'email_esperado'], 'ged_respostas_email_ref');
        });
        Schema::create('ged_emails_recebidos', function (Blueprint $t) {
            $t->id();
            $t->string('de', 255);
            $t->string('para', 500);
            $t->string('assunto', 300)->nullable();
            $t->string('token', 32)->nullable();
            $t->string('resultado', 20);                  // registrado | recusado | sem_destino
            $t->string('motivo', 300)->nullable();
            $t->string('referencia_tipo', 30)->nullable();
            $t->unsignedBigInteger('referencia_id')->nullable();
            $t->unsignedSmallInteger('anexos')->default(0);
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_emails_recebidos');
        Schema::dropIfExists('ged_respostas_email');
    }
};
