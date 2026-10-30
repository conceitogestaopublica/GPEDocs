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
        'documento.sigiloso'   => 'Acessar documentos confidenciais (restritos seguem só para autor e signatários)',
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
        'contato.gerenciar'    => 'Consultar e manter contatos (cadastro, listas, procurações, unificação e CSV)',
        'urbanismo.gerir'      => 'Consulta prévia: zonas e parâmetros urbanísticos, camadas e lotes do mapa',
        'tramita.usar'         => 'Tramita GOV.BR: enviar ofícios e processos a outros entes, aceitar ou recusar os recebidos',
        'tramita.configurar'   => 'Tramita GOV.BR: adesão, certificado, estrutura do ente e destino dos recebidos',
        'suporte.atender'      => 'Suporte: atender, responder, escalar e resolver atendimentos dos usuários',
        'suporte.gerir'        => 'Suporte: central de ajuda (assuntos e artigos), atendentes e configuração',
        'relatorio.indicadores' => 'Painel de indicadores: uso, setores, processos e assinaturas de todos os setores',
        'relatorio.eventos'    => 'Relatórios de eventos (auditoria) e de acessos diários, com exportação e agendamento',
        'fiscalizacao.campo'   => 'App da fiscalização: fazer vistorias no celular (offline) e sincronizar',
        'fiscalizacao.formularios' => 'App da fiscalização: formulários de checklist, campos e objetos fiscalizados',
        'tributario.usar'      => 'Sistema tributário: consultar imóvel, emitir guia, pedir alvará/habite-se e fiscalização a partir do processo',
        'diario.gerir'         => 'Diário Oficial: estrutura (cadernos, seções), responsáveis e montagem das edições',
        'ato.elaborar'         => 'Atos oficiais: elaborar, gerar PDF/A, enviar à assinatura e publicar',
        'ato.tipos'            => 'Atos oficiais: tipos de ato, numeração, modelos .docx e quem assina',
        'pss.gerir'            => 'Processo seletivo simplificado: editais, inscrições, respostas e exportação',
        'pad.gerir'            => 'PAD: ver todos os processos disciplinares, comissão e tipos de infração',
        'parecer.emitir'       => 'Emitir pareceres (numerados, com conclusão e anexos)',
        'legislativo.gerir'    => 'Protocolo legislativo: painel, relatórios e cadastro de vereadores',
        'rh.requerimentos'     => 'Gestão de Pessoas: ver todos os requerimentos pessoais de servidores',
        'ouvidoria.gerir'      => 'Ouvidoria: painel, dados de manifestante sigiloso, resolver em todos os setores e decisão final',
        'esic.gerir'           => 'e-SIC: painel de pedidos de acesso à informação e prorrogação de prazo (LAI)',

        // Administração
        'admin.configuracoes'       => 'Acessar o módulo de configurações',
        'admin.usuarios'            => 'Gerenciar usuários',
        'admin.roles'               => 'Gerenciar perfis e permissões',
        'admin.ugs'                 => 'Gerenciar unidades gestoras, organograma e banners do portal',
        'admin.sistemas_integrados' => 'Gerenciar sistemas integrados (credenciais de API e webhooks)',
        'admin.tipos_documentais'   => 'Gerenciar tipos documentais',
        'admin.contatos'            => 'Configurar contatos (campos complementares e regras de preenchimento)',
        'admin.classificacao'       =>'Manter o plano de classificação e reclassificar documentos e processos',
        'admin.tipos_processo'      => 'Gerenciar tipos de processo e modelos de ofício',
    ];

    /** Fora do perfil padrão: concedidas uma a uma pelo administrador. */
    private const SO_POR_CONCESSAO = ['portal.carta_servicos', 'documento.sigiloso'];

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
            fn (string $p) => ! str_starts_with($p, 'admin.') && ! in_array($p, self::SO_POR_CONCESSAO, true),
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
