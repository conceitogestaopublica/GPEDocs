<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Servidor "só portal/app": quem veio só do RH do gpe2 (docs:importar-servidores) pede pelo Portal
 * do Cidadão e pelo app, e não usa o GED — não entra no GED, não aparece no chat interno nem nas
 * listas de pessoas. Usuários do GED são os usuários do sistema (gpe2) e os cadastrados aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('somente_portal')->default(false)->index();
        });

        // Já importados: sem usuário do gpe2, sem super admin e só com o perfil "Servidor"
        $servidor = DB::table('ged_roles')->where('nome', 'Servidor')->value('id');
        if ($servidor) {
            DB::table('users')->whereNull('legado_usuario_id')->whereNotNull('legado_funcionario_id')
                ->where('super_admin', false)->where('tipo', 'interno')
                ->whereExists(fn ($q) => $q->from('ged_user_roles')->whereColumn('ged_user_roles.user_id', 'users.id')->where('role_id', $servidor))
                ->whereNotExists(fn ($q) => $q->from('ged_user_roles')->whereColumn('ged_user_roles.user_id', 'users.id')->where('role_id', '<>', $servidor))
                ->update(['somente_portal' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('somente_portal'));
    }
};
