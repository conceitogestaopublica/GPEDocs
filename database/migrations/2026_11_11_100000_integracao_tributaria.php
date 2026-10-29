<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integração com o sistema tributário (TR 4.48, 4.49, 4.50, 4.51): configurável por UG — adaptador
 * (GPE Tributário, REST genérico de terceiros ou sem integração), endereço, autenticação e o
 * mapeamento dos recursos. Os vínculos do processo (imóvel, guia, alvará, habite-se, certidão,
 * fiscalização, licença) e os eventos recebidos do tributário ficam registrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_integracao_tributaria', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->nullable()->unique()->constrained('ugs')->cascadeOnDelete();   // null = todas as UGs
            $t->string('driver', 20)->default('nenhum');        // gpe | rest | nenhum
            $t->string('nome_sistema', 100)->nullable();        // como aparece nas telas (ex.: "Tributário GPE", "Sistema X")
            $t->string('base_url', 300)->nullable();
            $t->string('auth_tipo', 10)->default('bearer');     // bearer | header | basic | nenhum
            $t->string('auth_header', 60)->nullable();           // nome do header quando auth_tipo = header
            $t->text('credencial')->nullable();                  // token / "usuario:senha" (cifrado)
            $t->jsonb('config')->nullable();                     // parâmetros do adaptador e mapeamento dos recursos (REST genérico)
            $t->unsignedSmallInteger('timeout')->default(20);
            $t->boolean('ativo')->default(true);
            $t->timestamp('ultimo_teste_em')->nullable();
            $t->boolean('ultimo_teste_ok')->nullable();
            $t->string('ultimo_teste_msg', 500)->nullable();
            $t->timestamps();
        });
        Schema::create('proc_vinculos_tributarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->string('tipo', 20);                              // imovel | guia | alvara_construcao | habitese | certidao | fiscalizacao | licenca
            $t->string('id_externo', 100)->nullable();
            $t->string('numero', 100)->nullable();
            $t->string('situacao', 30)->nullable();
            $t->decimal('valor', 14, 2)->nullable();
            $t->date('vencimento')->nullable();
            $t->jsonb('dados')->nullable();
            $t->foreignId('anexo_id')->nullable()->constrained('proc_anexos')->nullOnDelete();
            $t->boolean('manual')->default(false);               // informado à mão (sem integração)
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tipo', 'id_externo']);
        });
        Schema::create('ged_eventos_tributarios', function (Blueprint $t) {
            $t->id();
            $t->string('evento', 40);
            $t->string('sistema', 60)->nullable();
            $t->jsonb('payload');
            $t->string('resultado', 20);                         // aplicado | ignorado | erro
            $t->string('mensagem', 500)->nullable();
            $t->foreignId('vinculo_id')->nullable()->constrained('proc_vinculos_tributarios')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['tributario.usar'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'tributario.usar')->delete();
        Schema::dropIfExists('ged_eventos_tributarios');
        Schema::dropIfExists('proc_vinculos_tributarios');
        Schema::dropIfExists('ged_integracao_tributaria');
    }
};
