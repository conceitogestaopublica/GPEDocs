<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ug;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria documentos exemplo dentro das pastas do GedSeeder.
 * Atualiza as pastas existentes para terem ug_id da UG modelo.
 */
class DocumentosDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            $ug = Ug::query()->where('codigo', 'UG-MODELO')->first();
            if (! $ug) {
                $this->command?->warn('[DocumentosDemoSeeder] UG-MODELO nao encontrada.');
                return;
            }

            $admin = User::query()->where('email', 'admin@ged.local')->first();
            if (! $admin) {
                $this->command?->warn('[DocumentosDemoSeeder] admin@ged.local nao encontrado — execute GedSeeder antes.');
                return;
            }

            // 1) Atualiza pastas existentes para terem ug_id da UG modelo
            DB::table('ged_pastas')->whereNull('ug_id')->update(['ug_id' => $ug->id]);

            // 2) Mapas de tipos documentais e pastas
            $tipoIds = DB::table('ged_tipos_documentais')->pluck('id', 'nome')->toArray();
            $pastaIds = DB::table('ged_pastas')->pluck('id', 'nome')->toArray();
            $tagIds = DB::table('ged_tags')->pluck('id', 'nome')->toArray();

            $documentos = [
                [
                    'nome'          => 'Portaria 001-2026 - Nomeacao de Servidores',
                    'descricao'     => 'Nomeia servidores aprovados em concurso publico.',
                    'tipo'          => 'Portaria',
                    'pasta'         => 'Portarias',
                    'classificacao' => 'publico',
                    'metadados'     => [
                        'numero'           => '001/2026',
                        'data_publicacao'  => '2026-04-15',
                    ],
                    'tags'          => ['Aprovado'],
                ],
                [
                    'nome'          => 'Decreto 015-2026 - Calendario Municipal',
                    'descricao'     => 'Define feriados e pontos facultativos.',
                    'tipo'          => 'Decreto',
                    'pasta'         => 'Administrativo',
                    'classificacao' => 'publico',
                    'metadados'     => [
                        'numero'          => '015/2026',
                        'data_publicacao' => '2026-01-10',
                    ],
                    'tags'          => ['Aprovado'],
                ],
                [
                    'nome'          => 'Lei Municipal 4023-2026 - IPTU',
                    'descricao'     => 'Atualiza a tabela do IPTU para o exercicio de 2026.',
                    'tipo'          => 'Lei',
                    'pasta'         => 'Administrativo',
                    'classificacao' => 'publico',
                    'metadados'     => [
                        'numero'          => '4023/2026',
                        'data_publicacao' => '2026-01-05',
                    ],
                    'tags'          => ['Aprovado', 'Arquivo Permanente'],
                ],
                [
                    'nome'          => 'Contrato 042-2026 - Limpeza Urbana',
                    'descricao'     => 'Contratacao de empresa para servicos de limpeza urbana.',
                    'tipo'          => 'Contrato',
                    'pasta'         => 'Contratos',
                    'classificacao' => 'interno',
                    'metadados'     => [
                        'numero_contrato' => '042/2026',
                        'contratado'      => 'Empresa Modelo Limpeza LTDA',
                        'valor'           => '2400000.00',
                        'vigencia_inicio' => '2026-03-01',
                        'vigencia_fim'    => '2027-02-28',
                    ],
                    'tags'          => ['Em Tramitacao'],
                ],
                [
                    'nome'          => 'Contrato 043-2026 - Fornecimento de Combustivel',
                    'descricao'     => 'Fornecimento de combustivel para a frota municipal.',
                    'tipo'          => 'Contrato',
                    'pasta'         => 'Contratos',
                    'classificacao' => 'interno',
                    'metadados'     => [
                        'numero_contrato' => '043/2026',
                        'contratado'      => 'Posto Central Combustiveis LTDA',
                        'valor'           => '780000.00',
                        'vigencia_inicio' => '2026-04-01',
                        'vigencia_fim'    => '2027-03-31',
                    ],
                    'tags'          => ['Em Tramitacao', 'Urgente'],
                ],
                [
                    'nome'          => 'NF-e 12345 - Material Escritorio',
                    'descricao'     => 'Nota fiscal de aquisicao de material de escritorio.',
                    'tipo'          => 'Nota Fiscal',
                    'pasta'         => 'Notas Fiscais',
                    'classificacao' => 'interno',
                    'metadados'     => [
                        'numero_nf'     => '12345',
                        'fornecedor'    => 'Papelaria Central LTDA',
                        'valor'         => '4587.90',
                        'data_emissao'  => '2026-04-20',
                    ],
                    'tags'          => ['Aprovado'],
                ],
                [
                    'nome'          => 'Oficio 088-2026 - Convite para Audiencia',
                    'descricao'     => 'Convite para audiencia publica sobre o Plano Diretor.',
                    'tipo'          => 'Oficio',
                    'pasta'         => 'Oficios',
                    'classificacao' => 'publico',
                    'metadados'     => [
                        'numero'        => '088/2026',
                        'destinatario'  => 'Camara Municipal',
                        'data_emissao'  => '2026-04-22',
                    ],
                    'tags'          => ['Em Tramitacao'],
                ],
                [
                    'nome'          => 'Ata da Reuniao 15-04-2026',
                    'descricao'     => 'Ata da reuniao do conselho municipal.',
                    'tipo'          => 'Ata',
                    'pasta'         => 'Administrativo',
                    'classificacao' => 'interno',
                    'metadados'     => [
                        'data_reuniao'  => '2026-04-15',
                        'local'         => 'Sala de Reunioes — Sede da Prefeitura',
                        'participantes' => 'Secretarios municipais e gestores',
                    ],
                    'tags'          => ['Revisao Pendente'],
                ],
                [
                    'nome'          => 'Relatorio de Atividades 1T-2026',
                    'descricao'     => 'Relatorio trimestral das atividades das secretarias.',
                    'tipo'          => 'Relatorio',
                    'pasta'         => 'Administrativo',
                    'classificacao' => 'interno',
                    'metadados'     => [
                        'periodo'    => '1T/2026',
                        'responsavel' => 'Secretaria de Administracao',
                    ],
                    'tags'          => ['Aprovado'],
                ],
                [
                    'nome'          => 'Alvara 2026-LIC-0042',
                    'descricao'     => 'Alvara de funcionamento expedido para estabelecimento comercial.',
                    'tipo'          => 'Alvara',
                    'pasta'         => 'Administrativo',
                    'classificacao' => 'publico',
                    'metadados'     => [
                        'numero'        => '2026-LIC-0042',
                        'requerente'    => 'Comercial Centro LTDA',
                        'data_emissao'  => '2026-04-10',
                    ],
                    'tags'          => ['Aprovado'],
                ],
            ];

            $criados = 0;
            foreach ($documentos as $doc) {
                $tipoId = $tipoIds[$doc['tipo']] ?? null;
                $pastaId = $pastaIds[$doc['pasta']] ?? null;

                if (! $pastaId) {
                    $this->command?->warn("[DocumentosDemoSeeder] Pasta '{$doc['pasta']}' nao encontrada — pulando '{$doc['nome']}'.");
                    continue;
                }

                // Idempotencia: nao recriar o mesmo documento
                $existente = DB::table('ged_documentos')
                    ->where('ug_id', $ug->id)
                    ->where('nome', $doc['nome'])
                    ->first();

                if ($existente) {
                    continue;
                }

                $tamanho = random_int(120000, 1500000);

                $docId = DB::table('ged_documentos')->insertGetId([
                    'ug_id'              => $ug->id,
                    'nome'               => $doc['nome'],
                    'descricao'          => $doc['descricao'],
                    'tipo_documental_id' => $tipoId,
                    'pasta_id'           => $pastaId,
                    'versao_atual'       => 1,
                    'tamanho'            => $tamanho,
                    'mime_type'          => 'application/pdf',
                    'autor_id'           => $admin->id,
                    'status'             => 'ativo',
                    'classificacao'      => $doc['classificacao'],
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                // Versao 1
                DB::table('ged_versoes')->insert([
                    'documento_id' => $docId,
                    'versao'       => 1,
                    'arquivo_path' => 'demo/'.Str::slug($doc['nome']).'.pdf',
                    'tamanho'      => $tamanho,
                    'hash_sha256'  => hash('sha256', $doc['nome']),
                    'autor_id'     => $admin->id,
                    'comentario'   => 'Versao inicial (seed demo).',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // Metadados
                foreach ($doc['metadados'] as $chave => $valor) {
                    DB::table('ged_metadados')->insert([
                        'documento_id' => $docId,
                        'chave'        => $chave,
                        'valor'        => (string) $valor,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }

                // Tags
                foreach ($doc['tags'] as $tagNome) {
                    $tagId = $tagIds[$tagNome] ?? null;
                    if ($tagId) {
                        DB::table('ged_documento_tags')->insertOrIgnore([
                            'documento_id' => $docId,
                            'tag_id'       => $tagId,
                        ]);
                    }
                }

                $criados++;
            }

            $this->command?->info("[DocumentosDemoSeeder] {$criados} documentos criados.");
        });
    }
}
