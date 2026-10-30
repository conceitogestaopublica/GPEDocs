<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAD — processo administrativo disciplinar (TR 4.40): tipos de infração com o setor que
 * recebe o processo de início, a comissão processante e a permissão de quem conduz os PADs.
 * O sigilo é o mesmo do requerimento do servidor (só participantes e quem tem a permissão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_pad_infracoes', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 200);
            $t->string('gravidade', 10)->default('media');           // leve | media | grave
            $t->string('base_legal', 255)->nullable();
            $t->foreignId('unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();   // destino inicial
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('proc_pad_comissao', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('papel', 12)->default('membro');               // presidente | membro | secretario
            $t->timestamps();
            $t->unique(['processo_id', 'user_id']);
        });
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->foreignId('pad_infracao_id')->nullable()->constrained('proc_pad_infracoes')->nullOnDelete();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['pad.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'pad.gerir')->delete();
        Schema::table('proc_processos', fn (Blueprint $t) => $t->dropConstrainedForeignId('pad_infracao_id'));
        Schema::dropIfExists('proc_pad_comissao');
        Schema::dropIfExists('proc_pad_infracoes');
    }
};
