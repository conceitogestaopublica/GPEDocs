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
 * Importa UGs + organograma do banco gpdsantoantoniodoamparo (GPE legado).
 *
 * Diferenca crucial em relacao ao ImportarGpdParaguacu:
 *   TODOS os usuarios sao vinculados a uma UNICA UG (a principal — UG-001
 *   ou a primeira encontrada). Sem unidade do organograma.
 *
 * Uso:
 *   php artisan import:gpdsantoantonio
 *   php artisan import:gpdsantoantonio --so-users
 *   php artisan import:gpdsantoantonio --so-ugs
 */
class ImportarGpdSantoAntonio extends Command
{
    protected $signature = 'import:gpdsantoantonio
        {--so-ugs : Importa apenas UGs e organograma}
        {--so-users : Importa apenas users (UGs precisam ja existir)}
        {--so-ativos : Importa apenas registros ativos (sem dt_encerramento)}';

    protected $description = 'Importa UGs/organograma/usuarios do banco gpdsantoantoniodoamparo. Vinculos de UG espelhados de usuario_departamento.';

    /** Emails preservados (nao serao tocados se ja existirem) */
    private const PROTEGIDOS = ['joeljardim@gmail.com', 'admin@ged.local'];

    public function handle(): int
    {
        $this->info('Verificando conexao com gpe_legado (esperado: gpdsantoantoniodoamparo)...');
        try {
            $dbName = DB::connection('gpe_legado')->getDatabaseName();
            $count = DB::connection('gpe_legado')->table('gestora')->count();
            $this->info("OK — conectado em [{$dbName}] ({$count} gestoras).");
        } catch (Throwable $e) {
            $this->error('Falha ao conectar em gpe_legado: ' . $e->getMessage());
            $this->warn('Confirme GPE_LEGADO_DATABASE=gpdsantoantoniodoamparo no .env.');
            return self::FAILURE;
        }

        if (! $this->option('so-users')) {
            $this->importarUgsEOrganograma();
        }

        if (! $this->option('so-ugs')) {
            $this->importarUsersComVinculosReais();
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

    private function importarUgsEOrganograma(): void
    {
        $this->info('Importando gestoras como UGs...');

        $apenasAtivos = $this->option('so-ativos');

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
                    'observacoes'   => "Importado de gpdsantoantoniodoamparo — gestora_id={$g->gestora_id}, poder=" . $this->limparTexto($g->poder_nome ?? '') . ", codigo_tce={$g->codigo_tce}",
                ]
            );
            $totalUgs++;

            // Nivel 1: orgaos do mesmo poder
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
                    ['ug_id' => $ug->id, 'legado_id' => $orgao->id, 'legado_tipo' => 'orgao'],
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
                        ['ug_id' => $ug->id, 'legado_id' => $unidade->id, 'legado_tipo' => 'unidade'],
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
                            ['ug_id' => $ug->id, 'legado_id' => $dep->id, 'legado_tipo' => 'departamento'],
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

    /**
     * Importa usuarios espelhando o vinculo real do gpd via `usuario.gestora_id`.
     * (usuario_departamento esta vazia nesse banco — santoantonio nao usa o
     * modulo de protocolo, entao so existe vinculo no nivel da gestora.)
     * Cada user vai para a UG correspondente a sua gestora_id; unidade fica null.
     */
    private function importarUsersComVinculosReais(): void
    {
        $this->info('Importando usuarios com vinculo via usuario.gestora_id...');

        // Mapa: gestora_id (legado) -> Ug local
        $mapaUgs = Ug::whereNotNull('legado_orgao_id')
            ->get(['id', 'legado_orgao_id'])
            ->keyBy('legado_orgao_id');

        $usuarios = DB::connection('gpe_legado')->table('usuario')
            ->join('pessoa', 'pessoa.id', '=', 'usuario.pessoa_id')
            ->select(
                'usuario.id as usuario_id',
                'usuario.email',
                'usuario.password',
                'usuario.ativo',
                'usuario.gestora_id',
                'pessoa.nome',
                'pessoa.doc as cpf',
            )
            ->orderBy('pessoa.nome')
            ->get();

        $totalNovos     = 0;
        $totalAtual     = 0;
        $totalSemEmail  = 0;
        $totalSemVinc   = 0;
        $totalUgsLink   = 0;

        foreach ($usuarios as $u) {
            if (empty($u->email) || ! filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                $totalSemEmail++;
                continue;
            }

            $ug = $u->gestora_id ? ($mapaUgs[$u->gestora_id] ?? null) : null;

            $existente = User::where('email', $u->email)->first();
            if ($existente) {
                $totalAtual++;
                if (! in_array($existente->email, self::PROTEGIDOS, true) && $ug) {
                    $existente->ugs()->syncWithoutDetaching([
                        $ug->id => ['principal' => true],
                    ]);
                    if (! $existente->ug_id) {
                        $existente->update(['ug_id' => $ug->id]);
                    }
                    $totalUgsLink++;
                }
                continue;
            }

            $cpf = $u->cpf ? preg_replace('/\D/', '', $u->cpf) : null;
            if ($cpf && strlen($cpf) !== 11) {
                $cpf = null;
            }

            $novoUser = User::create([
                'name'              => mb_strtoupper($this->limparTexto($u->nome)),
                'email'             => $u->email,
                'cpf'               => $cpf,
                'password'          => $u->password,
                'tipo'              => 'interno',
                'ug_id'             => $ug?->id,
                'unidade_id'        => null,
                'legado_usuario_id' => $u->usuario_id,
                'email_verified_at' => now(),
            ]);

            if ($ug) {
                $novoUser->ugs()->sync([
                    $ug->id => ['principal' => true],
                ]);
                $totalUgsLink++;
            } else {
                $totalSemVinc++;
            }

            $totalNovos++;
        }

        $this->info(sprintf(
            '  %d novo(s) | %d ja existiam | %d sem email | %d sem gestora_id | %d vinculos UG criados.',
            $totalNovos, $totalAtual, $totalSemEmail, $totalSemVinc, $totalUgsLink
        ));
    }

    /**
     * gpe esta em latin1; a conexao tambem foi para latin1, entao o driver
     * entrega bytes em latin1 — convertemos aqui para UTF-8.
     */
    private function limparTexto(?string $txt): string
    {
        if ($txt === null || $txt === '') return '';
        if (! mb_check_encoding($txt, 'UTF-8')) {
            $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
        }
        return trim($txt);
    }

    private function formatarCnpj(string $doc): ?string
    {
        $d = preg_replace('/\D/', '', $doc);
        if (strlen($d) !== 14) return null;
        return sprintf('%s.%s.%s/%s-%s',
            substr($d,0,2), substr($d,2,3), substr($d,5,3), substr($d,8,4), substr($d,12,2));
    }

    private function resolverEndereco(?int $logradouroId, ?string $numero, ?string $compl): array
    {
        if (! $logradouroId) return [];

        // Nesse esquema o municipio vem via bairro (logradouro -> bairro -> municipio).
        $row = DB::connection('gpe_legado')->table('logradouro')
            ->leftJoin('bairro', 'bairro.id', '=', 'logradouro.bairro_id')
            ->leftJoin('municipio', 'municipio.id', '=', 'bairro.municipio_id')
            ->select(
                'logradouro.nome as logradouro',
                'logradouro.cep',
                'bairro.nome as bairro',
                'municipio.nome as cidade',
                'municipio.uf_id as uf',
            )
            ->where('logradouro.id', $logradouroId)
            ->first();

        if (! $row) return [];

        return [
            'cep'        => $row->cep ? $this->limparTexto($row->cep) : null,
            'logradouro' => $row->logradouro ? $this->limparTexto($row->logradouro) : null,
            'bairro'     => $row->bairro ? $this->limparTexto($row->bairro) : null,
            'cidade'     => $row->cidade ? $this->limparTexto($row->cidade) : null,
            'uf'         => $row->uf,
        ];
    }
}
