<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contatos (TR 4.21): base única de pessoas físicas, jurídicas e setores de jurídica,
 * ligada ao cidadão do portal e ao requerente do processo; campos complementares,
 * regras de preenchimento por perfil, listas, procurações e unificação de duplicados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contatos', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 10);                     // pf | pj | setor_pj
            $t->string('nome', 255);
            $t->string('cpf', 11)->nullable();
            $t->string('cnpj', 14)->nullable();
            $t->string('email', 255)->nullable();
            $t->string('celular', 20)->nullable();
            $t->string('telefone', 20)->nullable();
            $t->jsonb('endereco')->nullable();
            $t->foreignId('empresa_id')->nullable()->constrained('contatos')->nullOnDelete();   // setor de PJ → a PJ
            $t->foreignId('cidadao_id')->nullable()->unique()->constrained('portal_cidadaos')->nullOnDelete();
            $t->jsonb('campos')->nullable();            // complementares: {id do campo: valor}
            $t->text('observacao')->nullable();
            $t->string('origem', 20)->default('interno');   // interno | portal | processo | importacao
            $t->foreignId('unificado_em_id')->nullable()->constrained('contatos')->nullOnDelete();
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index('cpf');
            $t->index('cnpj');
            $t->index('email');
            $t->index('tipo');
        });

        Schema::create('contato_campos', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 80);
            $t->string('explicacao', 255)->nullable();
            $t->string('formato', 20);                  // texto | numero | data | lista | email | telefone | cpf_cnpj
            $t->jsonb('opcoes')->nullable();            // para "lista"
            $t->string('onde', 15)->default('ambos');   // interno | autocadastro | ambos
            $t->boolean('obrigatorio')->default(false);
            $t->jsonb('perfis')->nullable();            // ["pf","pj","setor_pj"]; null = todos
            $t->unsignedSmallInteger('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        // Regras de preenchimento por perfil (TR 4.21.3): quais campos básicos são obrigatórios
        Schema::create('contato_regras', function (Blueprint $t) {
            $t->string('tipo', 10)->primary();
            $t->jsonb('obrigatorios');
            $t->timestamps();
        });
        DB::table('contato_regras')->insert([
            ['tipo' => 'pf', 'obrigatorios' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['tipo' => 'pj', 'obrigatorios' => json_encode(['cnpj']), 'created_at' => now(), 'updated_at' => now()],
            ['tipo' => 'setor_pj', 'obrigatorios' => json_encode(['empresa_id']), 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('contato_listas', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 120);
            $t->string('descricao', 255)->nullable();
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('contato_lista_itens', function (Blueprint $t) {
            $t->foreignId('lista_id')->constrained('contato_listas')->cascadeOnDelete();
            $t->foreignId('contato_id')->constrained('contatos')->cascadeOnDelete();
            $t->primary(['lista_id', 'contato_id']);
        });

        // Procuração eletrônica (TR 4.21.10–11): o procurador age em nome do outorgante
        Schema::create('contato_procuracoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outorgante_id')->constrained('contatos')->cascadeOnDelete();
            $t->foreignId('procurador_id')->constrained('contatos')->cascadeOnDelete();
            $t->string('poderes', 1000)->nullable();
            $t->date('inicio')->nullable();
            $t->date('fim')->nullable();
            $t->string('documento_path', 500)->nullable();
            $t->timestamp('revogada_em')->nullable();
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('procurador_id');
        });

        Schema::create('contato_historico', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contato_id')->constrained('contatos')->cascadeOnDelete();
            $t->string('acao', 30);
            $t->jsonb('detalhes')->nullable();
            $t->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::table('proc_processos', function (Blueprint $t) {
            $t->foreignId('contato_id')->nullable()->constrained('contatos')->nullOnDelete();
            $t->foreignId('procurador_contato_id')->nullable()->constrained('contatos')->nullOnDelete();
        });

        // Os cidadãos do portal e os requerentes que já existiam entram na base
        \App\Support\Contatos::sincronizarTudo();

        $ids = Permissoes::sincronizarCatalogo();
        $perfis = DB::table('ged_role_permissions')->where('permission_id', $ids['portal.atendimento'] ?? 0)->pluck('role_id');
        foreach ($perfis as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['contato.gerenciar'], $ids);
        }
        $admins = DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id');
        foreach ($admins as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['admin.contatos'], $ids);
        }
    }

    public function down(): void
    {
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('procurador_contato_id');
            $t->dropConstrainedForeignId('contato_id');
        });
        foreach (['contato_historico', 'contato_procuracoes', 'contato_lista_itens', 'contato_listas', 'contato_regras', 'contato_campos', 'contatos'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
        DB::table('ged_permissions')->whereIn('nome', ['contato.gerenciar', 'admin.contatos'])->delete();
    }
};
