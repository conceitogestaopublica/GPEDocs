<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(): Response
    {
        $usuarios = User::select('users.*')
            ->with('roles')
            ->orderBy('name')
            ->paginate(20);

        $roles = Role::orderBy('nome')->get();

        return Inertia::render('GED/Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles'    => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles'    => ['nullable', 'array'],
            'roles.*'  => ['integer', 'exists:ged_roles,id'],
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'     => $request->input('name'),
                'email'    => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);

            if ($request->filled('roles')) {
                DB::table('ged_user_roles')->insert(
                    collect($request->input('roles'))->map(fn ($roleId) => [
                        'user_id' => $user->id,
                        'role_id' => $roleId,
                    ])->toArray()
                );
            }

            DB::commit();

            return redirect()->back()->with('success', 'Usuário criado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao criar usuário: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['nullable', 'string', 'min:8'],
            'roles'    => ['nullable', 'array'],
            'roles.*'  => ['integer', 'exists:ged_roles,id'],
        ]);

        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);

            $data = [
                'name'  => $request->input('name'),
                'email' => $request->input('email'),
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->input('password'));
            }

            $user->update($data);

            // Sync roles
            DB::table('ged_user_roles')->where('user_id', $user->id)->delete();
            if ($request->filled('roles')) {
                DB::table('ged_user_roles')->insert(
                    collect($request->input('roles'))->map(fn ($roleId) => [
                        'user_id' => $user->id,
                        'role_id' => $roleId,
                    ])->toArray()
                );
            }

            DB::commit();

            return redirect()->back()->with('success', 'Usuário atualizado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao atualizar usuário: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            DB::table('ged_user_roles')->where('user_id', $user->id)->delete();
            $user->delete();

            return redirect()->back()->with('success', 'Usuário excluído com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao excluir usuário: ' . $e->getMessage());
        }
    }
}
