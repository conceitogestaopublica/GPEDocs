<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Painel super-admin no domínio raiz (sem subdomain ou em www/admin).
 *
 * Listagem dos tenants cadastrados, com criação/edição/ativação. Acesso
 * restrito via middleware 'super-admin' aplicado em routes/landlord.php.
 */
class TenantsController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderBy('domain')->get();
        return view('landlord.tenants.index', compact('tenants'));
    }

    /** JSON do tenant para popular o modal de edição (senha não retornada). */
    public function show(int $id)
    {
        $t = Tenant::findOrFail($id);
        return response()->json([
            'id'            => $t->id,
            'domain'        => $t->domain,
            'nome'          => $t->nome,
            'cnpj'          => $t->cnpj,
            'uf'            => $t->uf,
            'db_host'       => $t->db_host,
            'db_port'       => $t->db_port,
            'db_name'       => $t->db_name,
            'db_username'   => $t->db_username,
            'has_password'  => ! empty($t->getRawOriginal('db_password')),
            'active'        => (bool) $t->active,
            'contratado_em' => optional($t->contratado_em)->format('Y-m-d'),
            'encerrado_em'  => optional($t->encerrado_em)->format('Y-m-d'),
            'observacoes'   => $t->observacoes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $semSenha = $request->boolean('db_no_password');

        $v = $request->validate([
            'domain'        => 'required|string|max:100|unique:landlord.tenants,domain',
            'nome'          => 'required|string|max:200',
            'cnpj'          => 'nullable|string|size:14',
            'uf'            => 'nullable|string|size:2',
            'db_host'       => 'required|string|max:100',
            'db_port'       => 'nullable|string|max:5',
            'db_name'       => 'required|string|max:100',
            'db_username'   => 'required|string|max:64',
            'db_password'   => $semSenha ? 'nullable' : 'required|string',
            'contratado_em' => 'nullable|date',
            'encerrado_em'  => 'nullable|date',
            'observacoes'   => 'nullable|string|max:5000',
        ]);

        if ($semSenha) {
            $v['db_password'] = null;
        }

        Tenant::create($v + ['active' => true]);
        Cache::forget("tenant:domain:{$v['domain']}");
        return back()->with('success', 'Tenant criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $t = Tenant::findOrFail($id);
        $semSenha   = $request->boolean('db_no_password');
        $manterSenha = $request->boolean('db_keep_password');

        // Regra: manter senha → não valida nem altera. Sem senha → null. Caso contrário, exige string.
        $regraSenha = match (true) {
            $manterSenha => 'nullable',
            $semSenha    => 'nullable',
            default      => 'required|string',
        };

        $v = $request->validate([
            'domain'        => 'required|string|max:100|unique:landlord.tenants,domain,' . $t->id,
            'nome'          => 'required|string|max:200',
            'cnpj'          => 'nullable|string|size:14',
            'uf'            => 'nullable|string|size:2',
            'db_host'       => 'required|string|max:100',
            'db_port'       => 'nullable|string|max:5',
            'db_name'       => 'required|string|max:100',
            'db_username'   => 'required|string|max:64',
            'db_password'   => $regraSenha,
            'contratado_em' => 'nullable|date',
            'encerrado_em'  => 'nullable|date',
            'observacoes'   => 'nullable|string|max:5000',
        ]);

        $dominioAntigo = $t->domain;

        if ($manterSenha) {
            unset($v['db_password']);          // não toca na coluna
        } elseif ($semSenha) {
            $v['db_password'] = null;
        }

        $t->update($v);

        // Invalida cache do domain antigo e do novo (caso tenha mudado)
        Cache::forget("tenant:domain:{$dominioAntigo}");
        Cache::forget("tenant:domain:{$t->domain}");

        return back()->with('success', 'Tenant atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $t = Tenant::findOrFail($id);
        $dominio = $t->domain;
        $t->delete();
        Cache::forget("tenant:domain:{$dominio}");
        return back()->with('success', "Tenant {$dominio} excluído do catálogo. O banco MariaDB do tenant não foi removido.");
    }

    public function toggle(int $id): RedirectResponse
    {
        $t = Tenant::findOrFail($id);
        $t->update(['active' => ! $t->active]);
        Cache::forget("tenant:domain:{$t->domain}");
        return back()->with('success', $t->active ? 'Tenant ativado.' : 'Tenant desativado.');
    }

    /** Testa conexão com o banco do tenant. */
    public function testConnection(int $id)
    {
        $t = Tenant::findOrFail($id);
        try {
            $pdo = new \PDO(
                "mysql:host={$t->db_host};port={$t->db_port};dbname={$t->db_name}",
                $t->db_username, $t->db_password,
                [\PDO::ATTR_TIMEOUT => 3]
            );
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            return response()->json(['ok' => true, 'version' => $version]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
