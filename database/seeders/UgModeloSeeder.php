<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Logradouro;
use App\Models\Ug;
use App\Models\UgOrganograma;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria uma Unidade Gestora MODELO para uso em ambientes de desenvolvimento e
 * demonstração, junto com um organograma minimo de 3 niveis (Orgao -> Unidade
 * -> Setor). Idempotente: pode ser executado multiplas vezes sem duplicar.
 */
class UgModeloSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            // Logradouro semeado pelo EnderecosDemoSeeder. CEP confirma a unicidade.
            $logradouro = Logradouro::query()
                ->where('nome', 'Praça da Matriz')
                ->where('cep', '37000-000')
                ->first();

            if (! $logradouro) {
                $this->command?->warn('Logradouro modelo nao encontrado — execute EnderecosDemoSeeder antes.');
                return;
            }

            $ug = Ug::firstOrCreate(
                ['codigo' => 'UG-MODELO'],
                [
                    'portal_slug' => 'modelo',
                    'nome' => 'PREFEITURA MUNICIPAL MODELO',
                    'cnpj' => '00.000.000/0001-00',
                    'logradouro_id' => $logradouro->id,
                    'numero' => '100',
                    'complemento' => null,
                    'nivel_1_label' => 'Órgão',
                    'nivel_2_label' => 'Unidade',
                    'nivel_3_label' => 'Setor',
                    'ativo' => true,
                    'observacoes' => 'Gestora modelo criada via UgModeloSeeder.',
                    'telefone' => '(35) 0000-0000',
                    'email_institucional' => 'contato@modelo.mg.gov.br',
                    'site' => 'https://www.modelo.mg.gov.br',
                    'banner_ativo' => true,
                ]
            );

            // ── Nivel 1: Orgao raiz ───────────────────────────────────────────
            $orgao = UgOrganograma::firstOrCreate(
                [
                    'ug_id' => $ug->id,
                    'parent_id' => null,
                    'nivel' => 1,
                    'codigo' => 'ORG-001',
                ],
                [
                    'nome' => 'Prefeitura Municipal Modelo',
                    'tipo_orgao' => 'Executivo Municipal',
                    'ativo' => true,
                    'dt_inicio' => now()->startOfYear()->toDateString(),
                ]
            );

            // ── Nivel 2: Unidade administrativa ──────────────────────────────
            $unidade = UgOrganograma::firstOrCreate(
                [
                    'ug_id' => $ug->id,
                    'parent_id' => $orgao->id,
                    'nivel' => 2,
                    'codigo' => 'UNI-001',
                ],
                [
                    'nome' => 'Secretaria de Administração',
                    'ativo' => true,
                ]
            );

            // ── Nivel 3: Setor folha (recebe protocolos externos) ────────────
            UgOrganograma::firstOrCreate(
                [
                    'ug_id' => $ug->id,
                    'parent_id' => $unidade->id,
                    'nivel' => 3,
                    'codigo' => 'SET-001',
                ],
                [
                    'nome' => 'Protocolo geral',
                    'ativo' => true,
                    'protocolo_externo' => true,
                ]
            );

            $this->command?->info("UG modelo criada/atualizada: [{$ug->codigo}] {$ug->nome} (id={$ug->id})");
        });
    }
}
