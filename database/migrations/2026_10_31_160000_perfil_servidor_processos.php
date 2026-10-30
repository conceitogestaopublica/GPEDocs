<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O perfil "Servidor" (criado pela importação do RH do gpe2) passa a abrir, ver e
 * tramitar processos — decisão de 2026-10-29: com a tramitação por setor, o servidor
 * precisa receber e despachar o que chega ao setor dele. Antes ficava só com a área
 * do servidor no Portal e dava 403 ao abrir um processo.
 */
return new class extends Migration
{
    private const PERMISSOES = ['processo.visualizar', 'processo.criar', 'processo.tramitar'];

    public function up(): void
    {
        $role = DB::table('ged_roles')->where('nome', 'Servidor')->value('id');
        if (! $role) {
            return;   // prefeitura sem importação do RH: o perfil nasce já com as permissões
        }
        DB::table('ged_roles')->where('id', $role)->update([
            'descricao'  => 'Servidor municipal: área do servidor no Portal e processos do setor (abrir, ver e tramitar)',
            'updated_at' => now(),
        ]);
        foreach (DB::table('ged_permissions')->whereIn('nome', self::PERMISSOES)->pluck('id') as $perm) {
            DB::table('ged_role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $perm]);
        }
    }

    public function down(): void
    {
        $role = DB::table('ged_roles')->where('nome', 'Servidor')->value('id');
        if ($role) {
            DB::table('ged_role_permissions')->where('role_id', $role)
                ->whereIn('permission_id', DB::table('ged_permissions')->whereIn('nome', self::PERMISSOES)->pluck('id'))->delete();
        }
    }
};
