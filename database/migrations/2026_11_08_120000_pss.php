<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Processo seletivo simplificado (TR 4.41): edital com período de inscrição, cargos e o assunto
 * cujo formulário e documentos exigidos formam a ficha de inscrição; inscrições pelo portal,
 * com anexos, situação e resposta ao candidato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_pss', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs');
            $t->string('titulo', 200);
            $t->string('edital_numero', 40)->nullable();
            $t->text('descricao')->nullable();
            $t->text('requisitos')->nullable();
            $t->jsonb('cargos')->nullable();                    // [{nome, vagas}]
            $t->foreignId('tipo_processo_id')->nullable()->constrained('proc_tipos_processo')->nullOnDelete();   // ficha = formulário do assunto
            $t->timestamp('inscricoes_inicio');
            $t->timestamp('inscricoes_fim');
            $t->boolean('publicado')->default(false);
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('proc_pss_inscricoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pss_id')->constrained('proc_pss')->cascadeOnDelete();
            $t->string('numero', 30)->unique();
            $t->string('cargo', 150)->nullable();
            $t->foreignId('cidadao_id')->nullable()->constrained('portal_cidadaos')->nullOnDelete();
            $t->string('nome', 200);
            $t->string('cpf', 11);
            $t->string('email', 150)->nullable();
            $t->string('telefone', 30)->nullable();
            $t->jsonb('dados')->nullable();
            $t->string('situacao', 12)->default('recebida');   // recebida | deferida | indeferida | classificada
            $t->text('resposta')->nullable();
            $t->timestamp('respondida_em')->nullable();
            $t->foreignId('respondida_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['pss_id', 'cpf', 'cargo']);
        });
        Schema::create('proc_pss_anexos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inscricao_id')->constrained('proc_pss_inscricoes')->cascadeOnDelete();
            $t->string('nome', 255);
            $t->string('arquivo_path', 500);
            $t->unsignedBigInteger('tamanho');
            $t->string('mime_type', 120);
            $t->string('hash_sha256', 64)->nullable();
            $t->timestamps();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['pss.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'pss.gerir')->delete();
        Schema::dropIfExists('proc_pss_anexos');
        Schema::dropIfExists('proc_pss_inscricoes');
        Schema::dropIfExists('proc_pss');
    }
};