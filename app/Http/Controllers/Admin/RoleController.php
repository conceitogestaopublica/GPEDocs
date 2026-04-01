<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::with('permissions')
            ->orderBy('nome')
            ->paginate(20);

        $permissions = Permission::orderBy('nome')->get();

        return Inertia::render('GED/Admin/Roles/Index', [
            'roles'       => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'          => ['required', 'string', 'max:255', 'unique:ged_roles,nome'],
            'descricao'     => ['nullable', 'string'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:ged_permissions,id'],
        ]);

        try {
            DB::beginTransaction();

            $role = Role::create([
                'nome'      => $request->input('nome'),
                'descricao' => $request->input('descricao'),
            ]);

            if ($request->filled('permissions')) {
                DB::table('ged_role_permissions')->insert(
                    collect($request->input('permissions'))->map(fn ($permId) => [
                        'role_id'       => $role->id,
                        'permission_id' => $permId,
                    ])->toArray()
                );
            }

            DB::commit();

            return redirect()->back()->with('success', 'Perfil criado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao criar perfil: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome'          => ['required', 'string', 'max:255', 'unique:ged_roles,nome,' . $id],
            'descricao'     => ['nullable', 'string'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:ged_permissions,id'],
        ]);

        try {
            DB::beginTransaction();

            $role = Role::findOrFail($id);

            $role->update([
                'nome'      => $request->input('nome'),
                'descricao' => $request->input('descricao'),
            ]);

            // Sync permissions
            DB::table('ged_role_permissions')->where('role_id', $role->id)->delete();
            if ($request->filled('permissions')) {
                DB::table('ged_role_permissions')->insert(
                    collect($request->input('permissions'))->map(fn ($permId) => [
                        'role_id'       => $role->id,
                        'permission_id' => $permId,
                    ])->toArray()
                );
            }

            DB::commit();

            return redirect()->back()->with('success', 'Perfil atualizado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao atualizar perfil: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);

            $hasUsers = DB::table('ged_user_roles')->where('role_id', $id)->exists();
            if ($hasUsers) {
                return redirect()->back()->with('error', 'Não é possível excluir um perfil que possui usuários vinculados.');
            }

            DB::table('ged_role_permissions')->where('role_id', $role->id)->delete();
            $role->delete();

            return redirect()->back()->with('success', 'Perfil excluído com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao excluir perfil: ' . $e->getMessage());
        }
    }
}
