<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `portal_cidadaos.excluido_em` — o titular excluiu a conta pelo Portal do Cidadão (ou pelo app).
 *
 * Cadastro sem nenhum registro ligado é apagado de vez; com registro (solicitação, assinatura,
 * recurso do e-SIC, inscrição, avaliação), fica só o nome e o CPF/CNPJ, como autor desses
 * registros, e esta data. Sem login, sem contato, e invisível para as buscas por documento e
 * e-mail: a pessoa pode fazer um cadastro novo, que nasce em outra linha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('portal_cidadaos', 'excluido_em')) {
            Schema::table('portal_cidadaos', function (Blueprint $t) {
                $t->timestamp('excluido_em')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('portal_cidadaos', 'excluido_em')) {
            Schema::table('portal_cidadaos', function (Blueprint $t) {
                $t->dropColumn('excluido_em');
            });
        }
    }
};
