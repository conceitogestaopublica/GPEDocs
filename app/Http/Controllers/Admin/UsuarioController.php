<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Ug;
use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $busca = trim((string) $request->input('busca', ''));
        $tipoFiltro = $request->input('tipo'); // null | 'interno' | 'externo'

        $usuarios = User::select('users.*')
            ->with(['roles', 'ug:id,codigo,nome', 'unidade:id,ug_id,nivel,nome'])
            ->when($busca !== '', function ($q) use ($busca) {
                $q->where(function ($q) use ($busca) {
                    $termo = "%{$busca}%";
                    $cpfDigits = preg_replace('/\D/', '', $busca);
                    $q->where('users.name', 'like', $termo)
                      ->orWhere('users.email', 'like', $termo);
                    if ($cpfDigits !== '') {
                        $q->orWhere('users.cpf', 'like', "%{$cpfDigits}%");
                    }
                });
            })
            ->when(in_array($tipoFiltro, ['interno', 'externo'], true), fn ($q) => $q->where('users.tipo', $tipoFiltro))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('GED/Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'filtros'  => [
                'busca' => $busca,
                'tipo'  => $tipoFiltro,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Configuracao/Usuarios/Form', [
            'usuario'  => null,
            'roles'    => Role::orderBy('nome')->get(),
            'ugs'      => Ug::where('ativo', true)->orderBy('codigo')->get(['id','codigo','nome','nivel_1_label','nivel_2_label','nivel_3_label']),
            'unidades' => UgOrganograma::where('ativo', true)->orderBy('ug_id')->orderBy('nivel')->orderBy('nome')->get(['id','ug_id','parent_id','nivel','codigo','nome']),
        ]);
    }

    public function edit($id): Response
    {
        $usuario = User::with('roles')->findOrFail($id);

        return Inertia::render('Configuracao/Usuarios/Form', [
            'usuario'  => [
                'id'         => $usuario->id,
                'name'       => $usuario->name,
                'email'      => $usuario->email,
                'cpf'        => $usuario->cpf,
                'tipo'       => $usuario->tipo,
                'ug_id'      => $usuario->ug_id,
                'unidade_id' => $usuario->unidade_id,
                'roles'      => $usuario->roles->pluck('id')->all(),
            ],
            'roles'    => Role::orderBy('nome')->get(),
            'ugs'      => Ug::where('ativo', true)->orderBy('codigo')->get(['id','codigo','nome','nivel_1_label','nivel_2_label','nivel_3_label']),
            'unidades' => UgOrganograma::where('ativo', true)->orderBy('ug_id')->orderBy('nivel')->orderBy('nome')->get(['id','ug_id','parent_id','nivel','codigo','nome']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'cpf'        => ['nullable', 'string', 'max:14'],
            'password'   => ['required', 'string', 'min:8'],
            'tipo'       => ['required', 'in:interno,externo'],
            'ug_id'      => ['nullable', 'integer', 'exists:ugs,id'],
            'unidade_id' => ['nullable', 'integer', 'exists:ug_organograma,id'],
            'roles'      => ['nullable', 'array'],
            'roles.*'    => ['integer', 'exists:ged_roles,id'],
        ]);

        // Internos podem ter unidade; externos nunca
        $tipo = $request->input('tipo');
        $unidadeId = $tipo === 'externo' ? null : $request->input('unidade_id');

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'       => $request->input('name'),
                'email'      => $request->input('email'),
                'cpf'        => $request->input('cpf'),
                'password'   => Hash::make($request->input('password')),
                'tipo'       => $tipo,
                'ug_id'      => $request->input('ug_id'),
                'unidade_id' => $unidadeId,
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

            return redirect()->route('configuracoes.usuarios.index')->with('success', 'Usuário criado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao criar usuário: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'cpf'        => ['nullable', 'string', 'max:14'],
            'password'   => ['nullable', 'string', 'min:8'],
            'tipo'       => ['required', 'in:interno,externo'],
            'ug_id'      => ['nullable', 'integer', 'exists:ugs,id'],
            'unidade_id' => ['nullable', 'integer', 'exists:ug_organograma,id'],
            'roles'      => ['nullable', 'array'],
            'roles.*'    => ['integer', 'exists:ged_roles,id'],
        ]);

        $tipo = $request->input('tipo');
        $unidadeId = $tipo === 'externo' ? null : $request->input('unidade_id');

        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);

            $data = [
                'name'       => $request->input('name'),
                'email'      => $request->input('email'),
                'cpf'        => $request->input('cpf'),
                'tipo'       => $tipo,
                'ug_id'      => $request->input('ug_id'),
                'unidade_id' => $unidadeId,
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

            return redirect()->route('configuracoes.usuarios.index')->with('success', 'Usuário atualizado com sucesso.');
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
