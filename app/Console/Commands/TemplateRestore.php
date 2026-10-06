<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Database\Seeders\EnderecosDemoSeeder;
use Database\Seeders\GedSeeder;
use Database\Seeders\PortalServicosSeeder;
use Database\Seeders\UgModeloSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Restaura o banco de um tenant PostgreSQL do GPEDocs ao estado de MODELO — o equivalente
 * ao `gpe:template-restore` do gpe2, que substitui o antigo `db:reset-demo`.
 *
 * Diferença de origem: o gpe2 restaura um dump (`modelo.sql`) porque o schema legado não
 * está em migrations. Aqui as migrations CRIAM o schema inteiro, então o "modelo" é:
 *
 *   1. schema  → todas as migrations de database/migrations;
 *   2. estrutura → os seeders de estrutura, chamados um a um (ver SEEDERS);
 *   3. acesso  → os SUPER ADMINS do banco legado MariaDB de Santo Antônio do Amparo
 *                (`usuario.isSuperAdmin = 1`, ativos), com a senha do legado.
 *
 * Não há mais usuário "demo" (UsuariosDemoSeeder): era um cadastro fixo, com senha
 * conhecida, em todo tenant — mesma decisão do gpe2.
 *
 * UM BANCO POR TENANT (não um schema por tenant): o alvo é o `db_name` da linha do tenant, e
 * tudo vive no schema public dele. Se o banco ainda não existe, o comando o CRIA no servidor
 * do tenant (com as credenciais do próprio tenant — o usuário precisa de CREATEDB); nunca
 * dropa banco. O alvo é escolhido entre os tenants PG do GPEDocs no landlord
 * (domain = TENANT_DOMINIO_BASE, driver='pgsql').
 *
 *   docker exec -it app-docs php artisan docs:template-restore
 *   docker exec -it app-docs php artisan docs:template-restore --tenant=paraguacu --force
 *
 * Segurança: se o banco alvo já tiver objetos/dados, mostra o que será destruído e exige
 * confirmação (ou --force). Em produção exige --force sempre. A limpeza é feita tabela a
 * tabela e recria o schema public — não toca no banco nem na locale.
 */
class TemplateRestore extends Command
{
    protected $signature = 'docs:template-restore
        {--tenant= : Tenant alvo: subdomínio, sub.domínio ou ID (vazio = menu)}
        {--legado= : Banco do legado MariaDB de onde vêm os super admins}
        {--force : Não pedir confirmação ao apagar um banco com dados}';

    protected $description = 'Restaura o banco de um tenant do GPEDocs: migrations + seeders de estrutura + super admins do legado';

    /**
     * Piso de sanidade. As migrations criam ~65 tabelas; bem abaixo disso significa que
     * elas não rodaram inteiras — e o schema já foi apagado antes.
     */
    private const MIN_TABELAS = 50;

    /** Banco legado de referência para os super admins (o mesmo usuário Maat em todos). */
    private const LEGADO_ADMINS = 'gpdsantoantoniodoamparo';

    /**
     * Seeders de ESTRUTURA, em ordem de dependência. Os de conteúdo demo (documentos,
     * processos, memorandos, solicitação do portal) ficam de fora: dependiam dos usuários
     * demo, que não existem mais.
     */
    private const SEEDERS = [
        'Tipos documentais, roles, permissões, tags e pastas-modelo' => GedSeeder::class,
        'Endereços base (UF, município, bairro, logradouro)' => EnderecosDemoSeeder::class,
        'UG modelo + organograma' => UgModeloSeeder::class,
        'Portal do cidadão: categorias e serviços' => PortalServicosSeeder::class,
    ];

    /** Role do GED dada aos super admins importados. */
    private const ROLE_ADMIN = 'Administrador';

    public function handle(): int
    {
        if (config('app.env') === 'production' && ! $this->option('force')) {
            $this->error('Este comando NÃO roda em produção sem --force.');

            return self::FAILURE;
        }

        $base = (string) config('multitenancy.dominio_base');
        $tenants = Tenant::active()
            ->where('driver', 'pgsql')   // domínio do GPEDocs: escopo global do model
            ->orderBy('id')
            ->get();
        if ($tenants->isEmpty()) {
            $this->error("Nenhum tenant PostgreSQL (driver='pgsql') ativo do domínio '{$base}' no landlord.");

            return self::FAILURE;
        }

        $tenant = $this->selecionarTenant($tenants);
        if (! $tenant) {
            return self::FAILURE;
        }

        $this->line("Alvo: tenant #{$tenant->id} {$tenant->nome} → {$tenant->db_host}:{$tenant->db_port}/{$tenant->db_name} (login: {$tenant->db_username})");

        // Lê os admins do legado ANTES de apagar qualquer coisa: se o legado estiver
        // inacessível, o tenant fica como está em vez de sair vazio e sem login.
        $legado = (string) ($this->option('legado') ?: self::LEGADO_ADMINS);
        $admins = $this->adminsDoLegado($legado);
        if ($admins === null) {
            return self::FAILURE;
        }
        if ($admins->isEmpty()) {
            $this->error("Nenhum super admin ativo com e-mail em '{$legado}' — o tenant ficaria sem login. Abortando.");

            return self::FAILURE;
        }

        $ctx = app(TenantContext::class);
        $ctx->set($tenant);

        try {
            try {
                DB::connection('tenant')->getPdo();
            } catch (\Throwable $e) {
                // Só cria quando o servidor responde e o banco comprovadamente não existe —
                // pergunta ao pg_database em vez de interpretar a mensagem (o PDO devolve 08006
                // genérico, com texto que muda com a locale do servidor).
                if ($this->bancoExiste($tenant) !== false || ! $this->criarBanco($tenant)) {
                    $this->error("Falha ao conectar em {$tenant->db_host}:{$tenant->db_port}/{$tenant->db_name} como '{$tenant->db_username}'.");
                    $this->error('Detalhe: '.$e->getMessage());

                    return self::FAILURE;
                }
                DB::purge('tenant');
            }

            [$tabelas, $linhas] = $this->inspecionar();

            if ($tabelas > 0 || $linhas > 0) {
                $this->warn("O banco '{$tenant->db_name}' já contém objetos/dados:");
                $this->table(
                    ['Banco', 'Tabelas (public)', 'Linhas (estimado)'],
                    [[$tenant->db_name, $tabelas, number_format($linhas, 0, ',', '.')]]
                );
                $this->warn('Continuar vai APAGAR todo o schema public e recriá-lo do zero.');

                if (! $this->option('force') && ! $this->confirm("Confirma limpar e restaurar '{$tenant->db_name}'?", false)) {
                    $this->line('Operação cancelada.');

                    return self::FAILURE;
                }

                $this->line("Limpando schema public de '{$tenant->db_name}'...");
                $this->limparSchemaPublic(DB::connection('tenant')->getPdo());
            }

            // ── 1. Schema ────────────────────────────────────────────────────────
            $this->line('Criando o schema (migrations)...');
            if ($this->call('migrate', ['--database' => 'tenant', '--force' => true]) !== self::SUCCESS) {
                $this->error('Falha nas migrations — o banco pode ter ficado parcial. Corrija e rode de novo.');

                return self::FAILURE;
            }

            // REDE DE SEGURANÇA: o schema foi apagado acima; migrate "com sucesso" que não
            // criou quase nada deixaria o tenant vazio sem ninguém acusar.
            DB::purge('tenant');
            [$tabelas] = $this->inspecionar();
            if ($tabelas < self::MIN_TABELAS) {
                $this->error("Schema terminou com apenas {$tabelas} tabela(s) — esperado ao menos ".self::MIN_TABELAS.'.');

                return self::FAILURE;
            }
            $this->info("Schema conferido: {$tabelas} tabelas no schema public.");

            // ── 2. Estrutura ─────────────────────────────────────────────────────
            $this->semearEstrutura();

            // ── 3. Acesso: super admins do legado ────────────────────────────────
            $this->importarAdmins($admins);

            $this->newLine();
            $this->info("✓ Tenant #{$tenant->id} ({$tenant->db_name}) restaurado com sucesso.");
            $this->table(
                ['Banco', 'Tabelas (public)', 'UGs', 'Usuários'],
                [[$tenant->db_name, $tabelas, $this->contar('ugs') ?? 'n/d', $this->contar('users') ?? 'n/d']]
            );

            $this->newLine();
            $this->info("Super admins com acesso ao tenant (senhas do legado '{$legado}'):");
            $this->table(['Nome', 'Login (legado)', 'Email'], $admins->map(fn ($a) => [$a->nome, $a->login, $a->email])->all());

            return self::SUCCESS;
        } finally {
            $ctx->clear();
        }
    }

    /**
     * O banco do tenant existe no servidor? null = nem o servidor respondeu (host/senha).
     */
    private function bancoExiste(Tenant $tenant): ?bool
    {
        try {
            return (bool) $this->manutencao()->scalar('SELECT 1 FROM pg_database WHERE datname = ?', [(string) $tenant->db_name]);
        } catch (\Throwable) {
            return null;
        } finally {
            DB::purge('_manutencao');
        }
    }

    /** Conexão no banco de manutenção `postgres` do servidor do tenant, com as credenciais dele. */
    private function manutencao(): \Illuminate\Database\Connection
    {
        Config::set('database.connections._manutencao', ['database' => 'postgres'] + config('database.connections.tenant'));
        DB::purge('_manutencao');

        return DB::connection('_manutencao');
    }

    /**
     * Cria o banco do tenant no servidor dele, conectando no banco de manutenção `postgres`
     * com as credenciais do próprio tenant. Encoding UTF8; o resto (locale/ICU) herda o
     * default do servidor, que é o mesmo dos demais tenants.
     */
    private function criarBanco(Tenant $tenant): bool
    {
        $nome = (string) $tenant->db_name;
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $nome)) {
            $this->error("Nome de banco inválido para criação automática: '{$nome}' (use minúsculas, dígitos e _).");

            return false;
        }

        $this->warn("O banco '{$nome}' não existe em {$tenant->db_host}:{$tenant->db_port}.");
        if (! $this->option('force') && ! $this->confirm("Criar o banco '{$nome}' agora?", true)) {
            return false;
        }

        try {
            $this->manutencao()->statement("CREATE DATABASE \"{$nome}\" ENCODING 'UTF8'");
        } catch (\Throwable $e) {
            $this->error("Não foi possível criar o banco '{$nome}': ".$e->getMessage());

            return false;
        } finally {
            DB::purge('_manutencao');
        }

        $this->info("Banco '{$nome}' criado.");

        return true;
    }

    /** Tenant pelo --tenant (id, subdomínio ou sub.domínio) ou pelo menu. */
    private function selecionarTenant(Collection $tenants): ?Tenant
    {
        $opt = (string) $this->option('tenant');
        if ($opt !== '') {
            $tenant = $tenants->first(fn (Tenant $t) => (string) $t->id === $opt
                || $t->subdomain === $opt
                || "{$t->subdomain}.{$t->domain}" === $opt);
            if (! $tenant) {
                $this->error("Tenant '{$opt}' não encontrado entre os tenants PG ativos do GPEDocs.");
            }

            return $tenant;
        }

        $rotulos = $tenants
            ->mapWithKeys(fn (Tenant $t) => [(string) $t->id => "#{$t->id} {$t->nome} — {$t->db_name} — {$t->subdomain}{$t->domain}"])
            ->all();

        $escolhido = $this->choice('Selecione o tenant PostgreSQL', array_values($rotulos));
        $id = (int) array_search($escolhido, $rotulos, true);

        return $tenants->firstWhere('id', $id);
    }

    /**
     * Roda cada seeder de estrutura isolado, na conexão tenant (default do processo após
     * TenantContext::set). __invoke respeita o WithoutModelEvents dos seeders.
     */
    private function semearEstrutura(): void
    {
        foreach (self::SEEDERS as $rotulo => $classe) {
            $this->components->task($rotulo, function () use ($classe) {
                (new $classe)->setContainer($this->laravel)->setCommand($this)->__invoke();

                return true;
            });
        }
    }

    /**
     * Super admins ativos do legado: um por e-mail (o de menor id), com nome/CPF da pessoa
     * e o hash bcrypt do legado — o Laravel valida `$2y$` direto, a senha continua a mesma.
     *
     * @return Collection<int,object>|null null = legado inacessível
     */
    private function adminsDoLegado(string $banco): ?Collection
    {
        Config::set('database.connections.gpe_legado.database', $banco);
        if ((string) config('database.connections.gpe_legado.password') === '' && $this->input->isInteractive()) {
            $senha = (string) $this->secret('Senha do legado ('.config('database.connections.gpe_legado.username').'@'
                .config('database.connections.gpe_legado.host').') — vazio p/ tentar sem senha');
            Config::set('database.connections.gpe_legado.password', $senha);
        }
        DB::purge('gpe_legado');

        try {
            // Query builder (e não SQL cru): o nome camelCase "isSuperAdmin" precisa ser
            // citado no PostgreSQL do gpe2 e o builder cita conforme o driver.
            $rows = DB::connection('gpe_legado')->table('usuario as u')
                ->join('pessoa as p', 'p.id', '=', 'u.pessoa_id')
                ->where('u.isSuperAdmin', 1)
                ->where('u.ativo', 1)
                ->orderBy('u.id')
                ->get(['u.id', 'u.login', 'u.email as email_usuario', 'p.email as email_pessoa', 'p.nome', 'p.doc', 'u.password'])
                ->each(function ($r) {
                    $r->email = trim((string) ($r->email_usuario ?: $r->email_pessoa));
                })
                ->all();
        } catch (\Throwable $e) {
            $this->error("Não foi possível ler os super admins do legado '{$banco}' em "
                .config('database.connections.gpe_legado.host').'. Nada foi alterado no tenant.');
            $this->line('  Confira GPE_LEGADO_HOST/USERNAME/PASSWORD no .env. Detalhe: '.$e->getMessage());

            return null;
        } finally {
            DB::purge('gpe_legado');
        }

        return collect($rows)
            ->filter(fn ($r) => filter_var((string) $r->email, FILTER_VALIDATE_EMAIL))
            ->map(function ($r) {
                $r->email = mb_strtolower((string) $r->email);

                return $r;
            })
            ->unique('email')
            ->values();
    }

    /**
     * Grava os super admins no tenant: `users` (super_admin, vinculado à UG modelo, com
     * legado_usuario_id), pivot `user_ugs` e a role Administrador do GED. Upsert por e-mail.
     */
    private function importarAdmins(Collection $admins): void
    {
        $this->components->task("Super admins do legado ({$admins->count()})", function () use ($admins) {
            DB::transaction(function () use ($admins) {
                $conn = DB::connection('tenant');
                $ugId = $conn->table('ugs')->where('codigo', 'UG-MODELO')->value('id');
                $roleId = $conn->table('ged_roles')->where('nome', self::ROLE_ADMIN)->value('id');

                foreach ($admins as $a) {
                    $conn->table('users')->updateOrInsert(['email' => $a->email], [
                        'name' => (string) $a->nome,
                        'password' => (string) $a->password,
                        'cpf' => $this->formatarCpf($a->doc),
                        'tipo' => 'interno',
                        'super_admin' => true,
                        'ug_id' => $ugId,
                        'legado_usuario_id' => (int) $a->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $userId = $conn->table('users')->where('email', $a->email)->value('id');

                    if ($ugId) {
                        $conn->table('user_ugs')->updateOrInsert(
                            ['user_id' => $userId, 'ug_id' => $ugId],
                            ['principal' => true, 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                    if ($roleId) {
                        $conn->table('ged_user_roles')->updateOrInsert(['user_id' => $userId, 'role_id' => $roleId]);
                    }
                }
            });

            return true;
        });
    }

    /** CPF de 11 dígitos no formato da tela (000.000.000-00); outro documento vira null. */
    private function formatarCpf(?string $doc): ?string
    {
        $d = preg_replace('/\D/', '', (string) $doc);

        return strlen($d) === 11
            ? substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2)
            : null;
    }

    /** [nº de tabelas base em public, nº estimado de linhas vivas] na conexão tenant. */
    private function inspecionar(): array
    {
        $conn = DB::connection('tenant');
        $tabelas = (int) ($conn->selectOne(
            'SELECT count(*) AS c FROM information_schema.tables '
            ."WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
        )->c ?? 0);
        $linhas = (int) ($conn->selectOne(
            'SELECT COALESCE(SUM(n_live_tup), 0) AS c FROM pg_stat_user_tables'
        )->c ?? 0);

        return [$tabelas, $linhas];
    }

    /** Contagem exata de uma tabela sentinela (null se não existir). */
    private function contar(string $tabela): ?int
    {
        try {
            return (int) (DB::connection('tenant')->selectOne("SELECT count(*) AS c FROM {$tabela}")->c ?? 0);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Limpa o schema public tabela a tabela (cada DROP é autocommit → poucos locks por vez)
     * e só então recria o schema já quase vazio — um DROP SCHEMA CASCADE único bloqueia
     * todo objeto numa só transação.
     */
    private function limparSchemaPublic(\PDO $pdo): void
    {
        $tabelas = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tabelas as $t) {
            $pdo->exec('DROP TABLE IF EXISTS "public"."'.str_replace('"', '""', (string) $t).'" CASCADE');
        }
        $pdo->exec('DROP SCHEMA public CASCADE');
        $pdo->exec('CREATE SCHEMA public');
    }
}
