<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Permissoes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GedSeeder extends Seeder
{
    public function run(): void
    {
        // Tipos Documentais
        $tipos = [
            ['nome' => 'Oficio', 'descricao' => 'Comunicacao oficial entre orgaos', 'schema_metadados' => json_encode([
                ['campo' => 'numero', 'tipo' => 'text', 'label' => 'Numero do Oficio', 'obrigatorio' => true],
                ['campo' => 'destinatario', 'tipo' => 'text', 'label' => 'Destinatario'],
                ['campo' => 'data_emissao', 'tipo' => 'date', 'label' => 'Data de Emissao'],
            ])],
            ['nome' => 'Memorando', 'descricao' => 'Comunicacao interna', 'schema_metadados' => json_encode([
                ['campo' => 'numero', 'tipo' => 'text', 'label' => 'Numero'],
                ['campo' => 'setor_origem', 'tipo' => 'text', 'label' => 'Setor de Origem'],
                ['campo' => 'setor_destino', 'tipo' => 'text', 'label' => 'Setor de Destino'],
            ])],
            ['nome' => 'Contrato', 'descricao' => 'Contratos e termos aditivos', 'schema_metadados' => json_encode([
                ['campo' => 'numero_contrato', 'tipo' => 'text', 'label' => 'Numero do Contrato', 'obrigatorio' => true],
                ['campo' => 'contratado', 'tipo' => 'text', 'label' => 'Contratado'],
                ['campo' => 'valor', 'tipo' => 'number', 'label' => 'Valor (R$)'],
                ['campo' => 'vigencia_inicio', 'tipo' => 'date', 'label' => 'Inicio da Vigencia'],
                ['campo' => 'vigencia_fim', 'tipo' => 'date', 'label' => 'Fim da Vigencia'],
            ])],
            ['nome' => 'Nota Fiscal', 'descricao' => 'Notas fiscais de servicos e produtos', 'schema_metadados' => json_encode([
                ['campo' => 'numero_nf', 'tipo' => 'text', 'label' => 'Numero da NF', 'obrigatorio' => true],
                ['campo' => 'fornecedor', 'tipo' => 'text', 'label' => 'Fornecedor'],
                ['campo' => 'valor', 'tipo' => 'number', 'label' => 'Valor (R$)'],
                ['campo' => 'data_emissao', 'tipo' => 'date', 'label' => 'Data de Emissao'],
            ])],
            ['nome' => 'Ata', 'descricao' => 'Atas de reuniao e sessao', 'schema_metadados' => json_encode([
                ['campo' => 'data_reuniao', 'tipo' => 'date', 'label' => 'Data da Reuniao'],
                ['campo' => 'local', 'tipo' => 'text', 'label' => 'Local'],
                ['campo' => 'participantes', 'tipo' => 'text', 'label' => 'Participantes'],
            ])],
            ['nome' => 'Portaria', 'descricao' => 'Portarias administrativas', 'schema_metadados' => json_encode([
                ['campo' => 'numero', 'tipo' => 'text', 'label' => 'Numero da Portaria', 'obrigatorio' => true],
                ['campo' => 'data_publicacao', 'tipo' => 'date', 'label' => 'Data de Publicacao'],
            ])],
            ['nome' => 'Decreto', 'descricao' => 'Decretos municipais', 'schema_metadados' => json_encode([
                ['campo' => 'numero', 'tipo' => 'text', 'label' => 'Numero do Decreto', 'obrigatorio' => true],
                ['campo' => 'data_publicacao', 'tipo' => 'date', 'label' => 'Data de Publicacao'],
            ])],
            ['nome' => 'Lei', 'descricao' => 'Leis municipais', 'schema_metadados' => json_encode([
                ['campo' => 'numero', 'tipo' => 'text', 'label' => 'Numero da Lei', 'obrigatorio' => true],
                ['campo' => 'data_publicacao', 'tipo' => 'date', 'label' => 'Data de Publicacao'],
            ])],
            ['nome' => 'Alvara', 'descricao' => 'Alvaras e licencas', 'schema_metadados' => null],
            ['nome' => 'Certidao', 'descricao' => 'Certidoes diversas', 'schema_metadados' => null],
            ['nome' => 'Relatorio', 'descricao' => 'Relatorios tecnicos e gerenciais', 'schema_metadados' => null],
            ['nome' => 'Outros', 'descricao' => 'Documentos diversos', 'schema_metadados' => null],
        ];

        foreach ($tipos as $tipo) {
            DB::table('ged_tipos_documentais')->insert(array_merge($tipo, [
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Permissoes — catálogo único (a migration do RBAC já pode tê-lo criado)
        $permIds = Permissoes::sincronizarCatalogo();

        // Roles
        $roles = [
            [
                'nome' => 'Administrador',
                'descricao' => 'Acesso total ao sistema GED',
                'permissoes' => array_keys($permIds), // todas
            ],
            [
                'nome' => 'Gestor Documental',
                'descricao' => 'Gerencia documentos, pastas e fluxos',
                'permissoes' => ['documento.visualizar', 'documento.criar', 'documento.editar', 'documento.excluir', 'documento.download',
                    'pasta.visualizar', 'pasta.criar', 'pasta.editar', 'pasta.excluir',
                    'fluxo.visualizar', 'fluxo.criar', 'fluxo.editar', 'fluxo.gerenciar'],
            ],
            [
                'nome' => 'Editor',
                'descricao' => 'Pode criar e editar documentos',
                'permissoes' => ['documento.visualizar', 'documento.criar', 'documento.editar', 'documento.download',
                    'pasta.visualizar', 'fluxo.visualizar'],
            ],
            [
                'nome' => 'Visualizador',
                'descricao' => 'Apenas visualiza e faz download',
                'permissoes' => ['documento.visualizar', 'documento.download', 'pasta.visualizar'],
            ],
            [
                'nome' => Permissoes::PERFIL_PADRAO,
                'descricao' => 'Tudo exceto administração',
                'permissoes' => Permissoes::doPerfilPadrao(),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('ged_roles')->updateOrInsert(
                ['nome' => $role['nome']],
                ['descricao' => $role['descricao'], 'created_at' => now(), 'updated_at' => now()],
            );
            $roleId = (int) DB::table('ged_roles')->where('nome', $role['nome'])->value('id');

            $permissoes = $role['nome'] === 'Administrador'
                ? $role['permissoes']
                : array_merge($role['permissoes'], Permissoes::OPERACIONAIS_NOVAS);
            Permissoes::concederAoPerfil($roleId, $permissoes, $permIds);
        }

        // Usuario admin (antes das pastas por causa da FK criado_por)
        $adminUserId = DB::table('users')->insertGetId([
            'name' => 'Administrador',
            'email' => 'admin@ged.local',
            'password' => Hash::make($this->senhaDoAdmin()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pastas iniciais
        $raizAdm = DB::table('ged_pastas')->insertGetId([
            'nome' => 'Administrativo', 'descricao' => 'Documentos administrativos', 'parent_id' => null, 'path' => '/1', 'criado_por' => $adminUserId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $raizFin = DB::table('ged_pastas')->insertGetId([
            'nome' => 'Financeiro', 'descricao' => 'Documentos financeiros', 'parent_id' => null, 'path' => '/2', 'criado_por' => $adminUserId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ged_pastas')->insert([
            ['nome' => 'Juridico', 'descricao' => 'Documentos juridicos', 'parent_id' => null, 'path' => '/3', 'criado_por' => $adminUserId, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Recursos Humanos', 'descricao' => 'Documentos de RH', 'parent_id' => null, 'path' => '/4', 'criado_por' => $adminUserId, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Licitacoes', 'descricao' => 'Processos licitatorios', 'parent_id' => null, 'path' => '/5', 'criado_por' => $adminUserId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Subpastas
        DB::table('ged_pastas')->insert([
            ['nome' => 'Oficios', 'descricao' => null, 'parent_id' => $raizAdm, 'path' => "/1/{$raizAdm}", 'criado_por' => $adminUserId, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Portarias', 'descricao' => null, 'parent_id' => $raizAdm, 'path' => "/1/{$raizAdm}", 'criado_por' => $adminUserId, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Contratos', 'descricao' => null, 'parent_id' => $raizFin, 'path' => "/2/{$raizFin}", 'criado_por' => $adminUserId, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Notas Fiscais', 'descricao' => null, 'parent_id' => $raizFin, 'path' => "/2/{$raizFin}", 'criado_por' => $adminUserId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Atribuir role admin ao usuario
        $adminRoleId = DB::table('ged_roles')->where('nome', 'Administrador')->value('id');
        DB::table('ged_user_roles')->insert([
            'user_id' => $adminUserId,
            'role_id' => $adminRoleId,
        ]);

        // Tags
        $tagData = [
            ['nome' => 'Urgente', 'cor' => '#EF4444'],
            ['nome' => 'Confidencial', 'cor' => '#F59E0B'],
            ['nome' => 'Revisao Pendente', 'cor' => '#3B82F6'],
            ['nome' => 'Aprovado', 'cor' => '#22C55E'],
            ['nome' => 'Arquivo Permanente', 'cor' => '#6B7280'],
            ['nome' => 'Em Tramitacao', 'cor' => '#8B5CF6'],
        ];

        foreach ($tagData as $tag) {
            DB::table('ged_tags')->insert(array_merge($tag, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Senha do admin@ged.local. Era fixa ("admin123") e todo ente criado pelo
     * docs:template-restore nascia com ela em produção. Agora vem de
     * GED_SEED_ADMIN_PASSWORD ou é sorteada e mostrada uma única vez no console.
     */
    private function senhaDoAdmin(): string
    {
        $senha = (string) env('GED_SEED_ADMIN_PASSWORD', '');
        if ($senha !== '') {
            return $senha;
        }

        $senha = \Illuminate\Support\Str::password(16, symbols: false);
        $this->command?->warn("Senha do admin@ged.local (anote, não será mostrada de novo): {$senha}");

        return $senha;
    }
}
