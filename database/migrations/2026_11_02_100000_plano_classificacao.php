<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de classificação de documentos (TR 4.27): quatro níveis com código único,
 * classificação padrão por módulo e por assunto (tipo de processo / tipo documental,
 * que vence a do módulo), a classificação de cada item e o histórico das reclassificações.
 */
return new class extends Migration
{
    /** tabela => coluna do assunto (null quando o módulo não tem assunto) */
    private const ITENS = [
        'proc_processos'  => 'tipo_processo_id',
        'proc_memorandos' => null,
        'proc_oficios'    => null,
        'proc_circulares' => null,
        'ged_documentos'  => 'tipo_documental_id',
    ];

    public function up(): void
    {
        Schema::create('ged_classificacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('ged_classificacoes')->restrictOnDelete();
            $t->unsignedSmallInteger('nivel');
            $t->string('codigo', 30)->unique();
            $t->string('descricao', 255);
            $t->text('observacao')->nullable();
            // Suspensa: não entra em item novo, mas continua nos que já tinha (TR 4.27.4)
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index('parent_id');
        });

        Schema::create('ged_classificacao_modulos', function (Blueprint $t) {
            $t->string('modulo', 20)->primary();
            $t->foreignId('classificacao_id')->constrained('ged_classificacoes')->cascadeOnDelete();
            $t->timestamps();
        });

        foreach (self::ITENS as $tabela => $_) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->foreignId('classificacao_id')->nullable()->constrained('ged_classificacoes')->restrictOnDelete();
                $t->index('classificacao_id');
            });
        }
        foreach (['proc_tipos_processo', 'ged_tipos_documentais'] as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->foreignId('classificacao_id')->nullable()->constrained('ged_classificacoes')->nullOnDelete();
            });
        }

        Schema::create('ged_classificacao_historico', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 20);
            $t->unsignedBigInteger('item_id');
            $t->foreignId('de_id')->nullable()->constrained('ged_classificacoes')->nullOnDelete();
            $t->foreignId('para_id')->nullable()->constrained('ged_classificacoes')->nullOnDelete();
            $t->string('motivo', 1000)->nullable();
            $t->boolean('automatica')->default(false);
            $t->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tipo', 'item_id']);
        });

        // Permissão nova; quem já administra os tipos documentais passa a cuidar do plano
        $ids = Permissoes::sincronizarCatalogo();
        $perfis = DB::table('ged_role_permissions')->where('permission_id', $ids['admin.tipos_documentais'] ?? 0)->pluck('role_id');
        foreach ($perfis as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['admin.classificacao'], $ids);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_classificacao_historico');
        foreach (['proc_tipos_processo', 'ged_tipos_documentais', ...array_keys(self::ITENS)] as $tabela) {
            Schema::table($tabela, fn (Blueprint $t) => $t->dropConstrainedForeignId('classificacao_id'));
        }
        Schema::dropIfExists('ged_classificacao_modulos');
        Schema::dropIfExists('ged_classificacoes');
        DB::table('ged_permissions')->where('nome', 'admin.classificacao')->delete();
    }
};
