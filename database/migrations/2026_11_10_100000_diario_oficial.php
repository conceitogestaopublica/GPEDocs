<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diário Oficial eletrônico (TR 4.43): estrutura de cadernos, seções e subseções; responsáveis;
 * matérias (atos enviados ao diário, com ou sem edição); edições ordinárias e extraordinárias,
 * publicadas de forma irreversível com o PDF assinado pelo e-CNPJ da prefeitura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dof_estrutura', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('dof_estrutura')->cascadeOnDelete();
            $t->unsignedTinyInteger('nivel');                   // 1 caderno | 2 seção | 3 subseção
            $t->string('nome', 150);
            $t->unsignedInteger('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('dof_responsaveis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->string('funcao', 100)->nullable();             // ex.: Editor responsável
            $t->string('telefone', 30)->nullable();
            $t->string('email', 150)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('dof_edicoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs');
            $t->unsignedInteger('numero')->nullable();          // dado na publicação
            $t->date('data');
            $t->string('tipo', 15);                             // ordinaria | extraordinaria
            $t->string('status', 10)->default('pendente');      // pendente | publicada
            $t->boolean('automatica')->default(false);
            $t->string('pdf_path', 500)->nullable();            // edição completa assinada (versão certificada)
            $t->string('hash_sha256', 64)->nullable();
            $t->unsignedInteger('paginas')->nullable();
            $t->boolean('assinada')->default(false);
            $t->string('certificado_cn', 255)->nullable();
            $t->timestamp('publicada_em')->nullable();
            $t->foreignId('publicada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('criada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->uuid('qr_code_token')->unique();
            $t->timestamps();
            $t->index(['ug_id', 'data']);
        });
        Schema::create('dof_materias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs');
            $t->foreignId('ato_id')->unique()->constrained('proc_atos')->cascadeOnDelete();
            $t->foreignId('edicao_id')->nullable()->constrained('dof_edicoes')->nullOnDelete();
            $t->foreignId('estrutura_id')->nullable()->constrained('dof_estrutura')->nullOnDelete();
            $t->unsignedInteger('ordem')->default(0);
            $t->unsignedInteger('pagina_inicial')->nullable();  // página na edição publicada
            $t->foreignId('enviada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['diario.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'diario.gerir')->delete();
        Schema::dropIfExists('dof_materias');
        Schema::dropIfExists('dof_edicoes');
        Schema::dropIfExists('dof_responsaveis');
        Schema::dropIfExists('dof_estrutura');
    }
};
