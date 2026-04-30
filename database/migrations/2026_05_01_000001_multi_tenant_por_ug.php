<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenancy por Unidade Gestora (UG).
 *
 * Mudancas:
 *  1. Pivot user_ugs (N:N entre users e ugs, com flag principal)
 *  2. Flag super_admin em users (vai poder acessar dados de todas UGs)
 *  3. ug_id em tabelas de dados (documentos, pastas, processos, etc.)
 *  4. Backfill: copia users.ug_id para o pivot e atribui ug_id padrao
 *     aos dados existentes baseado na primeira UG ativa
 *
 * Mantemos users.ug_id por compatibilidade (sera deprecado em uma migration
 * futura, depois que todo o codigo passar a usar exclusivamente o pivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Pivot user_ugs
        Schema::create('user_ugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ug_id')->constrained('ugs')->cascadeOnDelete();
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'ug_id']);
            $table->index('ug_id');
        });

        // 2) super_admin em users
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('super_admin')->default(false)->after('tipo');
        });

        // 3) ug_id em tabelas de dados
        $tabelas = [
            'ged_documentos',
            'ged_pastas',
            'proc_processos',
            'proc_memorandos',
            'proc_circulares',
            'proc_oficios',
        ];

        foreach ($tabelas as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            Schema::table($tabela, function (Blueprint $t) {
                $t->foreignId('ug_id')->nullable()->after('id')
                    ->constrained('ugs')->nullOnDelete();
                $t->index('ug_id');
            });
        }

        // 4) Backfill — copia users.ug_id para o pivot
        $usersComUg = DB::table('users')->whereNotNull('ug_id')->get(['id', 'ug_id']);
        foreach ($usersComUg as $u) {
            DB::table('user_ugs')->insertOrIgnore([
                'user_id'    => $u->id,
                'ug_id'      => $u->ug_id,
                'principal'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Backfill — atribui ug_id padrao para registros existentes (primeira UG ativa)
        $ugDefaultId = DB::table('ugs')->where('ativo', true)->orderBy('id')->value('id');
        if ($ugDefaultId) {
            foreach ($tabelas as $tabela) {
                if (! Schema::hasTable($tabela)) {
                    continue;
                }
                DB::table($tabela)->whereNull('ug_id')->update(['ug_id' => $ugDefaultId]);
            }
        }
    }

    public function down(): void
    {
        $tabelas = [
            'ged_documentos',
            'ged_pastas',
            'proc_processos',
            'proc_memorandos',
            'proc_circulares',
            'proc_oficios',
        ];

        foreach ($tabelas as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            Schema::table($tabela, function (Blueprint $t) {
                $t->dropForeign(['ug_id']);
                $t->dropColumn('ug_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('super_admin');
        });

        Schema::dropIfExists('user_ugs');
    }
};
