<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Traz os servidores ATIVOS do RH do gpe2 para o cadastro de usuários do GPEDocs, para
 * que possam usar a área do servidor do Portal do Cidadão. Complementa o
 * docs:importar-estrutura (que traz os usuários do sistema gpe2) e roda depois dele.
 *
 *   rh_matricula ativa e sem encerramento → rh_funcionario → pessoa
 *
 * - Servidor que JÁ é usuário (mesmo CPF): só ganha a marca de servidor (funcionário de
 *   origem e matrícula) e o telefone, se não tinha. E-mail, senha e perfil ficam como estão.
 * - Servidor novo: usuário interno da UG da gestora, lotado no departamento da matrícula,
 *   com a MESMA senha (hash bcrypt) do cadastro de RH do gpe2 e o perfil "Servidor" (sem
 *   acesso aos módulos internos). Sem e-mail válido e único na origem, recebe um e-mail
 *   provisório srv-{código aleatório}@servidor.local (nunca o CPF: o e-mail aparece em listas e na
 *   busca), trocado pelo real quando a origem tiver um.
 * - Cargo (rh_matricula → rh_cargo_progressao → rh_cargo), sexo e data de nascimento
 *   (pessoa): o RH é a fonte, atualizam a cada execução.
 * - Foto: o RH do gpe2 guarda a foto do funcionário no próprio GED (path_perfil_img = id do
 *   documento aqui); é ligada ao usuário, sem trocar a que o servidor enviou no GED.
 *
 * Idempotente: rodar de novo atualiza pelo funcionário de origem, sem duplicar. A origem é
 * só lida (gpe_legado); --legado troca o nome do banco.
 *
 *   php artisan docs:importar-servidores --tenant=5 --legado=gpe_gpdtaquaracu
 */
class ImportarServidores extends Command
{
    protected $signature = 'docs:importar-servidores
        {--tenant= : Tenant alvo (id ou subdomínio)}
        {--legado= : Banco de origem (RH do gpe2)}
        {--simular : Só mostra o que faria, sem gravar}';

    protected $description = 'Importa os servidores ativos do RH do gpe2 como usuários do GPEDocs (área do servidor no portal)';

    public const PERFIL_SERVIDOR = 'Servidor';

    private const DOMINIO_PROVISORIO = 'servidor.local';

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
        $this->line(sprintf('Origem: %s %s/%s → destino: tenant #%d %s (%s)%s',
            $origem->getDriverName(), config('database.connections.gpe_legado.host'), $origem->getDatabaseName(),
            $tenant->id, $tenant->nome, $tenant->db_name, $this->option('simular') ? ' — SIMULAÇÃO' : ''));

        $ctx = app(TenantContext::class);
        $ctx->set($tenant);

        try {
            $servidores = $this->servidoresAtivos($origem);
            $resumo = ['marcados' => 0, 'criados' => 0, 'atualizados' => 0, 'email_provisorio' => 0, 'sem_cpf' => 0, 'sem_ug' => 0];

            DB::beginTransaction();
            try {
                $this->importar($servidores, $resumo);
                $this->option('simular') ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            // Fotos depois do commit: mexem em arquivos, que um rollback não desfaz
            $fotos = $this->option('simular') ? ['trazidas' => 0, 'mantidas' => 0, 'sem_arquivo' => 0] : $this->trazerFotos($servidores);

            $this->newLine();
            $this->table(
                ['Servidores ativos', 'Já eram usuários (marcados)', 'Novos usuários', 'Atualizados', 'Com e-mail provisório', 'Sem CPF (ignorados)', 'Sem UG (ignorados)'],
                [[$servidores->count(), $resumo['marcados'], $resumo['criados'], $resumo['atualizados'], $resumo['email_provisorio'], $resumo['sem_cpf'], $resumo['sem_ug']]],
            );
            $this->line(sprintf('Fotos do RH: %d trazidas, %d mantidas (já iguais ou enviadas pelo próprio servidor), %d sem arquivo no GED.',
                $fotos['trazidas'], $fotos['mantidas'], $fotos['sem_arquivo']));

            return self::SUCCESS;
        } finally {
            $ctx->clear();
        }
    }

    /** Um registro por funcionário: a matrícula ativa mais recente define matrícula e lotação. */
    private function servidoresAtivos($origem)
    {
        return $origem->table('rh_matricula as m')
            ->join('rh_funcionario as f', 'f.id', '=', 'm.funcionario_id')
            ->join('pessoa as p', 'p.id', '=', 'f.pessoa_id')
            ->where('m.ativo', 1)
            ->whereNull('m.dt_encerramento_matricula')
            ->orderBy('f.id')
            ->orderByDesc('m.dt_admissao')
            ->orderByDesc('m.id')
            // Cargo pela progressão da matrícula (rh_matricula → rh_cargo_progressao → rh_cargo)
            ->leftJoin('rh_cargo_progressao as cp', 'cp.id', '=', 'm.progressao_id')
            ->leftJoin('rh_cargo as c', 'c.id', '=', 'cp.cargo_id')
            ->get(['f.id as funcionario_id', 'f.password', 'm.matricula', 'm.matricula_dig', 'm.gestora_id',
                'm.departamento_id', 'p.nome', 'p.doc', 'p.email', 'p.celular', 'p.fone',
                'p.sexo', 'p.dt_nascimento', 'c.descricao as cargo', 'f.disk', 'f.path_perfil_img'])
            ->unique('funcionario_id')
            ->values();
    }

    private function importar($servidores, array &$resumo): void
    {
        $ugs = DB::table('ugs')->whereNotNull('legado_orgao_id')->pluck('id', 'legado_orgao_id');
        $perfilId = $this->perfilServidor();

        // E-mail que aparece em mais de um servidor não identifica ninguém: vira provisório.
        $repetidos = $servidores->map(fn ($s) => $this->email($s->email))->filter()->countBy()->filter(fn ($n) => $n > 1);

        $this->components->task("Servidores ({$servidores->count()} ativos na origem)", function () use ($servidores, $ugs, $perfilId, $repetidos, &$resumo) {
            foreach ($servidores as $s) {
                $digitos = preg_replace('/\D/', '', (string) $s->doc);
                if (strlen($digitos) !== 11) {
                    $resumo['sem_cpf']++;
                    continue;
                }
                $ugId = $ugs[(int) $s->gestora_id] ?? null;
                if (! $ugId) {
                    $resumo['sem_ug']++;
                    continue;
                }

                $matricula = $s->matricula_dig !== null ? "{$s->matricula}-{$s->matricula_dig}" : (string) $s->matricula;
                // Cargo, sexo e nascimento: o RH é a fonte — atualiza sempre que tiver valor
                $doRh = $this->dadosDoRh($s);
                $telefone = $this->texto($s->celular ?: $s->fone) ?: null;
                $unidadeId = $s->departamento_id ? DB::table('ug_organograma')
                    ->where(['ug_id' => $ugId, 'legado_tipo' => 'departamento', 'legado_id' => $s->departamento_id])->value('id') : null;

                $usuario = DB::table('users')->where('legado_funcionario_id', $s->funcionario_id)->first()
                    ?? DB::table('users')->whereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?", [$digitos])->first();

                $email = $this->email($s->email);
                $emailLivre = $email && ! isset($repetidos[$email])
                    && ! DB::table('users')->where('email', $email)->when($usuario, fn ($q) => $q->where('id', '<>', $usuario->id))->exists();

                if ($usuario && $usuario->legado_funcionario_id === null) {
                    // Já era usuário: só marca como servidor.
                    DB::table('users')->where('id', $usuario->id)->update(array_filter([
                        'legado_funcionario_id' => (int) $s->funcionario_id,
                        'matricula'             => $matricula,
                        'telefone'              => $usuario->telefone ?: $telefone,
                        'updated_at'            => now(),
                    ] + $doRh, fn ($v) => $v !== null));
                    $resumo['marcados']++;
                    continue;
                }

                if ($usuario) {
                    // Já importado antes: atualiza o cadastro, sem mexer na senha nem no perfil.
                    $dados = [
                        'name'       => $this->texto($s->nome),
                        'matricula'  => $matricula,
                        'telefone'   => $usuario->telefone ?: $telefone,
                        'updated_at' => now(),
                    ] + $doRh;
                    if ($emailLivre && str_ends_with((string) $usuario->email, '@' . self::DOMINIO_PROVISORIO)) {
                        $dados['email'] = $email;
                    }
                    if (str_ends_with($dados['email'] ?? (string) $usuario->email, '@' . self::DOMINIO_PROVISORIO)) {
                        $resumo['email_provisorio']++;
                    }
                    DB::table('users')->where('id', $usuario->id)->update($dados);
                    $resumo['atualizados']++;
                    continue;
                }

                if (! $emailLivre) {
                    // Código aleatório, não o CPF: o e-mail aparece em listas e entra na busca (LGPD).
                    $email = 'srv-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(16)) . '@' . self::DOMINIO_PROVISORIO;
                    $resumo['email_provisorio']++;
                }
                $userId = (int) DB::table('users')->insertGetId([
                    'name'                  => $this->texto($s->nome),
                    'email'                 => $email,
                    'password'              => (string) $s->password,
                    'cpf'                   => $this->cpf($digitos),
                    'telefone'              => $telefone,
                    'tipo'                  => 'interno',
                    'super_admin'           => false,
                    'acesso_geral_ug'       => false,
                    'ug_id'                 => $ugId,
                    'unidade_id'            => $unidadeId,
                    'legado_funcionario_id' => (int) $s->funcionario_id,
                    'matricula'             => $matricula,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ] + $doRh);
                DB::table('user_ugs')->updateOrInsert(['user_id' => $userId, 'ug_id' => $ugId],
                    ['principal' => true, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('ged_user_roles')->insertOrIgnore(['user_id' => $userId, 'role_id' => $perfilId]);
                $resumo['criados']++;
            }

            return true;
        });
    }

    /** Permissões do perfil "Servidor": os processos do setor (abrir, ver e tramitar). */
    private const PERMISSOES_SERVIDOR = ['processo.visualizar', 'processo.criar', 'processo.tramitar'];

    /** Perfil "Servidor": área do servidor no Portal e processos do setor; nada de administração. */
    private function perfilServidor(): int
    {
        $id = DB::table('ged_roles')->where('nome', self::PERFIL_SERVIDOR)->value('id');
        if ($id) {
            return (int) $id;
        }
        $id = DB::table('ged_roles')->insertGetId([
            'nome'       => self::PERFIL_SERVIDOR,
            'descricao'  => 'Servidor municipal: área do servidor no Portal e processos do setor (abrir, ver e tramitar)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (DB::table('ged_permissions')->whereIn('nome', self::PERMISSOES_SERVIDOR)->pluck('id') as $perm) {
            DB::table('ged_role_permissions')->insertOrIgnore(['role_id' => $id, 'permission_id' => $perm]);
        }

        return (int) $id;
    }

    /**
     * Cargo, sexo e data de nascimento do RH (só os que vierem preenchidos).
     *
     * @return array<string, string>
     */
    private function dadosDoRh(object $s): array
    {
        $sexo = mb_strtoupper(trim((string) ($s->sexo ?? '')));

        return array_filter([
            'cargo'           => mb_substr($this->texto($s->cargo ?? null), 0, 120) ?: null,
            'sexo'            => in_array($sexo, ['F', 'M'], true) ? $sexo : null,
            'data_nascimento' => ! empty($s->dt_nascimento) ? substr((string) $s->dt_nascimento, 0, 10) : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * A foto do funcionário o RH do gpe2 guarda no próprio GED (POST
     * /api/integracoes/arquivos): rh_funcionario.path_perfil_img é o id do
     * documento aqui. Liga esse documento ao usuário — sem trocar foto que o
     * servidor enviou no GED (foto_origem nula com foto) nem a já trazida.
     *
     * @return array{trazidas: int, mantidas: int, sem_arquivo: int}
     */
    public function trazerFotos(iterable $servidores): array
    {
        $r = ['trazidas' => 0, 'mantidas' => 0, 'sem_arquivo' => 0];
        $disco = \Illuminate\Support\Facades\Storage::disk('documentos');

        foreach ($servidores as $s) {
            if (($s->disk ?? null) !== 'ged' || ! ctype_digit((string) ($s->path_perfil_img ?? ''))) {
                continue;
            }
            $usuario = \App\Models\User::where('legado_funcionario_id', $s->funcionario_id)->first();
            if (! $usuario) {
                continue;
            }
            $origem = 'rh:' . $s->path_perfil_img;
            if ($usuario->foto_origem === $origem || ($usuario->foto_path && $usuario->foto_origem === null)) {
                $r['mantidas']++;
                continue;
            }
            $caminho = \App\Models\Documento::withoutGlobalScopes()->with('versaoAtual')->find((int) $s->path_perfil_img)?->versaoAtual?->arquivo_path;
            if (! $caminho || ! $disco->exists($caminho)) {
                $r['sem_arquivo']++;
                continue;
            }
            try {
                \App\Support\FotoUsuario::salvarDeBytes($usuario, (string) $disco->get($caminho), $origem);
                $r['trazidas']++;
            } catch (\RuntimeException $e) {
                $r['sem_arquivo']++;   // arquivo que não é imagem
            }
        }

        return $r;
    }

    private function email(?string $s): ?string
    {
        $e = mb_strtolower(trim((string) $s));

        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    private function texto(?string $s): string
    {
        $s = (string) $s;
        if ($s !== '' && ! mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }

        return trim($s);
    }

    private function cpf(string $d): string
    {
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
}
