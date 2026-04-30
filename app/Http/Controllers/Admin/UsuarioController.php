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
        $usuario = User::with(['roles', 'ugs'])->findOrFail($id);

        return Inertia::render('Configuracao/Usuarios/Form', [
            'usuario'  => [
                'id'          => $usuario->id,
                'name'        => $usuario->name,
                'email'       => $usuario->email,
                'cpf'         => $usuario->cpf,
                'tipo'        => $usuario->tipo,
                'super_admin' => (bool) $usuario->super_admin,
                'ug_id'       => $usuario->ug_id,        // UG primaria/legada
                'unidade_id'  => $usuario->unidade_id,
                'ug_ids'      => $usuario->ugs->pluck('id')->all(),  // UGs vinculadas (multi)
                'roles'       => $usuario->roles->pluck('id')->all(),
            ],
            'roles'    => Role::orderBy('nome')->get(),
            'ugs'      => Ug::where('ativo', true)->orderBy('codigo')->get(['id','codigo','nome','nivel_1_label','nivel_2_label','nivel_3_label']),
            'unidades' => UgOrganograma::where('ativo', true)->orderBy('ug_id')->orderBy('nivel')->orderBy('nome')->get(['id','ug_id','parent_id','nivel','codigo','nome']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validarUsuario($request, criando: true);

        $tipo      = $validated['tipo'];
        $unidadeId = $tipo === 'externo' ? null : ($validated['unidade_id'] ?? null);
        $ugIds     = $this->normalizarUgIds($request);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'cpf'         => $validated['cpf'] ?? null,
                'password'    => Hash::make($validated['password']),
                'tipo'        => $tipo,
                'super_admin' => (bool) ($validated['super_admin'] ?? false),
                'ug_id'       => $ugIds[0] ?? null,  // UG primaria = primeira do array
                'unidade_id'  => $unidadeId,
            ]);

            // Sincroniza pivot multi-UG
            $this->sincronizarUgs($user, $ugIds);

            // Sync roles
            if (! empty($validated['roles'])) {
                DB::table('ged_user_roles')->insert(
                    collect($validated['roles'])->map(fn ($r) => ['user_id' => $user->id, 'role_id' => $r])->toArray()
                );
            }

            DB::commit();
            return redirect()->route('configuracoes.usuarios.index')->with('success', 'Usuário criado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erro ao criar usuário: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $this->validarUsuario($request, criando: false, userId: $id);

        $tipo      = $validated['tipo'];
        $unidadeId = $tipo === 'externo' ? null : ($validated['unidade_id'] ?? null);
        $ugIds     = $this->normalizarUgIds($request);

        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);

            $data = [
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'cpf'         => $validated['cpf'] ?? null,
                'tipo'        => $tipo,
                'super_admin' => (bool) ($validated['super_admin'] ?? false),
                'ug_id'       => $ugIds[0] ?? null,
                'unidade_id'  => $unidadeId,
            ];

            if (! empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }

            $user->update($data);

            $this->sincronizarUgs($user, $ugIds);

            DB::table('ged_user_roles')->where('user_id', $user->id)->delete();
            if (! empty($validated['roles'])) {
                DB::table('ged_user_roles')->insert(
                    collect($validated['roles'])->map(fn ($r) => ['user_id' => $user->id, 'role_id' => $r])->toArray()
                );
            }

            DB::commit();
            return redirect()->route('configuracoes.usuarios.index')->with('success', 'Usuário atualizado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erro ao atualizar usuário: ' . $e->getMessage());
        }
    }

    private function validarUsuario(Request $request, bool $criando, ?int $userId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255',
                              $criando ? 'unique:users,email' : 'unique:users,email,' . $userId],
            'cpf'         => ['nullable', 'string', 'max:14'],
            'password'    => $criando ? ['required', 'string', 'min:8'] : ['nullable', 'string', 'min:8'],
            'tipo'        => ['required', 'in:interno,externo'],
            'super_admin' => ['boolean'],
            'ug_ids'      => ['nullable', 'array'],
            'ug_ids.*'    => ['integer', 'exists:ugs,id'],
            'unidade_id'  => ['nullable', 'integer', 'exists:ug_organograma,id'],
            'roles'       => ['nullable', 'array'],
            'roles.*'     => ['integer', 'exists:ged_roles,id'],
        ]);
    }

    private function normalizarUgIds(Request $request): array
    {
        $ids = (array) $request->input('ug_ids', []);
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function sincronizarUgs(User $user, array $ugIds): void
    {
        // Sync com pivot — primeiro = principal
        $sync = [];
        foreach ($ugIds as $i => $ugId) {
            $sync[$ugId] = ['principal' => $i === 0];
        }
        $user->ugs()->sync($sync);
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
