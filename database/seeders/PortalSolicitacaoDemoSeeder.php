<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ug;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cria 1 cidadao + 1 solicitacao no Portal + eventos da solicitacao.
 */
class PortalSolicitacaoDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            $ug = Ug::query()->where('codigo', 'UG-MODELO')->first();
            if (! $ug) {
                $this->command?->warn('[PortalSolicitacaoDemoSeeder] UG-MODELO nao encontrada.');
                return;
            }

            // 1) Cidadao (email e unique)
            $cidadaoId = DB::table('portal_cidadaos')->where('email', 'beatriz@email.com')->value('id');
            if (! $cidadaoId) {
                $cidadaoId = DB::table('portal_cidadaos')->insertGetId([
                    'nome'                 => 'Beatriz Souza',
                    'email'                => 'beatriz@email.com',
                    'cpf'                  => '987.654.321-00',
                    'telefone'             => '(35) 98888-7777',
                    'senha'                => Hash::make('demo1234'),
                    'email_verificado_em'  => now()->subDays(10),
                    'ativo'                => true,
                    'created_at'           => now()->subDays(10),
                    'updated_at'           => now(),
                ]);
            }

            // 2) Servico de Poda
            $servicoId = DB::table('portal_servicos')
                ->where('ug_id', $ug->id)
                ->where('slug', Str::slug('Poda de Árvore em Via Pública'))
                ->value('id');

            if (! $servicoId) {
                $this->command?->warn('[PortalSolicitacaoDemoSeeder] Servico "Poda de Arvore em Via Publica" nao encontrado — execute PortalServicosSeeder antes.');
                return;
            }

            // 3) Atendente — Maria Admin
            $atendente = User::query()->where('email', 'admin.ug@modelo.local')->first();

            // 4) Solicitacao
            $codigo = 'SOL-2026-00001';
            $solicitacaoId = DB::table('portal_solicitacoes')
                ->where('ug_id', $ug->id)
                ->where('codigo', $codigo)
                ->value('id');

            if (! $solicitacaoId) {
                $solicitacaoId = DB::table('portal_solicitacoes')->insertGetId([
                    'codigo'           => $codigo,
                    'ug_id'            => $ug->id,
                    'servico_id'       => $servicoId,
                    'cidadao_id'       => $cidadaoId,
                    'anonima'          => false,
                    'status'           => 'em_atendimento',
                    'descricao'        => 'Solicito a poda de uma arvore em frente ao numero 250 da Rua das Flores, '
                        .'que esta com galhos sobre a rede eletrica e oferecendo risco aos pedestres. '
                        .'Endereco: Rua das Flores, 250 — Bairro Centro.',
                    'telefone_contato' => '(35) 98888-7777',
                    'email_contato'    => 'beatriz@email.com',
                    'atendente_id'     => $atendente?->id,
                    'created_at'       => now()->subDays(5),
                    'updated_at'       => now(),
                ]);

                // Eventos. IMPORTANTE: insert em lote com keys diferentes entre
                // rows quebra no Postgres (Laravel pega a ordem de colunas da
                // primeira row e mapeia as demais por posicao). Por isso todas
                // as rows tem o mesmo shape, com null onde nao se aplica.
                $eventoBase = [
                    'autor_user_id'    => null,
                    'autor_cidadao_id' => null,
                ];
                DB::table('portal_solicitacao_eventos')->insert([
                    [
                        'solicitacao_id'    => $solicitacaoId,
                        'tipo'              => 'criada',
                        'autor_tipo'        => 'cidadao',
                        'autor_nome'        => 'Beatriz Souza',
                        ...$eventoBase,
                        'autor_cidadao_id'  => $cidadaoId,
                        'status_anterior'   => null,
                        'status_novo'       => 'aberta',
                        'mensagem'          => 'Solicitacao criada pelo Portal de Servicos.',
                        'created_at'        => now()->subDays(5),
                        'updated_at'        => now()->subDays(5),
                    ],
                    [
                        'solicitacao_id'    => $solicitacaoId,
                        'tipo'              => 'status_alterado',
                        'autor_tipo'        => 'atendente',
                        'autor_nome'        => $atendente?->name ?? 'Atendente',
                        ...$eventoBase,
                        'autor_user_id'     => $atendente?->id,
                        'status_anterior'   => 'aberta',
                        'status_novo'       => 'em_atendimento',
                        'mensagem'          => 'Recebido pelo setor de Meio Ambiente. Vistoria tecnica sera agendada.',
                        'created_at'        => now()->subDays(3),
                        'updated_at'        => now()->subDays(3),
                    ],
                    [
                        'solicitacao_id'    => $solicitacaoId,
                        'tipo'              => 'comentario',
                        'autor_tipo'        => 'atendente',
                        'autor_nome'        => $atendente?->name ?? 'Atendente',
                        ...$eventoBase,
                        'autor_user_id'     => $atendente?->id,
                        'status_anterior'   => 'em_atendimento',
                        'status_novo'       => 'em_atendimento',
                        'mensagem'          => 'Equipe agendada para a proxima semana (vistoria + execucao).',
                        'created_at'        => now()->subDays(1),
                        'updated_at'        => now()->subDays(1),
                    ],
                ]);
            }

            $this->command?->info('[PortalSolicitacaoDemoSeeder] 1 cidadao, 1 solicitacao, 3 eventos criados.');
        });
    }
}
