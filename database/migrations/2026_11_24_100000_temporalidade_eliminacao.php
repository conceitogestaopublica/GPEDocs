<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de temporalidade e destinação (modelo CONARQ) em cada código do plano de classificação, e a
 * eliminação de documentos na forma da Resolução CONARQ nº 40/2014: listagem de eliminação, aprovação,
 * edital de ciência com prazo e termo de eliminação. O item eliminado perde os arquivos e mantém os
 * metadados, a classificação e o histórico.
 */
return new class extends Migration
{
    private const ITENS = ['proc_processos', 'proc_memorandos', 'proc_oficios', 'proc_circulares', 'ged_documentos'];

    public function up(): void
    {
        Schema::table('ged_classificacoes', function (Blueprint $t) {
            $t->unsignedSmallInteger('ttd_corrente_anos')->nullable();
            $t->string('ttd_corrente_texto', 150)->nullable();          // prazo condicional (ex.: "enquanto vigente")
            $t->unsignedSmallInteger('ttd_intermediaria_anos')->nullable();
            $t->string('ttd_intermediaria_texto', 150)->nullable();
            $t->string('ttd_evento', 20)->nullable();                   // producao | conclusao — início da contagem
            $t->string('ttd_destinacao', 20)->nullable();               // eliminacao | guarda_permanente
            $t->text('ttd_observacao')->nullable();
        });

        Schema::create('ged_eliminacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->nullable()->constrained('ugs')->nullOnDelete();
            $t->string('numero', 20)->unique();                          // LE-2026/0001
            $t->string('status', 20)->default('rascunho');               // rascunho | aprovada | edital | eliminada | cancelada
            $t->text('observacao')->nullable();
            $t->foreignId('criada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('aprovada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('aprovada_em')->nullable();
            $t->timestamp('edital_em')->nullable();
            $t->unsignedSmallInteger('edital_prazo_dias')->nullable();   // prazo para manifestações (30 a 45 dias)
            $t->foreignId('eliminada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('eliminada_em')->nullable();
            $t->unsignedInteger('arquivos_apagados')->default(0);
            $t->foreignId('cancelada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->string('motivo_cancelamento', 1000)->nullable();
            $t->timestamps();
        });
        Schema::create('ged_eliminacao_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('eliminacao_id')->constrained('ged_eliminacoes')->cascadeOnDelete();
            $t->string('tipo', 20);                                       // processo | memorando | oficio | circular | documento
            $t->unsignedBigInteger('item_id');
            $t->foreignId('classificacao_id')->nullable()->constrained('ged_classificacoes')->nullOnDelete();
            $t->string('identificacao', 255);                             // número/nome no momento da listagem
            $t->string('assunto', 500)->nullable();
            $t->date('data_inicial')->nullable();                         // datas-limite do conjunto
            $t->date('data_final')->nullable();
            $t->timestamps();
            $t->unique(['eliminacao_id', 'tipo', 'item_id']);
            $t->index(['tipo', 'item_id']);
        });

        foreach (self::ITENS as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->timestamp('eliminado_em')->nullable();
                $t->foreignId('eliminacao_id')->nullable()->constrained('ged_eliminacoes')->nullOnDelete();
            });
        }

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.classificacao'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['arquivo.temporalidade', 'arquivo.eliminar'], $ids);
        }
    }

    public function down(): void
    {
        foreach (self::ITENS as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->dropConstrainedForeignId('eliminacao_id');
                $t->dropColumn('eliminado_em');
            });
        }
        Schema::dropIfExists('ged_eliminacao_itens');
        Schema::dropIfExists('ged_eliminacoes');
        Schema::table('ged_classificacoes', fn (Blueprint $t) => $t->dropColumn(['ttd_corrente_anos', 'ttd_corrente_texto',
            'ttd_intermediaria_anos', 'ttd_intermediaria_texto', 'ttd_evento', 'ttd_destinacao', 'ttd_observacao']));
        DB::table('ged_permissions')->whereIn('nome', ['arquivo.temporalidade', 'arquivo.eliminar'])->delete();
    }
};
