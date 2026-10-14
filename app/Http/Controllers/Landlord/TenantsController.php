<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        // Ordena por domínio base e depois subdomínio para o agrupamento na view.
        $tenants = Tenant::orderBy('domain')->orderBy('subdomain')->get();

        return view('landlord.tenants.index', compact('tenants'));
    }

    /** JSON do tenant para popular o modal de edição (senha não retornada). */
    public function show(int $id)
    {
        $t = Tenant::findOrFail($id);

        return response()->json([
            'id' => $t->id,
            'subdomain' => $t->subdomain,
            'domain' => $t->domain,
            'nome' => $t->nome,
            'cnpj' => $t->cnpj,
            'uf' => $t->uf,
            'driver' => $t->driver,
            'db_host' => $t->db_host,
            'db_port' => $t->db_port,
            'db_name' => $t->db_name,
            'db_schema' => $t->db_schema,
            'db_username' => $t->db_username,
            'has_password' => ! empty($t->getRawOriginal('db_password')),
            'active' => (bool) $t->active,
            'contratado_em' => optional($t->contratado_em)->format('Y-m-d'),
            'encerrado_em' => optional($t->encerrado_em)->format('Y-m-d'),
            'observacoes' => $t->observacoes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $semSenha = $request->boolean('db_no_password');

        $driver = $request->input('driver');

        $v = $request->validate([
            // Unicidade por par: (subdomain + driver) e (db_host + driver).
            'subdomain' => ['required', 'string', 'max:100'],
            'domain' => ['required', 'string', 'max:150', Rule::unique('landlord.tenants', 'domain')->where('driver', $request->input('subdomain'))],
            'nome' => 'required|string|max:200',
            'cnpj' => 'nullable|string|size:14',
            'uf' => 'nullable|string|size:2',
            'driver' => 'required|in:mysql,mariadb,pgsql,sqlite,sqlsrv',
            'db_host' => 'required|string|max:200',
            'db_port' => 'nullable|string|max:5',
            'db_name' => 'required|string|max:100',
            'db_schema' => ['nullable', 'string', 'max:64', 'required_if:driver,pgsql', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'db_username' => 'required|string|max:64',
            'db_password' => $semSenha ? 'nullable' : 'required|string',
            'contratado_em' => 'nullable|date',
            'encerrado_em' => 'nullable|date',
            'observacoes' => 'nullable|string|max:5000',
        ], [
            'domain.unique' => 'Já existe um tenant com este subdomínio para o driver selecionado.',
            'db_host.unique' => 'Já existe um tenant com este host para o driver selecionado.',
            'db_schema.required_if' => 'Informe o schema do Postgres (search_path).',
            'db_schema.regex' => 'Schema deve começar com letra/underscore e conter apenas letras, números e underscore.',
        ]);

        if ($semSenha) {
            $v['db_password'] = null;
        }
        // Schema só faz sentido no Postgres — zera para os demais drivers.
        if ($driver !== 'pgsql') {
            $v['db_schema'] = null;
        }

        Tenant::create($v + ['active' => true]);
        Cache::forget("tenant:subdomain:{$v['subdomain']}");

        return back()->with('success', 'Tenant criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $t = Tenant::findOrFail($id);
        $semSenha = $request->boolean('db_no_password');
        $manterSenha = $request->boolean('db_keep_password');

        // Regra: manter senha → não valida nem altera. Sem senha → null. Caso contrário, exige string.
        $regraSenha = match (true) {
            $manterSenha => 'nullable',
            $semSenha => 'nullable',
            default => 'required|string',
        };

        $driver = $request->input('driver');

        $v = $request->validate([
            // Unicidade por par: (domain + driver) e (db_host + driver), ignorando o próprio registro.
            'subdomain' => 'required|string|max:200',
            'domain' => ['required', 'string', 'max:150', Rule::unique('landlord.tenants', 'domain')->where('driver', $request->input('subdomain'))],
            'nome' => 'required|string|max:200',
            'cnpj' => 'nullable|string|size:14',
            'uf' => 'nullable|string|size:2',
            'driver' => 'required|in:mysql,mariadb,pgsql,sqlite,sqlsrv',
            'db_host' => 'required|string|max:200',
            'db_port' => 'nullable|string|max:5',
            'db_name' => 'required|string|max:100',
            'db_schema' => ['nullable', 'string', 'max:64', 'required_if:driver,pgsql', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'db_username' => 'required|string|max:64',
            'db_password' => $regraSenha,
            'contratado_em' => 'nullable|date',
            'encerrado_em' => 'nullable|date',
            'observacoes' => 'nullable|string|max:5000',
        ], [
            'subdomain.unique' => 'Já existe um tenant com este subdomínio para o driver selecionado.',
            'db_host.unique' => 'Já existe um tenant com este host para o driver selecionado.',
            'db_schema.required_if' => 'Informe o schema do Postgres (search_path).',
            'db_schema.regex' => 'Schema deve começar com letra/underscore e conter apenas letras, números e underscore.',
        ]);

        $subdomainAntigo = $t->subdomain;

        if ($manterSenha) {
            unset($v['db_password']);          // não toca na coluna
        } elseif ($semSenha) {
            $v['db_password'] = null;
        }
        if ($driver !== 'pgsql') {
            $v['db_schema'] = null;
        }

        $t->update($v);

        // Invalida cache do subdomain antigo e do novo (caso tenha mudado)
        Cache::forget("tenant:subdomain:{$subdomainAntigo}");
        Cache::forget("tenant:subdomain:{$t->subdomain}");

        return back()->with('success', 'Tenant atualizado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $t = Tenant::findOrFail($id);
        $subdominio = $t->subdomain;
        $t->delete();
        Cache::forget("tenant:subdomain:{$subdominio}");

        return back()->with('success', "Tenant {$subdominio} excluído do catálogo. O banco MariaDB do tenant não foi removido.");
    }

    public function toggle(int $id): RedirectResponse
    {
        $t = Tenant::findOrFail($id);
        $t->update(['active' => ! $t->active]);
        Cache::forget("tenant:subdomain:{$t->subdomain}");

        return back()->with('success', $t->active ? 'Tenant ativado.' : 'Tenant desativado.');
    }

    /**
     * Marca (ou desmarca) o tenant como MATRIZ das tabelas universais.
     *
     * A matriz é o banco modelo de onde as universais — plano de contas, naturezas,
     * fontes, elementos, função/subfunção — são publicadas para os municípios. Quem
     * publica pergunta ao landlord qual é, em vez de receber o nome do banco por
     * parâmetro: um `--source` digitado à mão propagaria o cadastro de um município como
     * se fosse norma, e o erro apareceria semanas depois como "o plano de contas mudou".
     *
     * SÓ UMA matriz existe, garantido por índice único parcial no banco. Por isso a troca
     * é uma transação que primeiro LIMPA a anterior: marcar direto violaria o índice, e o
     * operador veria um erro de constraint em vez da troca que pediu.
     *
     * Ação separada do formulário de propósito — é decisão consequente, não um campo a
     * mais de cadastro.
     */
    public function toggleMatriz(int $id): RedirectResponse
    {
        $t = Tenant::findOrFail($id);

        if ($t->is_matriz) {
            $t->update(['is_matriz' => false]);

            return back()->with('success', "{$t->nome} deixou de ser a matriz. Nenhum tenant é matriz agora — a publicação de universais fica sem origem até que outro seja marcado.");
        }

        $anterior = DB::connection('landlord')->transaction(function () use ($t) {
            $atual = Tenant::where('is_matriz', true)->first();
            if ($atual) {
                $atual->update(['is_matriz' => false]);
            }
            $t->update(['is_matriz' => true]);

            return $atual?->nome;
        });

        return back()->with('success', $anterior
            ? "{$t->nome} agora é a matriz das tabelas universais (antes era {$anterior})."
            : "{$t->nome} agora é a matriz das tabelas universais.");
    }

    /** Testa conexão com o banco do tenant. */
    public function testConnection(int $id): JsonResponse
    {
        $t = Tenant::findOrFail($id);
        try {
            $pdo = new \PDO("{$t->driver}:host={$t->db_host};port={$t->db_port};dbname={$t->db_name}", $t->db_username, $t->db_password,
                [\PDO::ATTR_TIMEOUT => 3, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            // Postgres: schema é obrigatório no cadastro; verifica existência real
            // antes de aplicar o search_path. SET search_path não falha sozinho
            // se o schema não existe — só na próxima resolução de identificador.
            if ($t->driver === 'pgsql' && $t->db_schema) {
                if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $t->db_schema)) {
                    throw new \RuntimeException("Schema inválido: {$t->db_schema}");
                }
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.schemata WHERE schema_name = ?');
                $stmt->execute([$t->db_schema]);
                if (! $stmt->fetchColumn()) {
                    throw new \RuntimeException("Schema '{$t->db_schema}' não existe no banco '{$t->db_name}'.");
                }
                $pdo->exec("SET search_path TO {$t->db_schema}");
            }
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            return response()->json(['ok' => true, 'version' => $version]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
