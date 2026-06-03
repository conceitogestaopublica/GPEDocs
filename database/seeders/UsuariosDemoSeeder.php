<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ug;
use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Cria usuarios de demonstracao alem do admin do GedSeeder.
 * Idempotente: usa firstOrCreate por email.
 */
class UsuariosDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            $ug = Ug::query()->where('codigo', 'UG-MODELO')->first();
            if (! $ug) {
                $this->command?->warn('[UsuariosDemoSeeder] UG-MODELO nao encontrada — execute UgModeloSeeder antes.');
                return;
            }

            $setorFolha = UgOrganograma::query()
                ->where('ug_id', $ug->id)
                ->where('codigo', 'SET-001')
                ->where('nivel', 3)
                ->first();

            $unidadeNivel2 = UgOrganograma::query()
                ->where('ug_id', $ug->id)
                ->where('nivel', 2)
                ->orderBy('id')
                ->first();

            $senhaHash = Hash::make('demo1234');

            $usuariosDemo = [
                [
                    'email'           => 'joeljardim@gmail.com',
                    'name'            => 'Super Admin',
                    'cpf'             => '111.111.111-11',
                    'tipo'            => 'interno',
                    'super_admin'     => true,
                    'ug_id'           => $ug->id,
                    'unidade_id'      => null,
                    'role'            => 'Administrador',
                ],
                [
                    'email'           => 'admin.ug@modelo.local',
                    'name'            => 'Maria Admin',
                    'cpf'             => '222.222.222-22',
                    'tipo'            => 'interno',
                    'super_admin'     => false,
                    'ug_id'           => $ug->id,
                    'unidade_id'      => $setorFolha?->id,
                    'role'            => 'Administrador',
                ],
                [
                    'email'           => 'operador@modelo.local',
                    'name'            => 'João Operador',
                    'cpf'             => '333.333.333-33',
                    'tipo'            => 'interno',
                    'super_admin'     => false,
                    'ug_id'           => $ug->id,
                    'unidade_id'      => $setorFolha?->id,
                    'role'            => 'Editor',
                ],
                [
                    'email'           => 'gestor@modelo.local',
                    'name'            => 'Ana Gestora',
                    'cpf'             => '444.444.444-44',
                    'tipo'            => 'interno',
                    'super_admin'     => false,
                    'ug_id'           => $ug->id,
                    'unidade_id'      => $unidadeNivel2?->id,
                    'role'            => 'Gestor Documental',
                ],
            ];

            $criados = 0;
            foreach ($usuariosDemo as $dados) {
                $user = User::firstOrCreate(
                    ['email' => $dados['email']],
                    [
                        'name'        => $dados['name'],
                        'password'    => $senhaHash,
                        'cpf'         => $dados['cpf'],
                        'tipo'        => $dados['tipo'],
                        'super_admin' => $dados['super_admin'],
                        'ug_id'       => $dados['ug_id'],
                        'unidade_id'  => $dados['unidade_id'],
                    ]
                );

                // Pivot user_ugs (idempotente)
                DB::table('user_ugs')->updateOrInsert(
                    ['user_id' => $user->id, 'ug_id' => $ug->id],
                    [
                        'principal'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Role do GED (ged_user_roles tem PK composta user_id+role_id)
                $roleId = DB::table('ged_roles')->where('nome', $dados['role'])->value('id');
                if ($roleId) {
                    $jaTem = DB::table('ged_user_roles')
                        ->where('user_id', $user->id)
                        ->where('role_id', $roleId)
                        ->exists();
                    if (! $jaTem) {
                        DB::table('ged_user_roles')->insert([
                            'user_id' => $user->id,
                            'role_id' => $roleId,
                        ]);
                    }
                }

                $criados++;
            }

            // Garante que o admin@ged.local (criado pelo GedSeeder) tambem fique vinculado a UG modelo
            $adminGed = User::query()->where('email', 'admin@ged.local')->first();
            if ($adminGed) {
                if (empty($adminGed->ug_id)) {
                    $adminGed->ug_id = $ug->id;
                    $adminGed->tipo = $adminGed->tipo ?: 'interno';
                    $adminGed->save();
                }
                DB::table('user_ugs')->updateOrInsert(
                    ['user_id' => $adminGed->id, 'ug_id' => $ug->id],
                    [
                        'principal'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            $this->command?->info("[UsuariosDemoSeeder] {$criados} usuarios demo criados/atualizados.");
        });
    }
}
