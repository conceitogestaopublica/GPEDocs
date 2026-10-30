<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ouvidoria (TR 4.33, Lei 13.460) e e-SIC (TR 4.34, Lei 12.527) sobre o motor de processos:
 * a modalidade fica no assunto; no processo, o tipo da manifestação, a identificação (aberta,
 * sigilosa, anônima), o canal de entrada, o prazo legal e a prorrogação e a decisão final.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->string('modalidade', 12)->nullable();          // ouvidoria | esic
        });
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->string('canal', 20)->nullable();               // portal | balcao | telefone | email | redes_sociais | presencial
            $t->string('manifestacao_tipo', 15)->nullable();   // denuncia | elogio | reclamacao | solicitacao | sugestao
            $t->string('identificacao', 10)->default('aberta'); // aberta | sigilosa | anonima
            $t->timestamp('prazo_legal_em')->nullable();
            $t->timestamp('prorrogado_em')->nullable();
            $t->text('prorrogacao_motivo')->nullable();
            $t->text('decisao_final')->nullable();
            $t->timestamp('decisao_final_em')->nullable();
            $t->index(['manifestacao_tipo', 'identificacao']);
        });
        DB::table('proc_tipos_processo')->whereRaw('upper(sigla) = ?', ['OUV'])->update(['modalidade' => 'ouvidoria', 'prazo_dias_uteis' => true]);
        DB::table('proc_tipos_processo')->whereRaw('upper(sigla) = ?', ['LAI'])->update(['modalidade' => 'esic']);

        // Quem já administra o atendimento ganha as permissões novas
        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['ouvidoria.gerir', 'esic.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->whereIn('nome', ['ouvidoria.gerir', 'esic.gerir'])->delete();
        Schema::table('proc_processos', fn (Blueprint $t) => $t->dropColumn(['canal', 'manifestacao_tipo', 'identificacao', 'prazo_legal_em',
            'prorrogado_em', 'prorrogacao_motivo', 'decisao_final', 'decisao_final_em']));
        Schema::table('proc_tipos_processo', fn (Blueprint $t) => $t->dropColumn('modalidade'));
    }
};
