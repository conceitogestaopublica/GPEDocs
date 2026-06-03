<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ug;
use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria 2 memorandos + 1 oficio exemplo. Todos da UG modelo, com remetente Maria Admin.
 */
class MemorandosOficiosDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            $ug = Ug::query()->where('codigo', 'UG-MODELO')->first();
            if (! $ug) {
                $this->command?->warn('[MemorandosOficiosDemoSeeder] UG-MODELO nao encontrada.');
                return;
            }

            $maria = User::query()->where('email', 'admin.ug@modelo.local')->first();
            $ana   = User::query()->where('email', 'gestor@modelo.local')->first();
            $joao  = User::query()->where('email', 'operador@modelo.local')->first();

            if (! $maria) {
                $this->command?->warn('[MemorandosOficiosDemoSeeder] Usuarios demo nao encontrados — execute UsuariosDemoSeeder antes.');
                return;
            }

            $setorFolha = UgOrganograma::query()
                ->where('ug_id', $ug->id)
                ->where('codigo', 'SET-001')
                ->first();
            $unidade2 = UgOrganograma::query()
                ->where('ug_id', $ug->id)
                ->where('nivel', 2)
                ->orderBy('id')
                ->first();

            $memorandos = [
                [
                    'numero'         => 'MEM-2026/000001',
                    'assunto'        => 'Solicitação de material de expediente',
                    'conteudo'       => 'Solicitamos a aquisicao de material de expediente conforme lista anexa para o setor de protocolo geral.',
                    'setor_origem'   => 'Secretaria de Administração',
                    'confidencial'   => false,
                    'status'         => 'enviado',
                    'destinatario_user'   => $ana?->id,
                    'destinatario_unidade'=> $unidade2?->id,
                ],
                [
                    'numero'         => 'MEM-2026/000002',
                    'assunto'        => 'Convocação para reunião de planejamento',
                    'conteudo'       => 'Solicitamos a presenca dos servidores na reuniao de planejamento que ocorrera no proximo dia 30/04/2026, as 14h.',
                    'setor_origem'   => 'Secretaria de Administração',
                    'confidencial'   => false,
                    'status'         => 'enviado',
                    'destinatario_user'   => $joao?->id,
                    'destinatario_unidade'=> $setorFolha?->id,
                ],
            ];

            $memCriados = 0;
            foreach ($memorandos as $m) {
                $existente = DB::table('proc_memorandos')->where('numero', $m['numero'])->first();
                if ($existente) {
                    continue;
                }

                $memorandoId = DB::table('proc_memorandos')->insertGetId([
                    'ug_id'                  => $ug->id,
                    'numero'                 => $m['numero'],
                    'assunto'                => $m['assunto'],
                    'conteudo'               => $m['conteudo'],
                    'remetente_id'           => $maria->id,
                    'setor_origem'           => $m['setor_origem'],
                    'confidencial'           => $m['confidencial'],
                    'status'                 => $m['status'],
                    'enviado_em'             => $m['status'] === 'enviado' ? now()->subDays(2) : null,
                    'qr_code_token'          => (string) Str::uuid(),
                    'created_at'             => now()->subDays(3),
                    'updated_at'             => now(),
                ]);

                DB::table('proc_memorando_destinatarios')->insert([
                    'memorando_id' => $memorandoId,
                    'usuario_id'   => $m['destinatario_user'],
                    'unidade_id'   => $m['destinatario_unidade'],
                    'lido'         => false,
                    'created_at'   => now()->subDays(2),
                    'updated_at'   => now(),
                ]);

                $memCriados++;
            }

            // ── Oficio ──────────────────────────────────────────────────
            $oficios = [
                [
                    'numero'              => 'OF-2026/000001',
                    'assunto'             => 'Encaminhamento de relatorio trimestral',
                    'conteudo'             => 'Prezado(a), segue em anexo o relatorio trimestral de atividades para conhecimento e providencias cabiveis.',
                    'setor_origem'        => 'Secretaria de Administração',
                    'destinatario_nome'   => 'Camara Municipal Modelo',
                    'destinatario_email'  => 'contato@camara.modelo.mg.gov.br',
                    'destinatario_cargo'  => 'Presidente',
                    'destinatario_orgao'  => 'Camara Municipal Modelo',
                    'status'              => 'enviado',
                ],
            ];

            $ofCriados = 0;
            foreach ($oficios as $o) {
                $existente = DB::table('proc_oficios')->where('numero', $o['numero'])->first();
                if ($existente) {
                    continue;
                }

                DB::table('proc_oficios')->insert([
                    'ug_id'                   => $ug->id,
                    'numero'                  => $o['numero'],
                    'assunto'                 => $o['assunto'],
                    'conteudo'                => $o['conteudo'],
                    'remetente_id'            => $maria->id,
                    'setor_origem'            => $o['setor_origem'],
                    'destinatario_nome'       => $o['destinatario_nome'],
                    'destinatario_email'      => $o['destinatario_email'],
                    'destinatario_cargo'      => $o['destinatario_cargo'],
                    'destinatario_orgao'      => $o['destinatario_orgao'],
                    'status'                  => $o['status'],
                    'enviado_em'              => now()->subDays(1),
                    'rastreio_token'          => bin2hex(random_bytes(20)),
                    'qr_code_token'           => (string) Str::uuid(),
                    'created_at'              => now()->subDays(2),
                    'updated_at'              => now(),
                ]);

                $ofCriados++;
            }

            $this->command?->info("[MemorandosOficiosDemoSeeder] {$memCriados} memorandos + {$ofCriados} oficio(s) criados.");
        });
    }
}
