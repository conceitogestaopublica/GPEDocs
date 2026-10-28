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
        $ugAtualId = (int) ($request->session()->get('ug_id') ?? 0);
        // Toggle visivel apenas para super_admin: mostrar users de TODAS as UGs
        $todasUgs = $request->boolean('todas_ugs') && (bool) $request->user()?->super_admin;

        $usuarios = User::select('users.*')
            ->with(['roles', 'ug:id,codigo,nome', 'unidade:id,ug_id,nivel,nome'])
            // Filtra por UG ativa (multi-tenant). Super_admin pode optar por ver tudo
            // marcando ?todas_ugs=1; usuarios externos ficam sempre fora desse filtro.
            ->when(! $todasUgs && $ugAtualId, function ($q) use ($ugAtualId) {
                $q->where(function ($q) use ($ugAtualId) {
                    $q->where('users.tipo', 'externo')
                      ->orWhereExists(function ($sub) use ($ugAtualId) {
                          $sub->select(DB::raw(1))
                              ->from('user_ugs')
                              ->whereColumn('user_ugs.user_id', 'users.id')
                              ->where('user_ugs.ug_id', $ugAtualId);
                      });
                });
            })
            ->when($busca !== '', function ($q) use ($busca) {
                $q->where(function ($q) use ($busca) {
                    $termo = "%{$busca}%";
                    $cpfDigits = preg_replace('/\D/', '', $busca);
                    $q->where('users.name', 'like', $termo)
                      ->orWhere('users.email', 'like', $termo);
                    // Só o CPF completo encontra (LGPD): busca parcial permitiria descobrir CPFs por tentativa.
                    if (strlen($cpfDigits) === 11) {
                        // O CPF está gravado com e sem pontuação: compara só os dígitos.
                        $q->orWhereRaw("regexp_replace(users.cpf, '[^0-9]', '', 'g') = ?", [$cpfDigits]);
                    }
                });
            })
            ->when(in_array($tipoFiltro, ['interno', 'externo'], true), fn ($q) => $q->where('users.tipo', $tipoFiltro))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => $u->setAttribute('cpf', \App\Support\DocumentoPessoa::mascararCpf($u->cpf)));

        return Inertia::render('GED/Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            // "Online agora" = alguma atividade nos últimos minutos (middleware BloquearUsuarioSuspenso).
            'online'   => User::whereNull('suspenso_em')->where('ultima_atividade_em', '>=', now()->subMinutes(\App\Http\Middleware\BloquearUsuarioSuspenso::MINUTOS_ONLINE))->count(),
            'minutos_online' => \App\Http\Middleware\BloquearUsuarioSuspenso::MINUTOS_ONLINE,
            'filtros'  => [
                'busca'     => $busca,
                'tipo'      => $tipoFiltro,
                'todas_ugs' => $todasUgs,
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
                'id'              => $usuario->id,
                'name'            => $usuario->name,
                'email'           => $usuario->email,
                'cpf'             => $usuario->cpf,
                'telefone'        => $usuario->telefone,
                'tipo'            => $usuario->tipo,
                'super_admin'     => (bool) $usuario->super_admin,
                'acesso_geral_ug' => (bool) $usuario->acesso_geral_ug,
                'ug_id'           => $usuario->ug_id,        // UG primaria/legada
                'unidade_id'      => $usuario->unidade_id,
                'ug_ids'          => $usuario->ugs->pluck('id')->all(),  // UGs vinculadas (multi)
                'roles'           => $usuario->roles->pluck('id')->all(),
            ],
            // Registro do cadastro e situação do acesso (só leitura na tela).
            'registro' => [
                'criado_em'        => $usuario->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'criado_por'       => $usuario->criado_por ? User::whereKey($usuario->criado_por)->value('name') : null,
                'criado_ip'        => $usuario->criado_ip,
                'criado_navegador' => $usuario->criado_user_agent,
                'ultimo_acesso_em' => $usuario->ultimo_acesso_em?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'suspenso_em'      => $usuario->suspenso_em?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'suspenso_motivo'  => $usuario->suspenso_motivo,
                'dois_fatores'     => $usuario->temDoisFatores(),
            ],
            'roles'    => Role::orderBy('nome')->get(),
            'ugs'      => Ug::where('ativo', true)->orderBy('codigo')->get(['id','codigo','nome','nivel_1_label','nivel_2_label','nivel_3_label']),
            'unidades' => UgOrganograma::where('ativo', true)->orderBy('ug_id')->orderBy('nivel')->orderBy('nome')->get(['id','ug_id','parent_id','nivel','codigo','nome']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validarUsuario($request, criando: true);
        // Só quem já é super admin concede o privilégio — sem isto, qualquer usuário com
        // acesso à tela de usuários se promovia criando uma conta super_admin.
        $validated['super_admin'] = $request->user()->super_admin && ($validated['super_admin'] ?? false);

        $tipo      = $validated['tipo'];
        $unidadeId = $tipo === 'externo' ? null : ($validated['unidade_id'] ?? null);
        $ugIds     = $this->normalizarUgIds($request);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'            => $validated['name'],
                'email'           => $validated['email'],
                'cpf'             => $validated['cpf'] ?? null,
                'telefone'        => $validated['telefone'] ?? null,
                'password'        => Hash::make($validated['password']),
                'tipo'            => $tipo,
                'super_admin'     => (bool) ($validated['super_admin'] ?? false),
                'acesso_geral_ug' => (bool) ($validated['acesso_geral_ug'] ?? false),
                'ug_id'           => $ugIds[0] ?? null,  // UG primaria = primeira do array
                'unidade_id'      => $unidadeId,
            ]);

            // Cadeia de responsabilidade do cadastro: quem, de onde e com que navegador.
            DB::table('users')->where('id', $user->id)->update([
                'criado_por'        => $request->user()->id,
                'criado_ip'         => $request->ip(),
                'criado_user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
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
        $this->bloquearAlteracaoDeSuperAdmin($request, (int) $id);
        $validated = $this->validarUsuario($request, criando: false, userId: (int) $id);
        if (! $request->user()->super_admin) {
            // Mantém o que está gravado: quem não é super admin não concede nem retira.
            $validated['super_admin'] = (bool) User::whereKey($id)->value('super_admin');
        }

        $tipo      = $validated['tipo'];
        $unidadeId = $tipo === 'externo' ? null : ($validated['unidade_id'] ?? null);
        $ugIds     = $this->normalizarUgIds($request);

        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);

            $data = [
                'name'            => $validated['name'],
                'email'           => $validated['email'],
                'cpf'             => $validated['cpf'] ?? null,
                'telefone'        => $validated['telefone'] ?? null,
                'tipo'            => $tipo,
                'super_admin'     => (bool) ($validated['super_admin'] ?? false),
                'acesso_geral_ug' => (bool) ($validated['acesso_geral_ug'] ?? false),
                'ug_id'           => $ugIds[0] ?? null,
                'unidade_id'      => $unidadeId,
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
            'telefone'    => ['nullable', 'string', 'max:30'],
            'password'    => $criando ? ['required', 'string', 'min:8'] : ['nullable', 'string', 'min:8'],
            'tipo'        => ['required', 'in:interno,externo'],
            'super_admin'      => ['boolean'],
            'acesso_geral_ug'  => ['boolean'],
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

    /** Conta super admin só é alterada ou excluída por outro super admin. */
    private function bloquearAlteracaoDeSuperAdmin(Request $request, int $alvoId): void
    {
        if (! $request->user()->super_admin && User::whereKey($alvoId)->value('super_admin')) {
            abort(403, 'Somente um super administrador altera outro super administrador.');
        }
    }

    /**
     * Usuário não é excluído, é suspenso: o histórico de quem tramitou, assinou e despachou continua
     * apontando para a pessoa. Suspenso não entra (LoginController) e perde a sessão aberta
     * (middleware BloquearUsuarioSuspenso). Perfis e vínculos ficam, para a reativação devolver o acesso.
     */
    public function destroy(Request $request, $id)
    {
        $this->bloquearAlteracaoDeSuperAdmin($request, (int) $id);

        $dados = $request->validate(['motivo' => ['nullable', 'string', 'max:500']]);
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'Você não pode suspender o seu próprio acesso.');
        }

        DB::table('users')->where('id', $user->id)->update([
            'suspenso_em'     => now(),
            'suspenso_por'    => $request->user()->id,
            'suspenso_motivo' => $dados['motivo'] ?? null,
        ]);

        return redirect()->back()->with('success', "Acesso de {$user->name} suspenso.");
    }

    public function reativar(Request $request, $id)
    {
        $this->bloquearAlteracaoDeSuperAdmin($request, (int) $id);

        $user = User::findOrFail($id);
        DB::table('users')->where('id', $user->id)->update([
            'suspenso_em' => null, 'suspenso_por' => null, 'suspenso_motivo' => null,
        ]);

        return redirect()->back()->with('success', "Acesso de {$user->name} reativado.");
    }

    /** Servidor perdeu o celular e os códigos de recuperação: volta a entrar só com a senha. */
    public function removerDoisFatores(Request $request, $id)
    {
        $this->bloquearAlteracaoDeSuperAdmin($request, (int) $id);

        $user = User::findOrFail($id);
        $user->forceFill([
            'dois_fatores_segredo' => null, 'dois_fatores_recuperacao' => null,
            'dois_fatores_ativado_em' => null, 'dois_fatores_ultimo_passo' => null,
        ])->save();
        \App\Models\AuditLog::create([
            'usuario_id' => auth()->id(),
            'acao'       => 'usuario_2fa_removido',
            'detalhes'   => ['user_id' => $user->id, 'nome' => $user->name],
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', "Verificação em duas etapas de {$user->name} removida.");
    }
}
