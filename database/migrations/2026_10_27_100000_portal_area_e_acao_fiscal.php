<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Carta de Serviços do GPE Docs passa a ser a fonte única do Portal do
 * Cidadão (Next.js) — inclusive dos serviços fiscais. Dois campos que só o
 * portal novo tinha:
 *
 * - `portal_categorias_servicos.area`: em qual área de atendimento do portal a
 *   categoria aparece (contribuinte | protocolo | servidor | transparencia).
 * - `portal_servicos.acao_fiscal`: para serviço de autoatendimento no
 *   tributário, qual tela abre (segunda_via, certidao, parcelamento...). Sem
 *   valor, o serviço é de solicitação/processo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_categorias_servicos', function (Blueprint $t) {
            $t->string('area', 40)->nullable()->after('descricao');
        });

        Schema::table('portal_servicos', function (Blueprint $t) {
            $t->string('acao_fiscal', 40)->nullable()->after('tipo_processo_id');
        });
    }

    public function down(): void
    {
        Schema::table('portal_servicos', function (Blueprint $t) {
            $t->dropColumn('acao_fiscal');
        });

        Schema::table('portal_categorias_servicos', function (Blueprint $t) {
            $t->dropColumn('area');
        });
    }
};
