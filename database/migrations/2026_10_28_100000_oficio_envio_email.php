<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envio do ofício eletrônico por e-mail, com rastreio.
 *
 * modo_envio: escolhido na criação ("eletronico" manda e-mail; "fisico" só registra no livro) e até
 * agora não era gravado. email_status: null (sem envio), "enviado" (aceito pelo servidor de e-mail)
 * ou "falhou" (motivo em email_falha_motivo). email_aberto_em: imagem de rastreio carregada.
 * O acesso ao link continua em lido_em.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_oficios', function (Blueprint $t) {
            $t->string('modo_envio', 20)->nullable();
            $t->string('email_status', 20)->nullable();
            $t->timestamp('email_enviado_em')->nullable();
            $t->timestamp('email_aberto_em')->nullable();
            $t->text('email_falha_motivo')->nullable();
            $t->unsignedSmallInteger('email_tentativas')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('proc_oficios', function (Blueprint $t) {
            $t->dropColumn(['modo_envio', 'email_status', 'email_enviado_em', 'email_aberto_em', 'email_falha_motivo', 'email_tentativas']);
        });
    }
};
