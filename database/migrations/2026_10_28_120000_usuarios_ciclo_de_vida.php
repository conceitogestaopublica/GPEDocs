<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclo de vida do usuário.
 *
 * - Suspensão em vez de exclusão: o histórico de quem tramitou e assinou fica preservado.
 * - Último acesso (login) e última atividade (para "online agora").
 * - Registro do cadastro: quem cadastrou, de que IP e navegador.
 * - ged_login_logs: cada tentativa de entrada, com sucesso ou não, IP e navegador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('suspenso_em')->nullable();
            $t->unsignedBigInteger('suspenso_por')->nullable();
            $t->string('suspenso_motivo', 500)->nullable();
            $t->timestamp('ultimo_acesso_em')->nullable();
            $t->timestamp('ultima_atividade_em')->nullable();
            $t->unsignedBigInteger('criado_por')->nullable();
            $t->string('criado_ip', 45)->nullable();
            $t->string('criado_user_agent', 500)->nullable();
        });

        Schema::create('ged_login_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('login', 255)->nullable();
            $t->boolean('sucesso');
            $t->string('motivo', 100)->nullable();
            $t->string('origem', 20)->default('senha');
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_login_logs');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['suspenso_em', 'suspenso_por', 'suspenso_motivo', 'ultimo_acesso_em', 'ultima_atividade_em', 'criado_por', 'criado_ip', 'criado_user_agent']);
        });
    }
};
