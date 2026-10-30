<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Protocolo legislativo (TR 4.37): vereadores, proposição (requerimento, indicação, pedido de
 * informação, moção) com número e o prazo de resposta do assunto, que vai para prazo_legal_em.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_vereadores', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 150);
            $t->string('partido', 30)->nullable();
            $t->string('email', 150)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->unsignedSmallInteger('prazo_resposta_dias')->nullable();   // legislativo: prazo para responder ao vereador
        });
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->foreignId('vereador_id')->nullable()->constrained('proc_vereadores')->nullOnDelete();
            $t->string('proposicao_tipo', 20)->nullable();     // requerimento | indicacao | pedido_informacao | mocao
            $t->string('proposicao_numero', 30)->nullable();
            $t->index(['vereador_id', 'proposicao_tipo']);
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['legislativo.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'legislativo.gerir')->delete();
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vereador_id');
            $t->dropColumn(['proposicao_tipo', 'proposicao_numero']);
        });
        Schema::table('proc_tipos_processo', fn (Blueprint $t) => $t->dropColumn('prazo_resposta_dias'));
        Schema::dropIfExists('proc_vereadores');
    }
};
