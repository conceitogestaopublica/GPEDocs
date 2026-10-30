<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App offline da fiscalização (TR 4.52): formulários de checklist configuráveis (campos, itens com
 * resposta padrão, objetos fiscalizados) e as vistorias que o fiscal faz no celular sem internet e
 * sincroniza depois. A vistoria guarda a cópia do formulário usado, para não mudar se ele for editado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fis_formularios', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 150);
            $t->text('descricao')->nullable();
            $t->jsonb('objetos')->nullable();        // lista de objetos fiscalizados; vazia = texto livre (4.52.9)
            $t->jsonb('campos')->nullable();         // [{chave, rotulo, tipo, opcoes, obrigatorio}] (4.52.4, 4.52.6)
            $t->jsonb('itens')->nullable();          // [{chave, grupo, texto}] com resposta atende/não atende/não se aplica (4.52.10)
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('fis_vistorias', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();              // gerado no celular: reenviar não duplica
            $t->foreignId('formulario_id')->nullable()->constrained('fis_formularios')->nullOnDelete();
            $t->jsonb('modelo');                     // cópia do formulário no momento da vistoria
            $t->foreignId('fiscal_id')->constrained('users');
            $t->foreignId('processo_id')->nullable()->constrained('proc_processos')->nullOnDelete();
            $t->unsignedBigInteger('diligencia_id')->nullable();
            $t->string('objeto', 250)->nullable();
            $t->string('local_descricao', 500)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->decimal('precisao', 8, 1)->nullable();
            $t->timestamp('realizada_em');
            $t->string('resultado', 20)->nullable();
            $t->jsonb('campos')->nullable();
            $t->jsonb('respostas')->nullable();
            $t->text('observacao')->nullable();
            $t->jsonb('fotos')->nullable();          // [{path, nome, legenda, mime, tamanho}]
            $t->timestamps();                        // created_at = sincronizada em
            $t->index(['fiscal_id', 'realizada_em']);
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['fiscalizacao.formularios', 'fiscalizacao.campo'], $ids);
        }
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['processo.tramitar'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['fiscalizacao.campo'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->whereIn('nome', ['fiscalizacao.formularios', 'fiscalizacao.campo'])->delete();
        Schema::dropIfExists('fis_vistorias');
        Schema::dropIfExists('fis_formularios');
    }
};
