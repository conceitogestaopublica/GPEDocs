<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Requerimento do servidor (TR 4.36): permissão de Gestão de Pessoas para ver todos os
 * requerimentos pessoais; quem já administra o sistema recebe a permissão.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['rh.requerimentos'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'rh.requerimentos')->delete();
    }
};
