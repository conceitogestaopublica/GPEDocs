<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Painel de indicadores (TR 4.25.1, 4.25.3, 4.25.7, 4.25.17–26, 4.29): páginas de cada anexo (para o
 * total e o acumulado de páginas; os antigos são contados pelo comando anexos:contar-paginas) e a
 * permissão de ver os indicadores de todos os setores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_anexos', fn (Blueprint $t) => $t->unsignedInteger('paginas')->nullable());

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['relatorio.indicadores'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'relatorio.indicadores')->delete();
        Schema::table('proc_anexos', fn (Blueprint $t) => $t->dropColumn('paginas'));
    }
};
