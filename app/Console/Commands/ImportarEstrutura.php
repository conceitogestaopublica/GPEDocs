<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\TenantAware;
use App\Models\Logradouro;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissoes;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Traz para o banco de um tenant do GPEDocs a estrutura real do município, lida do gpe2
 * (PostgreSQL) ou do GPD legado (MariaDB) — as tabelas são as mesmas nos dois:
 *
 *   gestora                      → UG (endereço normalizado, CNPJ, e-mail institucional)
 *   orgao / unidade / departamento → organograma de 3 níveis de cada UG (pelo poder da gestora)
 *   usuario + pessoa (ativos)    → usuários com a MESMA senha (hash bcrypt) e o vínculo à UG
 *                                  da gestora; super admin → super_admin + Administrador,
 *                                  admin → Administrador, demais → Usuário padrão
 *
 * Depois absorve a UG MODELO criada pelo docs:template-restore: carta de serviços, pastas
 * e usuários passam para a primeira UG importada (a de menor código no TCE), e a modelo
 * sai. Idempotente: rodar de novo atualiza pelo id de origem, sem duplicar.
 *
 * Origem configurada em GPE_LEGADO_* (.env); --legado troca só o nome do banco.
 *
 *   php artisan docs:importar-estrutura --tenant=3 --legado=gpesantoantoniodoamparo
 */
class ImportarEstrutura extends Command
{
    use TenantAware;

    protected $signature = 'docs:importar-estrutura
        {--tenant= : Tenant alvo: id, subdomínio ou sub.domínio (vazio = menu)}
        {--legado= : Banco de origem (perguntado se faltar; obrigatório sem terminal)}
        {--principal= : codigo_tce da gestora que absorve a UG modelo (vazio = menu; padrão: a de mais usuários)}';

    protected $description = 'Importa UGs, organograma e usuários reais do gpe2/GPD para um tenant do GPEDocs';

    private const NIVEIS = ['Órgão', 'Unidade', 'Departamento'];

    public function handle(): int
    {
        $tenant = $this->escolherTenant();
        if (! $tenant) {
            return self::FAILURE;
        }

        $origem = $this->conectarOrigem();
        if (! $origem) {
            return self::FAILURE;
        }

        $codigoPrincipal = $this->escolherPrincipal($origem);
        if ($codigoPrincipal === false) {
            return self::FAILURE;
        }

        $this->line(sprintf('Origem: %s %s/%s → destino: tenant #%d %s (%s)',
            $origem->getDriverName(), config('database.connections.gpe_legado.host'), $origem->getDatabaseName(),
            $tenant->id, $tenant->nome, $tenant->db_name));
        if ($this->temTerminal() && ! $this->confirm('Importar UGs, organograma e usuários para este tenant?', true)) {
            $this->line('Nada foi importado.');

            return self::FAILURE;
        }

        $ctx = app(TenantContext::class);
        $ctx->set($tenant);

        try {
            $ugs = $this->importarUgs($origem);
            $nos = $this->importarOrganograma($origem, $ugs);
            [$usuarios, $ignorados] = $this->importarUsuarios($origem, $ugs);
            $principal = $this->absorverUgModelo($origem, $ugs, $codigoPrincipal);

            $this->newLine();
            $this->table(['UGs', 'Nós do organograma', 'Usuários (ativos)', 'Inativos/sem e-mail ignorados', 'UG principal'],
                [[count($ugs), $nos, $usuarios, $ignorados, $principal]]);

            return self::SUCCESS;
        } finally {
            $ctx->clear();
        }
    }

    /**
     * Dá para perguntar? Sem -n E com terminal. O Symfony não detecta mais a falta de TTY sozinho:
     * em `docker exec` sem -it a pergunta lia EOF e o comando abortava. SHELL_INTERACTIVE força
     * o modo interativo (respostas por pipe).
     */
    private function temTerminal(): bool
    {
        return $this->input->isInteractive() && (stream_isatty(STDIN) || getenv('SHELL_INTERACTIVE') !== false);
    }

    /**
     * Tenant de --tenant (id, subdomínio ou sub.domínio) ou do menu. Um por vez: a origem é o
     * banco de UM município, então "todos" não faz sentido aqui.
     */
    private function escolherTenant(): ?Tenant
    {
        $valor = (string) $this->option('tenant');
        if ($valor === '' && ! $this->temTerminal()) {
            $this->error('Informe o tenant com --tenant=<id> (ou rode com docker exec -it para escolher no menu).');

            return null;
        }
        if ($valor === '') {
            $tenants = Tenant::active()->orderBy('domain')->orderBy('id')->get();
            if ($tenants->isEmpty()) {
                $this->error('Nenhum tenant ativo do GPEDocs ('.config('multitenancy.dominio_base').'). Cadastre com tenant:create.');

                return null;
            }
            $opcoes = $tenants->mapWithKeys(fn (Tenant $t) => [
                "#{$t->id} {$t->nome} — {$t->subdomain}.{$t->domain} ({$t->db_name})" => (string) $t->id,
            ])->all();
            $valor = $opcoes[$this->choice('Tenant de destino', array_keys($opcoes), 0)];
        } elseif ($this->ehTodos($valor)) {
            $this->error('Este comando importa um município por vez: informe o ID do tenant.');

            return null;
        }

        return $this->resolveTenant($valor);
    }

    /**
     * Conexão com o banco de origem: servidor e login de GPE_LEGADO_*, banco de --legado ou
     * perguntado SEM padrão — cada município tem o seu, e um padrão errado importaria a estrutura
     * de outro. Sem terminal (docker exec sem -it, -n) o --legado é obrigatório. Testa a conexão e
     * a tabela `gestora` antes de mexer no tenant.
     */
    private function conectarOrigem(): ?\Illuminate\Database\Connection
    {
        $cfg = config('database.connections.gpe_legado');
        $banco = trim((string) $this->option('legado'));
        if ($banco === '' && ! $this->temTerminal()) {
            $this->error('Informe o banco de origem com --legado=<banco> (ou rode com docker exec -it para ele ser perguntado).');

            return null;
        }
        if ($banco === '') {
            $bancos = $this->bancosDaOrigem();
            if ($bancos !== []) {
                $this->line("Bancos em {$cfg['host']}:{$cfg['port']}: ".implode(', ', $bancos));
            }
            $banco = trim((string) $this->anticipate("Nome do banco de origem em {$cfg['host']}:{$cfg['port']}", $bancos));
        }
        if ($banco === '') {
            $this->error('Informe o nome do banco de origem.');

            return null;
        }
        Config::set('database.connections.gpe_legado.database', $banco);
        DB::purge('gpe_legado');

        try {
            $origem = DB::connection('gpe_legado');
            if (! $origem->getSchemaBuilder()->hasTable('gestora')) {
                $this->error("O banco '{$banco}' não tem a tabela 'gestora': não é um banco do gpe2/GPD.");

                return null;
            }
        } catch (\Throwable $e) {
            $this->error("Não foi possível conectar em {$cfg['driver']} {$cfg['host']}:{$cfg['port']}/{$banco} como '{$cfg['username']}'.");
            $this->line('  Confira GPE_LEGADO_* no .env. Detalhe: '.$e->getMessage());

            return null;
        }

        return $origem;
    }

    /**
     * Bancos do servidor de origem, só para sugerir na pergunta. Falha de conexão aqui não é erro:
     * o nome é digitado e a conexão é testada logo depois, com mensagem própria.
     *
     * @return list<string>
     */
    private function bancosDaOrigem(): array
    {
        // Conecta no banco de manutenção, não no GPE_LEGADO_DATABASE, que pode nem existir neste servidor.
        $pgsql = config('database.connections.gpe_legado.driver') === 'pgsql';
        Config::set('database.connections.gpe_legado.database', $pgsql ? 'postgres' : '');
        DB::purge('gpe_legado');

        try {
            $conexao = DB::connection('gpe_legado');
            $nomes = $pgsql
                ? $conexao->table('pg_database')->whereRaw('not datistemplate')->orderBy('datname')->pluck('datname')
                : collect($conexao->select('SHOW DATABASES'))->map(fn ($r) => array_values((array) $r)[0]);

            return $nomes->reject(fn ($n) => in_array($n, ['postgres', 'information_schema', 'mysql', 'performance_schema', 'sys'], true))
                ->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * codigo_tce da gestora que absorve a UG modelo. --principal é validado; sem ele, o menu
     * lista as gestoras com o nº de usuários e sugere a de mais usuários (a Prefeitura).
     * Retorna null para "a de mais usuários" e false quando o código informado não existe.
     */
    private function escolherPrincipal($origem): string|false|null
    {
        $codigo = $this->option('principal');
        if ($codigo !== null && $codigo !== '') {
            if (! $origem->table('gestora')->where('codigo_tce', $codigo)->exists()) {
                $this->error("Nenhuma gestora com codigo_tce '{$codigo}' na origem.");

                return false;
            }

            return (string) $codigo;
        }
        if (! $this->temTerminal()) {
            return null;
        }

        $gestoras = $origem->table('gestora as g')
            ->join('pessoa as p', 'p.id', '=', 'g.pessoa_id')
            ->leftJoin('usuario as u', 'u.gestora_id', '=', 'g.id')
            ->groupBy('g.id', 'g.codigo_tce', 'p.nome')
            ->orderByRaw('count(u.id) desc')->orderBy('g.codigo_tce')
            ->get(['g.codigo_tce', 'p.nome', DB::raw('count(u.id) as usuarios')])
            ->filter(fn ($g) => $g->codigo_tce !== null)
            ->unique('codigo_tce');
        if ($gestoras->isEmpty()) {
            return null;
        }
        $opcoes = $gestoras->mapWithKeys(fn ($g) => [
            sprintf('%s — %s (%d usuários)', $g->codigo_tce, $this->texto($g->nome), $g->usuarios) => (string) $g->codigo_tce,
        ])->all();

        return $opcoes[$this->choice('Todas as UGs serão importadas. Qual delas recebe o conteúdo da UG modelo (carta de serviços, pastas, admins)?', array_keys($opcoes), 0)];
    }

    /** @return array<int,int> gestora_id de origem → ug_id */
    private function importarUgs($origem): array
    {
        $gestoras = $origem->table('gestora as g')
            ->join('pessoa as p', 'p.id', '=', 'g.pessoa_id')
            ->leftJoin('logradouro as l', 'l.id', '=', 'p.logradouro_id')
            ->leftJoin('bairro as b', 'b.id', '=', 'l.bairro_id')
            ->leftJoin('municipio as m', 'm.id', '=', 'b.municipio_id')
            ->orderBy('g.codigo_tce')
            ->orderBy('g.id')
            ->get(['g.id', 'g.codigo_tce', 'g.dt_encerramento', 'p.nome', 'p.doc', 'p.email',
                'p.numero_lograd', 'p.comple_lograd', 'l.nome as logradouro', 'l.cep', 'b.nome as bairro',
                'm.nome as cidade', 'm.uf_id as uf']);

        // O código do TCE NÃO é único: fundos municipais costumam prestar contas
        // junto com a Prefeitura e repetem o código dela (Taquaraçu: Prefeitura e
        // 4 fundos com codigo_tce=2). A gestora de menor id com aquele código
        // fica com "UG-002"; as demais, "UG-002-{id}" — `ugs.codigo` é único.
        $primeiraDoCodigo = [];
        foreach ($gestoras as $g) {
            $tce = (string) ($g->codigo_tce ?? $g->id);
            $primeiraDoCodigo[$tce] = min($primeiraDoCodigo[$tce] ?? PHP_INT_MAX, (int) $g->id);
        }

        $mapa = [];
        $this->components->task("UGs ({$gestoras->count()} gestoras)", function () use ($gestoras, $primeiraDoCodigo, &$mapa) {
            foreach ($gestoras as $g) {
                $tce = (string) ($g->codigo_tce ?? $g->id);
                $codigo = str_pad($tce, 3, '0', STR_PAD_LEFT)
                    . ($primeiraDoCodigo[$tce] === (int) $g->id ? '' : '-' . $g->id);

                // E-mail também é único: o mesmo e-mail em duas gestoras (fundo que
                // usa o da Prefeitura) fica só na primeira; a outra recebe o marcador.
                $email = filter_var(trim((string) $g->email), FILTER_VALIDATE_EMAIL)
                    ? mb_strtolower(trim($g->email))
                    : null;
                if ($email && DB::table('ugs')->where('email_institucional', $email)
                    ->where(fn ($q) => $q->whereNull('legado_orgao_id')->orWhere('legado_orgao_id', '<>', $g->id))
                    ->exists()) {
                    $email = null;
                }

                $logradouro = Logradouro::resolverFromFlat([
                    'logradouro' => $this->texto($g->logradouro),
                    'bairro'     => mb_convert_case($this->texto($g->bairro), MB_CASE_TITLE),
                    'cidade'     => $this->texto($g->cidade),
                    'uf'         => $g->uf,
                    'cep'        => $g->cep,
                ]);
                $complemento = preg_match('/^\**$/', (string) $g->comple_lograd) ? null : $this->texto($g->comple_lograd);

                DB::table('ugs')->updateOrInsert(['legado_orgao_id' => $g->id], [
                    'codigo'              => 'UG-' . $codigo,
                    'nome'                => $this->texto($g->nome),
                    'cnpj'                => $this->cnpj($g->doc),
                    'logradouro_id'       => $logradouro?->id,
                    'numero'              => $g->numero_lograd ? $this->texto($g->numero_lograd) : null,
                    'complemento'         => $complemento,
                    // Único e obrigatório: sem e-mail na origem vira marcador .invalid (domínio
                    // reservado, nunca entrega) para ser corrigido no cadastro da UG.
                    'email_institucional' => $email ?? 'ug-' . $codigo . '@email-nao-informado.invalid',
                    'nivel_1_label'       => self::NIVEIS[0],
                    'nivel_2_label'       => self::NIVEIS[1],
                    'nivel_3_label'       => self::NIVEIS[2],
                    'ativo'               => $g->dt_encerramento === null || strtotime((string) $g->dt_encerramento) >= time(),
                    'observacoes'         => "Importada de {$this->origemNome()} — gestora_id={$g->id}, codigo_tce={$g->codigo_tce}",
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ]);
                $mapa[(int) $g->id] = (int) DB::table('ugs')->where('legado_orgao_id', $g->id)->value('id');
            }

            return true;
        });

        return $mapa;
    }

    /** Órgãos do mesmo poder da gestora → unidades → departamentos. */
    private function importarOrganograma($origem, array $ugs): int
    {
        $total = 0;
        $poderes = $origem->table('gestora')->pluck('poder_id', 'id');

        $this->components->task('Organograma (órgão → unidade → departamento)', function () use ($origem, $ugs, $poderes, &$total) {
            foreach ($ugs as $gestoraId => $ugId) {
                $orgaos = $origem->table('orgao')->where('poder_id', $poderes[$gestoraId] ?? 0)->orderBy('num_orgao')->get();
                foreach ($orgaos as $o) {
                    $idOrgao = $this->no($ugId, null, 1, 'orgao', $o->id, $o->num_orgao, $o->nome, $o->dt_encerramento);
                    $total++;
                    foreach ($origem->table('unidade')->where('orgao_id', $o->id)->orderBy('num_unidade')->get() as $u) {
                        $fimUnidade = $this->primeiroFim($o->dt_encerramento, $u->dt_encerramento);
                        $idUnidade = $this->no($ugId, $idOrgao, 2, 'unidade', $u->id, $u->num_unidade, $u->nome, $fimUnidade);
                        $total++;
                        foreach ($origem->table('departamento')->where('unidade_id', $u->id)->orderBy('num_departamento')->get() as $d) {
                            $this->no($ugId, $idUnidade, 3, 'departamento', $d->id, $d->num_departamento, $d->nome, $this->primeiroFim($fimUnidade, $d->dt_encerramento));
                            $total++;
                        }
                    }
                }
            }

            return true;
        });

        return $total;
    }

    /**
     * Encerramento efetivo: o setor acaba quando ele ou o de cima acaba (o gpe2 encerra o
     * órgão antigo e deixa os departamentos dele sem data — ficavam ativos sob pai inativo).
     */
    private function primeiroFim($dePai, $proprio): ?string
    {
        $datas = array_filter([$dePai, $proprio], fn ($d) => $d !== null && $d !== '');

        return $datas ? (string) min(array_map(fn ($d) => substr((string) $d, 0, 10), $datas)) : null;
    }

    private function no(int $ugId, ?int $parentId, int $nivel, string $tipo, $legadoId, $numero, ?string $nome, $encerramento): int
    {
        $chave = ['ug_id' => $ugId, 'legado_id' => $legadoId, 'legado_tipo' => $tipo];
        $encerrado = $encerramento !== null && strtotime((string) $encerramento) < time();
        $dados = [
            'parent_id'  => $parentId,
            'nivel'      => $nivel,
            'codigo'     => str_pad((string) $numero, 3, '0', STR_PAD_LEFT),
            'nome'       => $this->texto($nome),
            'updated_at' => now(),
        ];
        $atual = DB::table('ug_organograma')->where($chave)->first(['id']);
        if ($atual) {
            // Encerrado na origem desativa; ativo na origem não reativa o que o GED desativou (TR 4.12.9)
            DB::table('ug_organograma')->where('id', $atual->id)->update($dados + ($encerrado
                ? ['ativo' => false, 'dt_encerramento' => $encerramento] : []));

            return (int) $atual->id;
        }

        return (int) DB::table('ug_organograma')->insertGetId($chave + $dados + [
            'dt_encerramento' => $encerramento,
            'ativo'           => ! $encerrado,
            'created_at'      => now(),
        ]);
    }

    /**
     * Só usuários ATIVOS com e-mail válido: o GED não tem inativação de usuário, então
     * trazer um inativo seria devolver o acesso a ele. Senha = mesmo hash bcrypt da origem.
     *
     * @return array{0:int,1:int} [importados, ignorados]
     */
    private function importarUsuarios($origem, array $ugs): array
    {
        $usuarios = $origem->table('usuario as u')
            ->join('pessoa as p', 'p.id', '=', 'u.pessoa_id')
            ->orderBy('u.id')
            ->get(['u.id', 'u.email as email_usuario', 'p.email as email_pessoa', 'u.password', 'u.ativo',
                'u.gestora_id', 'u.isAdmin as admin', 'u.isSuperAdmin as super', 'p.nome', 'p.doc']);

        $roles = DB::table('ged_roles')->pluck('id', 'nome');
        $importados = 0;
        $ignorados = 0;
        $vistos = [];

        $this->components->task("Usuários ({$usuarios->count()} na origem)", function () use ($usuarios, $ugs, $roles, &$importados, &$ignorados, &$vistos) {
            foreach ($usuarios as $u) {
                $email = mb_strtolower(trim((string) ($u->email_usuario ?: $u->email_pessoa)));
                if ((int) $u->ativo !== 1 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($vistos[$email])) {
                    $ignorados++;
                    continue;
                }
                $vistos[$email] = true;
                $ugId = $ugs[(int) $u->gestora_id] ?? (reset($ugs) ?: null);
                $super = (int) $u->super === 1;

                $cpf = $this->cpf($u->doc);
                $documento = User::documentoDisponivelPara($cpf, $email);
                if ($cpf !== $documento) {
                    $this->warn("{$email}: documento já está em outra conta; importado sem documento.");
                }

                DB::table('users')->updateOrInsert(['email' => $email], [
                    'name'              => $this->texto($u->nome),
                    'password'          => (string) $u->password,
                    'cpf'               => $documento,
                    'tipo'              => 'interno',
                    'super_admin'       => $super,
                    'ug_id'             => $ugId,
                    'legado_usuario_id' => (int) $u->id,
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ]);
                $userId = (int) DB::table('users')->where('email', $email)->value('id');

                if ($ugId) {
                    DB::table('user_ugs')->updateOrInsert(['user_id' => $userId, 'ug_id' => $ugId],
                        ['principal' => true, 'created_at' => now(), 'updated_at' => now()]);
                }

                $perfil = ($super || (int) $u->admin === 1) ? 'Administrador' : Permissoes::PERFIL_PADRAO;
                if (isset($roles[$perfil])) {
                    DB::table('ged_user_roles')->insertOrIgnore(['user_id' => $userId, 'role_id' => $roles[$perfil]]);
                }
                $importados++;
            }

            return true;
        });

        return [$importados, $ignorados];
    }

    /**
     * A UG modelo do template vira a principal real: tudo que estava nela (carta de
     * serviços, pastas, usuários, organograma de exemplo) passa ou sai, e a modelo é apagada.
     */
    private function absorverUgModelo($origem, array $ugs, ?string $codigo): string
    {
        $gestoraPrincipal = $codigo !== null
            ? $origem->table('gestora')->where('codigo_tce', $codigo)->value('id')
            : $origem->table('usuario')->select('gestora_id')->groupBy('gestora_id')->orderByRaw('count(*) desc')->value('gestora_id');
        $principal = $ugs[(int) $gestoraPrincipal] ?? reset($ugs);
        $modelo = DB::table('ugs')->where('codigo', 'UG-MODELO')->value('id');

        if ($modelo && $principal) {
            $this->components->task('Absorver a UG modelo na UG principal', function () use ($modelo, $principal) {
                DB::transaction(function () use ($modelo, $principal) {
                    foreach (['portal_categorias_servicos', 'portal_servicos', 'portal_banners', 'ged_pastas', 'proc_oficio_modelos'] as $tabela) {
                        if (\Illuminate\Support\Facades\Schema::hasTable($tabela)) {
                            DB::table($tabela)->where('ug_id', $modelo)->update(['ug_id' => $principal]);
                        }
                    }
                    // Pastas-modelo do GedSeeder nascem sem UG (CLI, sem sessão): ficariam invisíveis.
                    DB::table('ged_pastas')->whereNull('ug_id')->update(['ug_id' => $principal]);

                    // Inclui quem nasceu sem UG no template (admin@ged.local, dono das pastas-modelo).
                    $semUg = DB::table('users')->whereNull('ug_id')->pluck('id');
                    DB::table('users')->where('ug_id', $modelo)->orWhereNull('ug_id')->update(['ug_id' => $principal]);
                    foreach ($semUg as $userId) {
                        DB::table('user_ugs')->updateOrInsert(['user_id' => $userId, 'ug_id' => $principal],
                            ['principal' => true, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    foreach (DB::table('user_ugs')->where('ug_id', $modelo)->pluck('user_id') as $userId) {
                        DB::table('user_ugs')->updateOrInsert(['user_id' => $userId, 'ug_id' => $principal],
                            ['principal' => true, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    DB::table('user_ugs')->where('ug_id', $modelo)->delete();
                    DB::table('users')->whereIn('unidade_id', DB::table('ug_organograma')->where('ug_id', $modelo)->pluck('id'))
                        ->update(['unidade_id' => null]);
                    DB::table('ug_organograma')->where('ug_id', $modelo)->orderByDesc('nivel')->get()
                        ->each(fn ($n) => DB::table('ug_organograma')->where('id', $n->id)->delete());
                    DB::table('ugs')->where('id', $modelo)->delete();
                });

                return true;
            });

            // O portal público do município responde pelo slug da UG principal.
            if (! DB::table('ugs')->whereNotNull('portal_slug')->exists()) {
                $slug = preg_replace('/[^a-z0-9]/', '', mb_strtolower(\Illuminate\Support\Str::ascii((string) DB::table('ugs')->where('id', $principal)->value('nome'))));
                DB::table('ugs')->where('id', $principal)->update(['portal_slug' => preg_replace('/^(municipiode|prefeituramunicipalde)/', '', $slug)]);
            }
        }

        return (string) DB::table('ugs')->where('id', $principal)->value('nome');
    }

    private function origemNome(): string
    {
        return (string) config('database.connections.gpe_legado.database');
    }

    /** Texto da origem em UTF-8 (o GPD legado é latin1; o gpe2 já é UTF-8). */
    private function texto(?string $s): string
    {
        $s = (string) $s;
        if ($s !== '' && ! mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }

        return trim($s);
    }

    private function cnpj(?string $doc): ?string
    {
        $d = preg_replace('/\D/', '', (string) $doc);

        return strlen($d) === 14
            ? sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12, 2))
            : null;
    }

    private function cpf(?string $doc): ?string
    {
        $d = preg_replace('/\D/', '', (string) $doc);

        return strlen($d) === 11
            ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2)
            : null;
    }
}
