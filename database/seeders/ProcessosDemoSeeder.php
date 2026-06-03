<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ug;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria tipos de processo, etapas e processos exemplo.
 */
class ProcessosDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            $ug = Ug::query()->where('codigo', 'UG-MODELO')->first();
            if (! $ug) {
                $this->command?->warn('[ProcessosDemoSeeder] UG-MODELO nao encontrada.');
                return;
            }

            $maria = User::query()->where('email', 'admin.ug@modelo.local')->first();
            $joao  = User::query()->where('email', 'operador@modelo.local')->first();
            $admin = User::query()->where('email', 'admin@ged.local')->first();

            if (! $maria || ! $admin) {
                $this->command?->warn('[ProcessosDemoSeeder] Usuarios demo nao encontrados — execute UsuariosDemoSeeder antes.');
                return;
            }

            $criador = $maria;

            // ── 1) Tipos de Processo + Etapas ─────────────────────────────
            $tiposConfig = [
                [
                    'nome'             => 'Processo Administrativo',
                    'sigla'            => 'PA',
                    'categoria'        => 'administrativo',
                    'sla_padrao_horas' => 120,
                    'descricao'        => 'Processos administrativos gerais da UG.',
                    'etapas'           => [
                        ['nome' => 'Recebimento',      'ordem' => 1, 'tipo' => 'analise'],
                        ['nome' => 'Análise Técnica',  'ordem' => 2, 'tipo' => 'analise'],
                        ['nome' => 'Parecer Jurídico', 'ordem' => 3, 'tipo' => 'parecer'],
                        ['nome' => 'Decisão',          'ordem' => 4, 'tipo' => 'aprovacao'],
                    ],
                ],
                [
                    'nome'             => 'Licitação Eletrônica',
                    'sigla'            => 'LIC',
                    'categoria'        => 'licitacao',
                    'sla_padrao_horas' => 240,
                    'descricao'        => 'Processos licitatorios na modalidade eletronica.',
                    'etapas'           => [
                        ['nome' => 'Abertura',     'ordem' => 1, 'tipo' => 'analise'],
                        ['nome' => 'Habilitação',  'ordem' => 2, 'tipo' => 'analise'],
                        ['nome' => 'Julgamento',   'ordem' => 3, 'tipo' => 'analise'],
                        ['nome' => 'Homologação',  'ordem' => 4, 'tipo' => 'aprovacao'],
                        ['nome' => 'Adjudicação',  'ordem' => 5, 'tipo' => 'despacho'],
                    ],
                ],
                [
                    'nome'             => 'Requerimento de Cidadão',
                    'sigla'            => 'REQ',
                    'categoria'        => 'atendimento',
                    'sla_padrao_horas' => 72,
                    'descricao'        => 'Solicitacoes feitas por cidadaos via Portal ou presencial.',
                    'etapas'           => [
                        ['nome' => 'Triagem',     'ordem' => 1, 'tipo' => 'analise'],
                        ['nome' => 'Atendimento', 'ordem' => 2, 'tipo' => 'despacho'],
                        ['nome' => 'Conclusão',   'ordem' => 3, 'tipo' => 'arquivamento'],
                    ],
                ],
            ];

            $tiposIds = [];
            foreach ($tiposConfig as $cfg) {
                $tipo = DB::table('proc_tipos_processo')->where('sigla', $cfg['sigla'])->first();
                if (! $tipo) {
                    $tipoId = DB::table('proc_tipos_processo')->insertGetId([
                        'nome'             => $cfg['nome'],
                        'descricao'        => $cfg['descricao'],
                        'sigla'            => $cfg['sigla'],
                        'categoria'        => $cfg['categoria'],
                        'sla_padrao_horas' => $cfg['sla_padrao_horas'],
                        'ativo'            => true,
                        'criado_por'       => $criador->id,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                } else {
                    $tipoId = $tipo->id;
                }

                $tiposIds[$cfg['sigla']] = $tipoId;

                // Etapas (idempotente por ordem dentro do tipo)
                foreach ($cfg['etapas'] as $etapa) {
                    $existe = DB::table('proc_tipo_etapas')
                        ->where('tipo_processo_id', $tipoId)
                        ->where('ordem', $etapa['ordem'])
                        ->exists();
                    if ($existe) {
                        continue;
                    }
                    DB::table('proc_tipo_etapas')->insert([
                        'tipo_processo_id' => $tipoId,
                        'nome'             => $etapa['nome'],
                        'ordem'            => $etapa['ordem'],
                        'tipo'             => $etapa['tipo'],
                        'obrigatorio'      => true,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }

            // ── 2) Processos (instancias) ─────────────────────────────────
            $abertoPorJoao = $joao?->id ?? $maria->id;

            $processosConfig = [
                [
                    'numero_protocolo' => 'PA-2026-00001',
                    'sigla'            => 'PA',
                    'assunto'          => 'Solicitação de revisão de cálculo de IPTU',
                    'status'           => 'em_tramitacao',
                    'aberto_por'       => $maria->id,
                    'requerente_nome'  => 'Carlos Pereira',
                    'requerente_cpf'   => '123.456.789-00',
                    'prioridade'       => 'normal',
                    'concluido_por'    => null,
                    'concluido_em'     => null,
                ],
                [
                    'numero_protocolo' => 'LIC-2026-00001',
                    'sigla'            => 'LIC',
                    'assunto'          => 'Pregão Eletrônico — Material de Limpeza',
                    'status'           => 'em_tramitacao',
                    'aberto_por'       => $maria->id,
                    'requerente_nome'  => null,
                    'requerente_cpf'   => null,
                    'prioridade'       => 'alta',
                    'concluido_por'    => null,
                    'concluido_em'     => null,
                ],
                [
                    'numero_protocolo' => 'REQ-2026-00001',
                    'sigla'            => 'REQ',
                    'assunto'          => 'Solicitação de poda de árvore na Rua das Flores',
                    'status'           => 'concluido',
                    'aberto_por'       => $abertoPorJoao,
                    'requerente_nome'  => 'Beatriz Souza',
                    'requerente_cpf'   => '987.654.321-00',
                    'prioridade'       => 'normal',
                    'concluido_por'    => $abertoPorJoao,
                    'concluido_em'     => now()->subDays(2),
                ],
                [
                    'numero_protocolo' => 'PA-2026-00002',
                    'sigla'            => 'PA',
                    'assunto'          => 'Aposentadoria voluntária — processo de servidor',
                    'status'           => 'aberto',
                    'aberto_por'       => $maria->id,
                    'requerente_nome'  => null,
                    'requerente_cpf'   => null,
                    'prioridade'       => 'normal',
                    'concluido_por'    => null,
                    'concluido_em'     => null,
                ],
                [
                    'numero_protocolo' => 'REQ-2026-00002',
                    'sigla'            => 'REQ',
                    'assunto'          => 'Solicitação de certidão negativa de débitos',
                    'status'           => 'concluido',
                    'aberto_por'       => $abertoPorJoao,
                    'requerente_nome'  => 'Carlos Pereira',
                    'requerente_cpf'   => '123.456.789-00',
                    'prioridade'       => 'normal',
                    'concluido_por'    => $abertoPorJoao,
                    'concluido_em'     => now()->subDays(5),
                ],
            ];

            $criados = 0;
            foreach ($processosConfig as $cfg) {
                $tipoId = $tiposIds[$cfg['sigla']] ?? null;
                if (! $tipoId) {
                    continue;
                }

                $existente = DB::table('proc_processos')->where('numero_protocolo', $cfg['numero_protocolo'])->first();
                if ($existente) {
                    continue;
                }

                $processoId = DB::table('proc_processos')->insertGetId([
                    'ug_id'                 => $ug->id,
                    'numero_protocolo'      => $cfg['numero_protocolo'],
                    'tipo_processo_id'      => $tipoId,
                    'assunto'               => $cfg['assunto'],
                    'descricao'             => null,
                    'requerente_nome'       => $cfg['requerente_nome'],
                    'requerente_cpf'        => $cfg['requerente_cpf'],
                    'setor_origem'          => 'Protocolo geral',
                    'status'                => $cfg['status'],
                    'prioridade'            => $cfg['prioridade'],
                    'aberto_por'            => $cfg['aberto_por'],
                    'concluido_por'         => $cfg['concluido_por'],
                    'concluido_em'          => $cfg['concluido_em'],
                    'created_at'            => now()->subDays(7),
                    'updated_at'            => now(),
                ]);

                // Tramitacoes — busca as primeiras 2 etapas do tipo
                $etapas = DB::table('proc_tipo_etapas')
                    ->where('tipo_processo_id', $tipoId)
                    ->orderBy('ordem')
                    ->limit(2)
                    ->get();

                foreach ($etapas as $idx => $etapa) {
                    $isUltima = ($idx === $etapas->count() - 1);
                    $statusTram = $cfg['status'] === 'concluido'
                        ? 'concluida'
                        : ($isUltima ? 'pendente' : 'concluida');

                    DB::table('proc_tramitacoes')->insert([
                        'processo_id'    => $processoId,
                        'tipo_etapa_id'  => $etapa->id,
                        'ordem'          => $idx + 1,
                        'setor_origem'   => $idx === 0 ? 'Protocolo geral' : 'Análise Técnica',
                        'setor_destino'  => $idx === 0 ? 'Análise Técnica' : 'Decisão',
                        'remetente_id'   => $cfg['aberto_por'],
                        'destinatario_id'=> $admin->id,
                        'status'         => $statusTram,
                        'despacho'       => $idx === 0
                            ? 'Encaminhar para analise tecnica.'
                            : 'Concluida a analise tecnica, encaminhar para decisao.',
                        'despachado_em'  => $statusTram === 'concluida' ? now()->subDays(3 - $idx) : null,
                        'created_at'     => now()->subDays(7 - $idx),
                        'updated_at'     => now(),
                    ]);
                }

                // Historico
                DB::table('proc_historico')->insert([
                    'processo_id' => $processoId,
                    'usuario_id'  => $cfg['aberto_por'],
                    'acao'        => 'criado',
                    'detalhes'    => json_encode(['numero' => $cfg['numero_protocolo']]),
                    'created_at'  => now()->subDays(7),
                ]);
                DB::table('proc_historico')->insert([
                    'processo_id' => $processoId,
                    'usuario_id'  => $cfg['aberto_por'],
                    'acao'        => 'tramitado',
                    'detalhes'    => json_encode(['etapa' => 'Recebimento → Análise Técnica']),
                    'created_at'  => now()->subDays(6),
                ]);

                $criados++;
            }

            $this->command?->info("[ProcessosDemoSeeder] 3 tipos + {$criados} processos criados.");
        });
    }
}
