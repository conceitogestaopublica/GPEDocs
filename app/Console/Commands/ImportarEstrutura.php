<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Logradouro;
use App\Models\Tenant;
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
    protected $signature = 'docs:importar-estrutura
        {--tenant= : Tenant alvo (id ou subdomínio)}
        {--legado= : Banco de origem (gestoras, organograma e usuários)}
        {--principal= : codigo_tce da gestora que absorve a UG modelo (padrão: a da Prefeitura, maior nº de usuários)}';

    protected $description = 'Importa UGs, organograma e usuários reais do gpe2/GPD para um tenant do GPEDocs';

    private const NIVEIS = ['Órgão', 'Unidade', 'Departamento'];

    public function handle(): int
    {
        $tenant = Tenant::active()->get()->first(fn (Tenant $t) => (string) $t->id === (string) $this->option('tenant')
            || $t->subdomain === $this->option('tenant'));
        if (! $tenant) {
            $this->error('Informe --tenant (id ou subdomínio) de um tenant ativo do GPEDocs.');

            return self::FAILURE;
        }

        if ($banco = $this->option('legado')) {
            Config::set('database.connections.gpe_legado.database', $banco);
            DB::purge('gpe_legado');
        }
        $origem = DB::connection('gpe_legado');
        $this->line(sprintf('Origem: %s %s/%s → destino: tenant #%d %s (%s)',
            $origem->getDriverName(), config('database.connections.gpe_legado.host'), $origem->getDatabaseName(),
            $tenant->id, $tenant->nome, $tenant->db_name));

        $ctx = app(TenantContext::class);
        $ctx->set($tenant);

        try {
            $ugs = $this->importarUgs($origem);
            $nos = $this->importarOrganograma($origem, $ugs);
            [$usuarios, $ignorados] = $this->importarUsuarios($origem, $ugs);
            $principal = $this->absorverUgModelo($origem, $ugs);

            $this->newLine();
            $this->table(['UGs', 'Nós do organograma', 'Usuários (ativos)', 'Inativos/sem e-mail ignorados', 'UG principal'],
                [[count($ugs), $nos, $usuarios, $ignorados, $principal]]);

            return self::SUCCESS;
        } finally {
            $ctx->clear();
        }
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
            ->get(['g.id', 'g.codigo_tce', 'g.dt_encerramento', 'p.nome', 'p.doc', 'p.email',
                'p.numero_lograd', 'p.comple_lograd', 'l.nome as logradouro', 'l.cep', 'b.nome as bairro',
                'm.nome as cidade', 'm.uf_id as uf']);

        $mapa = [];
        $this->components->task("UGs ({$gestoras->count()} gestoras)", function () use ($gestoras, &$mapa) {
            foreach ($gestoras as $g) {
                $logradouro = Logradouro::resolverFromFlat([
                    'logradouro' => $this->texto($g->logradouro),
                    'bairro'     => mb_convert_case($this->texto($g->bairro), MB_CASE_TITLE),
                    'cidade'     => $this->texto($g->cidade),
                    'uf'         => $g->uf,
                    'cep'        => $g->cep,
                ]);
                $complemento = preg_match('/^\**$/', (string) $g->comple_lograd) ? null : $this->texto($g->comple_lograd);

                DB::table('ugs')->updateOrInsert(['legado_orgao_id' => $g->id], [
                    'codigo'              => 'UG-' . str_pad((string) ($g->codigo_tce ?? $g->id), 3, '0', STR_PAD_LEFT),
                    'nome'                => $this->texto($g->nome),
                    'cnpj'                => $this->cnpj($g->doc),
                    'logradouro_id'       => $logradouro?->id,
                    'numero'              => $g->numero_lograd ? $this->texto($g->numero_lograd) : null,
                    'complemento'         => $complemento,
                    // Único e obrigatório: sem e-mail na origem vira marcador .invalid (domínio
                    // reservado, nunca entrega) para ser corrigido no cadastro da UG.
                    'email_institucional' => filter_var(trim((string) $g->email), FILTER_VALIDATE_EMAIL)
                        ? mb_strtolower(trim($g->email))
                        : 'ug-' . str_pad((string) ($g->codigo_tce ?? $g->id), 3, '0', STR_PAD_LEFT) . '@email-nao-informado.invalid',
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
                        $idUnidade = $this->no($ugId, $idOrgao, 2, 'unidade', $u->id, $u->num_unidade, $u->nome, $u->dt_encerramento);
                        $total++;
                        foreach ($origem->table('departamento')->where('unidade_id', $u->id)->orderBy('num_departamento')->get() as $d) {
                            $this->no($ugId, $idUnidade, 3, 'departamento', $d->id, $d->num_departamento, $d->nome, $d->dt_encerramento);
                            $total++;
                        }
                    }
                }
            }

            return true;
        });

        return $total;
    }

    private function no(int $ugId, ?int $parentId, int $nivel, string $tipo, $legadoId, $numero, ?string $nome, $encerramento): int
    {
        DB::table('ug_organograma')->updateOrInsert(
            ['ug_id' => $ugId, 'legado_id' => $legadoId, 'legado_tipo' => $tipo],
            [
                'parent_id'       => $parentId,
                'nivel'           => $nivel,
                'codigo'          => str_pad((string) $numero, 3, '0', STR_PAD_LEFT),
                'nome'            => $this->texto($nome),
                'dt_encerramento' => $encerramento,
                'ativo'           => $encerramento === null || strtotime((string) $encerramento) >= time(),
                'updated_at'      => now(),
                'created_at'      => now(),
            ]
        );

        return (int) DB::table('ug_organograma')
            ->where(['ug_id' => $ugId, 'legado_id' => $legadoId, 'legado_tipo' => $tipo])->value('id');
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

                DB::table('users')->updateOrInsert(['email' => $email], [
                    'name'              => $this->texto($u->nome),
                    'password'          => (string) $u->password,
                    'cpf'               => $this->cpf($u->doc),
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
    private function absorverUgModelo($origem, array $ugs): string
    {
        $codigo = $this->option('principal');
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
