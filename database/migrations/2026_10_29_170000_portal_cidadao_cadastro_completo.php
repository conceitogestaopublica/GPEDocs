<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro do cidadão (TR 4.8.5, 4.8.6, 4.8.13, 4.8.15):
 *  - nomes civis anteriores (Provimento 73/CNJ, Lei 14.382/2022) e endereço;
 *  - troca de e-mail pendente até o cidadão confirmar o código no e-mail novo;
 *  - quem protocolou a solicitação como procurador do titular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_cidadaos', function (Blueprint $t) {
            $t->jsonb('nomes_anteriores')->nullable();
            $t->jsonb('endereco')->nullable();          // cep, logradouro, numero, complemento, bairro, municipio, uf
            $t->string('email_novo', 150)->nullable();
            $t->string('email_novo_token', 64)->nullable();
            $t->timestamp('email_novo_expira_em')->nullable();
        });

        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->foreignId('procurador_cidadao_id')->nullable()
                ->constrained('portal_cidadaos', indexName: 'portal_solicitacoes_x_portal_cidadaos_X_procurador_cidadao_id');
        });
    }

    public function down(): void
    {
        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('procurador_cidadao_id');
        });
        Schema::table('portal_cidadaos', function (Blueprint $t) {
            $t->dropColumn(['nomes_anteriores', 'endereco', 'email_novo', 'email_novo_token', 'email_novo_expira_em']);
        });
    }
};
