<?php

declare(strict_types=1);

namespace App\Http\Controllers\Perfil;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DocumentoPessoa;
use App\Support\FotoUsuario;
use App\Support\HistoricoUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

/**
 * Meu perfil › Meus dados (TR 4.7.10, 4.7.12, 4.7.18, 4.7.20). O servidor
 * altera e-mail, e-mail secundário, celular, foto e senha; nome, CPF,
 * matrícula, cargo e lotação são do cadastro feito pelo administrador.
 */
class PerfilController extends Controller
{
    public function index()
    {
        $u = Auth::user()->load(['ug:id,codigo,nome', 'unidade:id,nome']);

        return Inertia::render('GED/Perfil/MeusDados', [
            'perfil' => [
                'name'             => $u->name,
                'cpf'              => DocumentoPessoa::mascararCpf($u->cpf),
                'matricula'        => $u->matricula,
                'cargo'            => $u->cargo,
                'sexo'             => $u->sexo,
                'data_nascimento'  => $u->data_nascimento?->format('d/m/Y'),
                'lotacao'          => trim(($u->unidade?->nome ?? '') . ($u->ug ? ' · ' . $u->ug->nome : ''), ' ·'),
                'email'            => $u->email,
                'email_secundario' => $u->email_secundario,
                'telefone'         => $u->telefone,
                'foto_url'         => $u->foto_url,
            ],
        ]);
    }

    public function atualizar(Request $request)
    {
        $u = Auth::user();
        $dados = $request->validate([
            'email'            => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
            'email_secundario' => ['nullable', 'email', 'max:255', 'different:email'],
            'telefone'         => ['nullable', 'string', 'max:30'],
            'senha_atual'      => ['nullable', 'string'],
        ], ['email_secundario.different' => 'O e-mail secundário precisa ser diferente do principal.']);

        // Trocar o e-mail de entrada exige a senha: é por ele que se recupera a conta
        if (mb_strtolower($dados['email']) !== mb_strtolower($u->email)) {
            $this->conferirSenha($u, (string) ($dados['senha_atual'] ?? ''));
        }

        $antes = HistoricoUsuario::retrato($u);
        $u->forceFill([
            'email'            => $dados['email'],
            'email_secundario' => $dados['email_secundario'] ?: null,
            'telefone'         => $dados['telefone'] ?: null,
        ])->save();
        HistoricoUsuario::comparar($u, $antes, HistoricoUsuario::retrato($u));

        return back()->with('success', 'Dados atualizados.');
    }

    public function alterarSenha(Request $request)
    {
        $u = Auth::user();
        $dados = $request->validate([
            'senha_atual' => ['required', 'string'],
            'senha'       => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['senha' => 'nova senha']);
        $this->conferirSenha($u, $dados['senha_atual']);

        $u->forceFill(['password' => Hash::make($dados['senha'])])->save();
        HistoricoUsuario::registrar($u, 'Senha', null, 'Alterada pelo próprio servidor');

        return back()->with('success', 'Senha alterada.');
    }

    public function enviarFoto(Request $request)
    {
        $request->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120']]);
        try {
            FotoUsuario::salvar(Auth::user(), $request->file('foto'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['foto' => $e->getMessage()]);
        }
        HistoricoUsuario::registrar(Auth::user(), 'Foto', null, 'Alterada pelo próprio servidor');

        return back()->with('success', 'Foto atualizada.');
    }

    public function removerFoto()
    {
        FotoUsuario::remover(Auth::user());
        HistoricoUsuario::registrar(Auth::user(), 'Foto', null, 'Removida pelo próprio servidor');

        return back()->with('success', 'Foto removida.');
    }

    /** Meu perfil › Histórico: o que mudou no meu cadastro, quando e por quem (TR 4.7.22). */
    public function historico()
    {
        return Inertia::render('GED/Perfil/Historico', ['historico' => HistoricoUsuario::de(Auth::user())]);
    }

    /** Meu perfil › Administradores: a quem recorrer (TR 4.7.21). */
    public function administradores()
    {
        $comPermissao = \Illuminate\Support\Facades\DB::table('ged_user_roles as ur')
            ->join('ged_role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('ged_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.nome', 'admin.usuarios')
            ->select('ur.user_id');

        $admins = User::query()
            ->whereNull('suspenso_em')
            ->where(fn ($q) => $q->where('super_admin', true)->orWhereIn('id', $comPermissao))
            ->with('unidade:id,nome')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'telefone', 'cargo', 'unidade_id', 'super_admin', 'foto_path'])
            ->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'telefone' => $u->telefone,
                'cargo' => $u->cargo, 'setor' => $u->unidade?->nome, 'foto_url' => $u->foto_url,
                'papel' => $u->super_admin ? 'Super administrador' : 'Administrador',
            ]);

        return Inertia::render('GED/Perfil/Administradores', ['administradores' => $admins]);
    }

    /** Foto de qualquer servidor, para quem está logado (aparece na tramitação). */
    public function foto(int $id)
    {
        $caminho = User::whereKey($id)->value('foto_path');
        abort_unless($caminho && Storage::disk('documentos')->exists($caminho), 404);

        return Storage::disk('documentos')->response($caminho, null, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function conferirSenha(User $u, string $senha): void
    {
        if (! Hash::check($senha, (string) $u->password)) {
            throw ValidationException::withMessages(['senha_atual' => 'Senha atual incorreta.']);
        }
    }
}
