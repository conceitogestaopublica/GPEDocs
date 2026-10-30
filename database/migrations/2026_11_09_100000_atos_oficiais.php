<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atos oficiais (TR 4.42): tipos de ato (numeração geral, por tipo ou manual; modelo .docx;
 * setores que elaboram; quem assina) e o ato, que gera PDF/A com QR, vai à assinatura
 * (servidores e externos) e é publicado para consulta pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_ato_tipos', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 100);
            $t->string('sigla', 20);
            $t->string('numeracao', 10)->default('tipo');         // geral | tipo | manual
            $t->string('modelo_path', 500)->nullable();           // .docx com ${marcadores}
            $t->string('modelo_nome', 255)->nullable();
            $t->jsonb('setores')->nullable();                     // quem elabora; vazio = todos com a permissão
            $t->jsonb('signatarios')->nullable();                 // servidores que assinam por padrão (roteamento)
            $t->boolean('publico')->default(true);                // aparece na consulta pública depois de publicado
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('proc_atos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs');
            $t->foreignId('tipo_id')->constrained('proc_ato_tipos');
            $t->unsignedInteger('numero')->nullable();
            $t->unsignedSmallInteger('ano');
            $t->string('ementa', 1000);
            $t->longText('conteudo')->nullable();                 // texto do editor (sem modelo)
            $t->jsonb('campos')->nullable();                      // valores extras dos marcadores do modelo
            $t->date('data_ato');
            $t->string('status', 12)->default('rascunho');        // rascunho | assinatura | publicado | revogado
            $t->foreignId('autor_id')->constrained('users');
            $t->foreignId('unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();
            $t->foreignId('processo_id')->nullable()->constrained('proc_processos')->nullOnDelete();
            $t->string('pdf_path', 500)->nullable();              // PDF/A gerado
            $t->boolean('pdfa')->default(false);
            $t->foreignId('documento_id')->nullable()->constrained('ged_documentos')->nullOnDelete();
            $t->uuid('qr_code_token')->unique();
            $t->timestamp('publicado_em')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['ug_id', 'tipo_id', 'ano', 'numero']);
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['ato.elaborar', 'ato.tipos'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->whereIn('nome', ['ato.elaborar', 'ato.tipos'])->delete();
        Schema::dropIfExists('proc_atos');
        Schema::dropIfExists('proc_ato_tipos');
    }
};