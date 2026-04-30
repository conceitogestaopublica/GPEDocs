<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ug;
use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Importa UGs reais (gestora), organograma (orgao -> unidade -> departamento)
 * e usuarios do banco gpdparaguacu (GPE legado MySQL) para o GED.
 *
 * Mapeamento corrigido:
 *   gestora      → ugs                       (UG real, com CNPJ proprio)
 *   orgao        → ug_organograma  nivel 1   (filtrado por poder_id da gestora)
 *   unidade      → ug_organograma  nivel 2   (filhos do orgao)
 *   departamento → ug_organograma  nivel 3   (filhos da unidade)
 *   usuario+pessoa → users                   (senha bcrypt copiada direto)
 *
 * Os labels dos niveis sao: "Orgao", "Unidade", "Departamento".
 *
 * Uso:
 *   php artisan import:gpdparaguacu
 *   php artisan import:gpdparaguacu --so-users
 *   php artisan import:gpdparaguacu --limpar
 *   php artisan import:gpdparaguacu --so-ativos
 */
class ImportarGpdParaguacu extends Command
{
    protected $signature = 'import:gpdparaguacu
        {--so-ugs : Importa apenas UGs e organograma}
        {--so-users : Importa apenas users (UGs precisam ja existir)}
        {--limpar : Apaga UGs/organograma e users importados antes (preserva admin/joel)}
        {--so-ativos : Importa apenas registros ativos (sem dt_encerramento)}';

    protected $description = 'Importa UGs (gestora), organograma e usuarios do banco GPE legado (gpdparaguacu)';

    /** Emails preservados durante --limpar */
    private const PROTEGIDOS = ['joeljardim@gmail.com', 'admin@ged.local'];

    public function handle(): int
    {
        $this->info('Verificando conexao com gpe_legado...');
        try {
            $count = DB::connection('gpe_legado')->table('gestora')->count();
            $this->info('OK — gpdparaguacu acessivel (' . $count . ' gestoras)');
        } catch (Throwable $e) {
            $this->error('Falha ao conectar em gpe_legado: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('limpar')) {
            $this->limpar();
        }

        if (! $this->option('so-users')) {
            $this->importarUgsEOrganograma();
        }

        if (! $this->option('so-ugs')) {
            $this->importarUsers();
        }

        $this->newLine();
        $this->info('Importacao concluida.');
        $this->line(sprintf(
            'Totais finais — UGs: %d, organograma: %d, users: %d',
            Ug::count(),
            UgOrganograma::count(),
            User::count(),
        ));

        return self::SUCCESS;
    }

    private function limpar(): void
    {
        $this->warn('Limpando dados anteriores (preservando ' . implode(', ', self::PROTEGIDOS) . ')...');

        // Apaga users importados (todos com legado_usuario_id != null), preservando os protegidos
        DB::table('users')
            ->whereNotIn('email', self::PROTEGIDOS)
            ->delete();

        // Desvincula protegidos de UG/unidade pois vamos apagar
        DB::table('users')
            ->whereIn('email', self::PROTEGIDOS)
            ->update(['ug_id' => null, 'unidade_id' => null]);

        DB::table('ug_organograma')->delete();
        DB::table('ugs')->delete();

        $this->line('  UGs, organograma e users importados removidos.');
    }

    private function importarUgsEOrganograma(): void
    {
        $this->info('Importando gestoras como UGs...');

        $apenasAtivos = $this->option('so-ativos');

        // gestora + pessoa (nome real, CNPJ)
        $gestoras = DB::connection('gpe_legado')->table('gestora')
            ->join('pessoa', 'pessoa.id', '=', 'gestora.pessoa_id')
            ->leftJoin('poder', 'poder.id', '=', 'gestora.poder_id')
            ->select(
                'gestora.id as gestora_id',
                'gestora.poder_id',
                'gestora.codigo_tce',
                'gestora.dt_encerramento',
                'pessoa.nome as nome',
                'pessoa.doc as cnpj',
                'pessoa.logradouro_id',
                'pessoa.numero_lograd',
                'pessoa.comple_lograd',
                'poder.nome as poder_nome',
            )
            ->when($apenasAtivos, fn ($q) => $q->where(function ($q) {
                $q->whereNull('gestora.dt_encerramento')
                  ->orWhere('gestora.dt_encerramento', '>=', now());
            }))
            ->orderBy('gestora.codigo_tce')
            ->get();

        $totalUgs = 0;
        $totalOrg = 0;

        foreach ($gestoras as $g) {
            // codigo: usa o codigo_tce (1, 2, 3) ou o id como fallback
            $codigo = 'UG-' . str_pad((string) ($g->codigo_tce ?? $g->gestora_id), 3, '0', STR_PAD_LEFT);

            $endereco = $this->resolverEndereco($g->logradouro_id, $g->numero_lograd, $g->comple_lograd);

            $ug = Ug::updateOrCreate(
                ['legado_orgao_id' => $g->gestora_id],
                [
                    'codigo'        => $codigo,
                    'nome'          => $this->limparTexto($g->nome),
                    'cnpj'          => $g->cnpj ? $this->formatarCnpj($g->cnpj) : null,
                    'cep'           => $endereco['cep'] ?? null,
                    'logradouro'    => $endereco['logradouro'] ?? null,
                    'numero'        => $g->numero_lograd,
                    'complemento'   => $g->comple_lograd,
                    'bairro'        => $endereco['bairro'] ?? null,
                    'cidade'        => $endereco['cidade'] ?? null,
                    'uf'            => $endereco['uf'] ?? null,
                    'nivel_1_label' => 'Órgão',
                    'nivel_2_label' => 'Unidade',
                    'nivel_3_label' => 'Departamento',
                    'ativo'         => $g->dt_encerramento === null
                                        || strtotime($g->dt_encerramento) >= time(),
                    'observacoes'   => "Importado de gpdparaguacu — gestora_id={$g->gestora_id}, poder={$g->poder_nome}, codigo_tce={$g->codigo_tce}",
                ]
            );
            $totalUgs++;

            // Nivel 1: orgaos do mesmo poder da gestora
            $orgaos = DB::connection('gpe_legado')->table('orgao')
                ->where('poder_id', $g->poder_id)
                ->when($apenasAtivos, fn ($q) => $q->where(function ($q) {
                    $q->whereNull('dt_encerramento')->orWhere('dt_encerramento', '>=', now());
                }))
                ->orderBy('num_orgao')
                ->get();

            foreach ($orgaos as $orgao) {
                $codOrgao = str_pad((string) $orgao->num_orgao, 3, '0', STR_PAD_LEFT);

                $noOrgao = UgOrganograma::updateOrCreate(
                    [
                        'ug_id'       => $ug->id,
                        'legado_id'   => $orgao->id,
                        'legado_tipo' => 'orgao',
                    ],
                    [
                        'nivel'     => 1,
                        'parent_id' => null,
                        'codigo'    => $codOrgao,
                        'nome'      => $this->limparTexto($orgao->nome),
                        'ativo'     => $orgao->dt_encerramento === null
                                        || strtotime($orgao->dt_encerramento) >= time(),
                    ]
                );
                $totalOrg++;

                // Nivel 2: unidades deste orgao
                $unidades = DB::connection('gpe_legado')->table('unidade')
                    ->where('orgao_id', $orgao->id)
                    ->when($apenasAtivos, fn ($q) => $q->where(function ($q) {
                        $q->whereNull('dt_encerramento')->orWhere('dt_encerramento', '>=', now());
                    }))
                    ->orderBy('num_unidade')
                    ->get();

                foreach ($unidades as $unidade) {
                    $codUnidade = str_pad((string) $unidade->num_unidade, 3, '0', STR_PAD_LEFT);

                    $noUnidade = UgOrganograma::updateOrCreate(
                        [
                            'ug_id'       => $ug->id,
                            'legado_id'   => $unidade->id,
                            'legado_tipo' => 'unidade',
                        ],
                        [
                            'nivel'     => 2,
                            'parent_id' => $noOrgao->id,
                            'codigo'    => $codUnidade,
                            'nome'      => $this->limparTexto($unidade->nome),
                            'ativo'     => $unidade->dt_encerramento === null
                                            || strtotime($unidade->dt_encerramento) >= time(),
                        ]
                    );
                    $totalOrg++;

                    // Nivel 3: departamentos desta unidade
                    $departamentos = DB::connection('gpe_legado')->table('departamento')
                        ->where('unidade_id', $unidade->id)
                        ->when($apenasAtivos, fn ($q) => $q->where(function ($q) {
                            $q->whereNull('dt_encerramento')->orWhere('dt_encerramento', '>=', now());
                        }))
                        ->orderBy('num_departamento')
                        ->get();

                    foreach ($departamentos as $dep) {
                        $codDep = str_pad((string) $dep->num_departamento, 3, '0', STR_PAD_LEFT);

                        UgOrganograma::updateOrCreate(
                            [
                                'ug_id'       => $ug->id,
                                'legado_id'   => $dep->id,
                                'legado_tipo' => 'departamento',
                            ],
                            [
                                'nivel'     => 3,
                                'parent_id' => $noUnidade->id,
                                'codigo'    => $codDep,
                                'nome'      => $this->limparTexto($dep->nome),
                                'ativo'     => $dep->dt_encerramento === null
                                                || strtotime($dep->dt_encerramento) >= time(),
                            ]
                        );
                        $totalOrg++;
                    }
                }
            }
        }

        $this->info(sprintf('  %d UGs e %d nos de organograma importadas.', $totalUgs, $totalOrg));
    }

    private function importarUsers(): void
    {
        $this->info('Importando usuarios (com senha bcrypt original)...');

        $usuarios = DB::connection('gpe_legado')->table('usuario')
            ->join('pessoa', 'pessoa.id', '=', 'usuario.pessoa_id')
            ->select(
                'usuario.id as usuario_id',
                'usuario.email',
                'usuario.password',
                'usuario.ativo',
                'usuario.pessoa_id',
                'pessoa.nome',
                'pessoa.doc as cpf',
            )
            ->orderBy('pessoa.nome')
            ->get();

        // Pre-carrega o primeiro vinculo de cada usuario (para ele ir para um departamento)
        $vinculosPorUsuario = DB::connection('gpe_legado')->table('usuario_departamento')
            ->select('usuario_id', 'departamento_id')
            ->orderBy('id')
            ->get()
            ->groupBy('usuario_id')
            ->map(fn ($g) => $g->first()->departamento_id);

        // Mapa: departamento_id (legado) -> no nivel 3 (novo) com ug_id
        $mapaDeptos = UgOrganograma::where('legado_tipo', 'departamento')
            ->get(['id', 'ug_id', 'legado_id'])
            ->keyBy('legado_id');

        $totalNovos     = 0;
        $totalAtual     = 0;
        $totalSemEmail  = 0;
        $totalVinculados = 0;

        foreach ($usuarios as $u) {
            if (empty($u->email) || ! filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                $totalSemEmail++;
                continue;
            }

            $existente = User::where('email', $u->email)->first();
            if ($existente) {
                $totalAtual++;
                continue;
            }

            $cpf = $u->cpf ? preg_replace('/\D/', '', $u->cpf) : null;
            if ($cpf && strlen($cpf) !== 11) {
                $cpf = null;
            }

            // Resolve UG e unidade via primeiro usuario_departamento
            $deptoId = $vinculosPorUsuario[$u->usuario_id] ?? null;
            $no = $deptoId ? ($mapaDeptos[$deptoId] ?? null) : null;

            if ($no) {
                $totalVinculados++;
            }

            $novoUser = User::create([
                'name'              => mb_strtoupper($this->limparTexto($u->nome)),
                'email'             => $u->email,
                'cpf'               => $cpf,
                'password'          => $u->password,
                'tipo'              => 'interno',
                'ug_id'             => $no?->ug_id,
                'unidade_id'        => $no?->id,
                'legado_usuario_id' => $u->usuario_id,
                'email_verified_at' => now(),
            ]);

            // Vincula no pivot multi-UG
            if ($no?->ug_id) {
                $novoUser->ugs()->sync([
                    $no->ug_id => ['principal' => true],
                ]);
            }
            $totalNovos++;
        }

        $this->info(sprintf(
            '  %d novo(s) | %d ja existiam | %d sem email | %d com vinculo de unidade.',
            $totalNovos, $totalAtual, $totalSemEmail, $totalVinculados,
        ));
    }

    /**
     * O banco gpe esta em latin1; nossa conexao tambem foi para latin1, entao
     * o driver entrega bytes em latin1 e convertemos aqui para UTF-8.
     */
    private function limparTexto(?string $txt): string
    {
        if ($txt === null || $txt === '') {
            return '';
        }
        if (mb_check_encoding($txt, 'UTF-8') && ! preg_match('/[\xC0-\xFF]{2,}/', $txt)) {
            return $txt;
        }
        $convertido = @mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
        return $convertido !== false ? $convertido : $txt;
    }

    private function formatarCnpj(string $cnpj): ?string
    {
        $d = preg_replace('/\D/', '', $cnpj);
        if (strlen($d) !== 14) {
            return $cnpj;
        }
        return sprintf('%s.%s.%s/%s-%s',
            substr($d, 0, 2),
            substr($d, 2, 3),
            substr($d, 5, 3),
            substr($d, 8, 4),
            substr($d, 12, 2),
        );
    }

    /**
     * Tenta resolver endereco via tabela logradouro do GPE legado.
     */
    private function resolverEndereco(?int $logradouroId, ?string $numero, ?string $complemento): array
    {
        if (! $logradouroId) {
            return [];
        }
        try {
            $log = DB::connection('gpe_legado')->table('logradouro')
                ->leftJoin('bairro', 'bairro.id', '=', 'logradouro.bairro_id')
                ->leftJoin('cidade', 'cidade.id', '=', 'logradouro.cidade_id')
                ->select(
                    'logradouro.cep',
                    'logradouro.nome as logradouro_nome',
                    'logradouro.tipo',
                    'bairro.nome as bairro',
                    'cidade.nome as cidade',
                    'cidade.uf_id as uf',
                )
                ->where('logradouro.id', $logradouroId)
                ->first();

            if (! $log) {
                return [];
            }
            $logr = trim(($log->tipo ?? '') . ' ' . ($log->logradouro_nome ?? ''));
            return [
                'cep'        => $log->cep ? $this->formatarCep($log->cep) : null,
                'logradouro' => $logr ?: null,
                'bairro'     => $this->limparTexto($log->bairro),
                'cidade'     => $this->limparTexto($log->cidade),
                'uf'         => $log->uf ?: null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function formatarCep(string $cep): string
    {
        $d = preg_replace('/\D/', '', $cep);
        return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : $cep;
    }
}
