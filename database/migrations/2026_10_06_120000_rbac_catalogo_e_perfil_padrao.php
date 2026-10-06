<?php

declare(strict_types=1);

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Liga o RBAC sem tirar de ninguém o que já usava:
 *  1. completa o catálogo de permissões (processo, comunicação, portal, admin.*);
 *  2. o perfil Administrador recebe todas;
 *  3. os demais perfis existentes recebem as operacionais novas (antes eram livres a todos);
 *  4. usuário INTERNO sem nenhum perfil recebe o "Usuário padrão" (tudo menos administração).
 * Super admin não depende de perfil (passa direto no Gate).
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permissoes::sincronizarCatalogo();

        $adminId = DB::table('ged_roles')->where('nome', 'Administrador')->value('id');
        if ($adminId) {
            Permissoes::concederAoPerfil((int) $adminId, array_keys(Permissoes::CATALOGO), $ids);
        }

        DB::table('ged_roles')->where('nome', '!=', 'Administrador')->pluck('id')
            ->each(fn ($roleId) => Permissoes::concederAoPerfil((int) $roleId, Permissoes::OPERACIONAIS_NOVAS, $ids));

        DB::table('ged_roles')->updateOrInsert(
            ['nome' => Permissoes::PERFIL_PADRAO],
            ['descricao' => 'Atribuído a quem não tinha perfil quando o controle de acesso foi ligado. Tudo exceto administração.',
             'created_at' => now(), 'updated_at' => now()],
        );
        $padraoId = (int) DB::table('ged_roles')->where('nome', Permissoes::PERFIL_PADRAO)->value('id');
        Permissoes::concederAoPerfil($padraoId, Permissoes::doPerfilPadrao(), $ids);

        $semPerfil = DB::table('users')
            ->where('tipo', 'interno')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ged_user_roles')->whereColumn('ged_user_roles.user_id', 'users.id'))
            ->pluck('id');

        DB::table('ged_user_roles')->insertOrIgnore(
            $semPerfil->map(fn ($userId) => ['user_id' => $userId, 'role_id' => $padraoId])->all()
        );
    }

    public function down(): void
    {
        $padraoId = DB::table('ged_roles')->where('nome', Permissoes::PERFIL_PADRAO)->value('id');
        if ($padraoId) {
            DB::table('ged_user_roles')->where('role_id', $padraoId)->delete();
            DB::table('ged_role_permissions')->where('role_id', $padraoId)->delete();
            DB::table('ged_roles')->where('id', $padraoId)->delete();
        }
        // O catálogo fica: remover permissões apagaria vínculos que o administrador
        // possa ter configurado depois.
    }
};
