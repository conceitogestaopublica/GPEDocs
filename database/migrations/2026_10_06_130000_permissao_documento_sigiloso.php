<?php

declare(strict_types=1);

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permissão documento.sigiloso (ver documentos confidenciais). Vai só para o
 * Administrador: os demais perfis a recebem por decisão do administrador da UG.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = Permissoes::sincronizarCatalogo();

        $adminId = DB::table('ged_roles')->where('nome', 'Administrador')->value('id');
        if ($adminId) {
            Permissoes::concederAoPerfil((int) $adminId, ['documento.sigiloso'], $ids);
        }
    }

    public function down(): void
    {
        $id = DB::table('ged_permissions')->where('nome', 'documento.sigiloso')->value('id');
        if ($id) {
            DB::table('ged_role_permissions')->where('permission_id', $id)->delete();
            DB::table('ged_permissions')->where('id', $id)->delete();
        }
    }
};
