<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Catálogo ÚNICO de permissões (módulo.ação). A migration que liga o RBAC e o GedSeeder
 * leem daqui — duas listas divergiriam, e `ged_permissions.nome` é único: o seeder que
 * inserisse de novo um nome já criado pela migration derrubaria o docs:template-restore.
 */
final class Permissoes
{
    public const CATALOGO = [
        // GPE Docs
        'documento.visualizar' => 'Visualizar documentos, repositório e busca',
        'documento.criar'      => 'Incorporar documentos (upload e captura)',
        'documento.editar'     => 'Editar documentos, alterar situação e mover de pasta',
        'documento.excluir'    => 'Excluir documentos',
        'documento.download'   => 'Fazer download de documentos',
        'pasta.visualizar'     => 'Visualizar pastas e repositório',
        'pasta.criar'          => 'Criar pastas',
        'pasta.editar'         => 'Renomear, inativar e reativar pastas',
        'pasta.excluir'        => 'Excluir pastas',
        'fluxo.visualizar'     => 'Visualizar fluxos de trabalho',
        'fluxo.criar'          => 'Criar fluxos de trabalho',
        'fluxo.editar'         => 'Editar e excluir fluxos de trabalho',
        'fluxo.gerenciar'      => 'Iniciar instâncias de fluxo',
        'assinatura.solicitar' => 'Solicitar assinatura de documentos',

        // GPE Flow
        'processo.visualizar'  => 'Consultar processos e painel de processos',
        'processo.criar'       => 'Abrir processos',
        'processo.tramitar'    => 'Receber, despachar, devolver, concluir, cancelar e arquivar processos',
        'comunicacao.enviar'   => 'Emitir memorandos, circulares e ofícios',

        // Portal do Cidadão
        'portal.atendimento'   => 'Atender solicitações do portal do cidadão',
        'portal.carta_servicos' => 'Manter a carta de serviços',

        // Administração
        'admin.configuracoes'       => 'Acessar o módulo de configurações',
        'admin.usuarios'            => 'Gerenciar usuários',
        'admin.roles'               => 'Gerenciar perfis e permissões',
        'admin.ugs'                 => 'Gerenciar unidades gestoras, organograma e banners do portal',
        'admin.sistemas_integrados' => 'Gerenciar sistemas integrados (credenciais de API e webhooks)',
        'admin.tipos_documentais'   => 'Gerenciar tipos documentais',
        'admin.tipos_processo'      => 'Gerenciar tipos de processo e modelos de ofício',
    ];

    /** Perfil atribuído a quem não tinha nenhum quando o RBAC foi ligado: tudo menos administração. */
    public const PERFIL_PADRAO = 'Usuário padrão';

    /**
     * Antes do RBAC qualquer usuário logado fazia tudo isto. Os perfis que já existiam
     * recebem essas permissões novas para ninguém perder o que usava no dia a dia.
     */
    public const OPERACIONAIS_NOVAS = [
        'assinatura.solicitar', 'processo.visualizar', 'processo.criar', 'processo.tramitar',
        'comunicacao.enviar', 'portal.atendimento',
    ];

    /** @return list<string> */
    public static function doPerfilPadrao(): array
    {
        return array_values(array_filter(
            array_keys(self::CATALOGO),
            fn (string $p) => ! str_starts_with($p, 'admin.') && $p !== 'portal.carta_servicos',
        ));
    }

    /**
     * Garante o catálogo na base corrente e devolve [nome => id]. Idempotente.
     *
     * @return array<string,int>
     */
    public static function sincronizarCatalogo(): array
    {
        foreach (self::CATALOGO as $nome => $descricao) {
            DB::table('ged_permissions')->updateOrInsert(
                ['nome' => $nome],
                ['descricao' => $descricao, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        return DB::table('ged_permissions')->pluck('id', 'nome')->map(fn ($id) => (int) $id)->all();
    }

    /** Vincula permissões a um perfil sem duplicar. */
    public static function concederAoPerfil(int $roleId, array $nomes, array $ids): void
    {
        $linhas = [];
        foreach ($nomes as $nome) {
            if (isset($ids[$nome])) {
                $linhas[] = ['role_id' => $roleId, 'permission_id' => $ids[$nome]];
            }
        }
        if ($linhas) {
            DB::table('ged_role_permissions')->insertOrIgnore($linhas);
        }
    }
}
