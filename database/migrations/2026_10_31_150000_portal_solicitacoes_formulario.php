<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulário e documentos exigidos do assunto também pelo Portal (TR 4.38.3–4.38.9,
 * 4.38.18, 4.32.14):
 *  - a solicitação guarda o formulário preenchido (vai para o processo na abertura
 *    automática ou quando o atendente abre depois);
 *  - anexo do processo vindo do portal pode não ter usuário do GED (cidadão sem
 *    cadastro de servidor) — antes esses anexos nem chegavam ao processo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->jsonb('dados_formulario')->nullable();
        });
        Schema::table('proc_anexos', function (Blueprint $t) {
            $t->unsignedBigInteger('enviado_por')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->dropColumn('dados_formulario');
        });
        // enviado_por continua aceitando vazio: anexos do portal sem usuário já gravados não teriam dono
    }
};
