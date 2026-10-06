<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers;
use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoEvento;
use App\Models\SistemaIntegrado;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissoes;
use App\Tenant\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Conteúdo de DEMONSTRAÇÃO / prova de conceito sobre a estrutura real de um tenant
 * (rodar depois do docs:importar-estrutura). Segue o roteiro do item 9.3 do TR.
 *
 * Tudo é criado pelos PRÓPRIOS controllers, autenticado como cada usuário de
 * demonstração — numeração de protocolo, histórico, notificações, PDF da decisão,
 * resumo criptográfico e extração de texto saem iguais ao uso real.
 *
 * Usuários de demonstração: e-mail @gpedocs.demo e senha única (--senha ou sorteada,
 * mostrada no fim). Antes de levar a produção para uso real, apagar esses usuários ou
 * trocar a senha.
 *
 *   php artisan docs:demonstracao --tenant=3 --ug=UG-002
 */
class Demonstracao extends Command
{
    protected $signature = 'docs:demonstracao
        {--tenant= : Tenant alvo (id ou subdomínio)}
        {--ug=UG-002 : Código da UG que recebe a demonstração}
        {--senha= : Senha dos usuários de demonstração (padrão: sorteada e mostrada)}';

    protected $description = 'Popula um tenant com conteúdo de demonstração para a prova de conceito';

    /** Secretarias (nível 2 do organograma) por chave usada nos cenários. */
    private array $setor = [];

    /** @var array<string,User> */
    private array $u = [];

    private int $ugId;

    private string $ugNome;

    public function handle(): int
    {
        $tenant = Tenant::active()->get()->first(fn (Tenant $t) => (string) $t->id === (string) $this->option('tenant')
            || $t->subdomain === $this->option('tenant'));
        if (! $tenant) {
            $this->error('Informe --tenant (id ou subdomínio) de um tenant ativo do GPEDocs.');

            return self::FAILURE;
        }

        $ctx = app(TenantContext::class);
        $ctx->set($tenant);
        Config::set('session.driver', 'array');
        Config::set('mail.default', 'log');

        try {
            $ug = DB::table('ugs')->where('codigo', $this->option('ug'))->first();
            if (! $ug) {
                $this->error("UG {$this->option('ug')} não encontrada — rode antes o docs:importar-estrutura.");

                return self::FAILURE;
            }
            $this->ugId = (int) $ug->id;
            $this->ugNome = (string) $ug->nome;

            if (DB::table('users')->where('email', 'like', '%@gpedocs.demo')->exists()) {
                $this->error('Este tenant já tem conteúdo de demonstração (usuários @gpedocs.demo). Recrie o banco para gerar de novo.');

                return self::FAILURE;
            }

            $senha = (string) ($this->option('senha') ?: Str::password(12, symbols: false));

            $this->components->task('Secretarias e usuários de demonstração', fn () => $this->usuarios($senha));
            $this->components->task('Pastas', fn () => $this->pastas());
            $this->components->task('Documentos (PDF, metadados, versões, sigilo)', fn () => $this->documentos());
            $this->components->task('Assinaturas (concluídas e pendentes)', fn () => $this->assinaturas());
            $this->components->task('Tipos de processo', fn () => $this->tiposProcesso());
            $this->components->task('Processos em andamento e concluídos', fn () => $this->processos());
            $this->components->task('Memorandos, circular e ofício', fn () => $this->comunicacoes());
            $this->components->task('Portal do cidadão', fn () => $this->portal($senha));
            $tokens = [];
            $this->components->task('Sistemas integrados (gpe2 e tributário)', function () use (&$tokens) {
                $tokens = $this->sistemasIntegrados();

                return true;
            });

            $this->newLine();
            $this->info("Usuários de demonstração (senha: {$senha})");
            $this->table(['Nome', 'E-mail', 'Lotação', 'Perfil'], collect($this->u)->map(fn (User $u, $k) => [
                $u->name, $u->email, DB::table('ug_organograma')->where('id', $u->unidade_id)->value('nome'),
                $k === 'gestor' ? 'Administrador' : Permissoes::PERFIL_PADRAO,
            ])->values()->all());
            $this->line("Cidadão do portal: cidadao@gpedocs.demo / {$senha}");
            $this->newLine();
            $this->warn('Tokens dos sistemas integrados (mostrados uma única vez — configure no gpe2 e no tributário):');
            $this->table(['Sistema', 'Token'], $tokens);

            return self::SUCCESS;
        } finally {
            $ctx->clear();
        }
    }

    // ─────────────────────────────── cenários ────────────────────────────────

    private function usuarios(string $senha): bool
    {
        $nivel2 = fn (string $nome) => (int) DB::table('ug_organograma')->where('ug_id', $this->ugId)
            ->where('nivel', 2)->where('ativo', true)->where('nome', 'ilike', $nome)->orderBy('id')->value('id');
        $this->setor = [
            'administracao' => $nivel2('Secretaria Municipal de Administração'),
            'saude'         => $nivel2('Secretaria Municipal de Saúde'),
            'obras'         => $nivel2('Sec. Mun de Obras%'),
            'fazenda'       => $nivel2('%Fazenda e Planejamento'),
            'gabinete'      => $nivel2('Gabinete da Prefeitura'),
            'procuradoria'  => $nivel2('Procuradoria Municipal'),
            'educacao'      => $nivel2('Secretaria Municipal de Educação'),
        ];
        // Município sem essas secretarias: usa as primeiras unidades de nível 2.
        $reserva = DB::table('ug_organograma')->where('ug_id', $this->ugId)->where('nivel', 2)->orderBy('id')->pluck('id')->all();
        foreach ($this->setor as $k => $id) {
            $this->setor[$k] = $id ?: ($reserva[array_search($k, array_keys($this->setor), true) % max(count($reserva), 1)] ?? null);
        }

        $roles = DB::table('ged_roles')->pluck('id', 'nome');
        $pessoas = [
            'gestor'       => ['Ana Paula Ferreira', 'administracao', 'Administrador', '529.982.247-25'],
            'protocolo'    => ['Carlos Eduardo Souza', 'administracao', Permissoes::PERFIL_PADRAO, '153.509.460-56'],
            'saude'        => ['Mariana Lopes Cardoso', 'saude', Permissoes::PERFIL_PADRAO, '714.602.380-01'],
            'obras'        => ['Roberto Almeida Neto', 'obras', Permissoes::PERFIL_PADRAO, '390.533.447-05'],
            'procuradoria' => ['Helena Martins Prado', 'procuradoria', Permissoes::PERFIL_PADRAO, '862.917.240-40'],
            'fazenda'      => ['Paulo Henrique Dias', 'fazenda', Permissoes::PERFIL_PADRAO, '275.484.389-23'],
        ];

        foreach ($pessoas as $chave => [$nome, $setor, $perfil, $cpf]) {
            $user = User::forceCreate([
                'name'        => $nome,
                'email'       => "{$chave}@gpedocs.demo",
                'cpf'         => $cpf,
                'password'    => Hash::make($senha),
                'tipo'        => 'interno',
                'ug_id'       => $this->ugId,
                'unidade_id'  => $this->setor[$setor],
                'super_admin' => false,
            ]);
            DB::table('user_ugs')->insert(['user_id' => $user->id, 'ug_id' => $this->ugId, 'principal' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ged_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roles[$perfil]]);
            if ($chave === 'gestor') {
                // A gestora vê a visão geral da UG e os documentos confidenciais.
                $user->forceFill(['acesso_geral_ug' => true])->save();
            }
            $this->u[$chave] = $user;
        }

        return true;
    }

    private array $pasta = [];

    private function pastas(): bool
    {
        $existentes = DB::table('ged_pastas')->where('ug_id', $this->ugId)->pluck('id', 'nome');
        $this->pasta = $existentes->all();

        $criar = function (string $nome, ?string $mae = null, string $descricao = '') {
            $this->chamar($this->u['gestor'], Controllers\PastaController::class, 'store',
                ['nome' => $nome, 'descricao' => $descricao, 'parent_id' => $mae ? $this->pasta[$mae] : null]);
            $this->pasta[$nome] = (int) DB::table('ged_pastas')->where('ug_id', $this->ugId)->where('nome', $nome)->orderByDesc('id')->value('id');
        };
        $criar('Gabinete', null, 'Atos normativos do Executivo');
        $criar('Decretos', 'Gabinete');
        $criar('Leis', 'Gabinete');
        $criar('Saúde', null, 'Secretaria Municipal de Saúde');
        $criar('Obras', null, 'Secretaria Municipal de Obras, Infraestrutura e Transporte');

        return true;
    }

    private array $doc = [];

    private function documentos(): bool
    {
        $t = DB::table('ged_tipos_documentais')->pluck('id', 'nome');
        $d = fn (int $dias) => now()->subDays($dias)->toDateString();

        $lista = [
            ['decreto015', 'Decreto nº 015-2026 - Ponto facultativo', 'Decreto', 'Decretos', 'publico',
                ['numero' => '015/2026', 'data_publicacao' => $d(40)], 'DECRETO Nº 015, DE 2026',
                ['Declara ponto facultativo nas repartições públicas municipais no dia 3 de abril de 2026.',
                    'Art. 1º Fica declarado ponto facultativo nas repartições públicas municipais, excetuados os serviços essenciais de saúde, limpeza urbana e segurança.',
                    'Art. 2º Este Decreto entra em vigor na data de sua publicação.']],
            ['decreto022', 'Decreto nº 022-2026 - Assinatura eletrônica', 'Decreto', 'Decretos', 'publico',
                ['numero' => '022/2026', 'data_publicacao' => $d(20)], 'DECRETO Nº 022, DE 2026',
                ['Regulamenta o uso de assinaturas eletrônicas nos atos e documentos do Poder Executivo Municipal, nos termos da Lei Federal nº 14.063, de 23 de setembro de 2020.',
                    'Art. 1º Os atos administrativos poderão ser assinados eletronicamente, admitidas a assinatura eletrônica simples, para os atos internos de menor impacto, e a qualificada, com certificado ICP-Brasil, para os atos normativos e contratos.',
                    'Art. 2º Os documentos assinados eletronicamente serão mantidos no repositório oficial de gestão documental do Município, com verificação pública de autenticidade.']],
            ['lei1845', 'Lei Municipal nº 1.845-2026 - Programa Município Digital', 'Lei', 'Leis', 'publico',
                ['numero' => '1.845/2026', 'data_publicacao' => $d(60)], 'LEI Nº 1.845, DE 2026',
                ['Institui o Programa Município Digital, de gestão eletrônica de documentos e processos administrativos.',
                    'Art. 1º Fica instituído o Programa Município Digital, com o objetivo de eliminar gradualmente a tramitação em papel no âmbito da Administração Municipal.',
                    'Art. 2º Os processos administrativos serão autuados e tramitados em meio eletrônico, na forma de regulamento.']],
            ['portaria104', 'Portaria nº 104-2026 - Comissão de recebimento de obras', 'Portaria', 'Portarias', 'publico',
                ['numero' => '104/2026', 'data_publicacao' => $d(15)], 'PORTARIA Nº 104, DE 2026',
                ['Designa comissão para recebimento provisório e definitivo das obras de pavimentação contratadas pelo Município.',
                    'Art. 1º Ficam designados para compor a comissão de recebimento de obras os servidores lotados na Secretaria Municipal de Obras, Infraestrutura e Transporte.']],
            ['portaria118', 'Portaria nº 118-2026 - Concessão de férias', 'Portaria', 'Recursos Humanos', 'restrito',
                ['numero' => '118/2026', 'data_publicacao' => $d(8)], 'PORTARIA Nº 118, DE 2026',
                ['Concede férias regulamentares a servidor, com os dados funcionais constantes do anexo.',
                    'Documento classificado como RESTRITO por conter dados pessoais de servidor (Lei nº 13.709/2018).']],
            ['contrato031', 'Contrato nº 031-2026 - Pavimentação da Rua José Coutinho', 'Contrato', 'Contratos', 'publico',
                ['numero_contrato' => '031/2026', 'contratado' => 'Construtora Vale do Amparo Ltda.', 'valor' => '487350.90', 'vigencia_inicio' => $d(10), 'vigencia_fim' => now()->addMonths(8)->toDateString()],
                'CONTRATO ADMINISTRATIVO Nº 031/2026',
                ['Objeto: execução de pavimentação asfáltica e drenagem pluvial na Rua José Coutinho, centro.',
                    'Valor global: R$ 487.350,90 (quatrocentos e oitenta e sete mil, trezentos e cinquenta reais e noventa centavos).',
                    'Vigência: 8 (oito) meses a contar da ordem de serviço.']],
            ['contrato045', 'Contrato nº 045-2026 - Fornecimento de medicamentos', 'Contrato', 'Saúde', 'publico',
                ['numero_contrato' => '045/2026', 'contratado' => 'Distribuidora Sul Mineira de Medicamentos S.A.', 'valor' => '132800.00', 'vigencia_inicio' => $d(30), 'vigencia_fim' => now()->addYear()->toDateString()],
                'CONTRATO ADMINISTRATIVO Nº 045/2026',
                ['Objeto: fornecimento parcelado de medicamentos da atenção básica para as unidades de Saúde da Família.',
                    'Valor estimado anual: R$ 132.800,00.']],
            ['ata3', 'Ata da 3ª Reunião Ordinária do Conselho Municipal de Saúde', 'Ata', 'Saúde', 'publico',
                ['data_reuniao' => $d(12), 'local' => 'Auditório da Secretaria Municipal de Saúde', 'participantes' => '9 conselheiros titulares'],
                'ATA DA 3ª REUNIÃO ORDINÁRIA DE 2026',
                ['Aos dias do mês corrente reuniu-se o Conselho Municipal de Saúde, com quórum regimental.',
                    'Pauta: prestação de contas do 1º quadrimestre; aquisição de medicamentos da atenção básica; calendário de vacinação.',
                    'Deliberação: aprovada por unanimidade a prestação de contas.']],
            ['vistoria', 'Relatório de vistoria - Obra da Rua José Coutinho', 'Relatorio', 'Obras', 'interno',
                [], 'RELATÓRIO DE VISTORIA Nº 07/2026',
                ['Vistoria realizada no trecho inicial da obra de pavimentação da Rua José Coutinho.',
                    'Constatou-se a execução da base e sub-base conforme projeto. Pendência: limpeza das bocas de lobo.']],
            ['oficio210', 'Ofício nº 210-2026 - Secretaria de Estado de Saúde', 'Oficio', 'Oficios', 'publico',
                ['numero' => '210/2026', 'destinatario' => 'Prefeitura de Santo Antônio do Amparo', 'data_emissao' => $d(5)],
                'OFÍCIO SES/MG Nº 210/2026',
                ['Comunica a liberação de recursos para a campanha estadual de vacinação e solicita a indicação do responsável técnico municipal.']],
            ['parecer', 'Parecer jurídico nº 019-2026 - Contratação emergencial', 'Outros', 'Juridico', 'confidencial',
                [], 'PARECER JURÍDICO Nº 019/2026',
                ['Consulta da Secretaria de Saúde sobre a possibilidade de contratação emergencial de transporte sanitário.',
                    'Conclusão: viável, desde que caracterizada a situação emergencial e observado o art. 75, VIII, da Lei nº 14.133/2021.',
                    'Documento CONFIDENCIAL: estratégia processual do Município.']],
            ['certidao', 'Certidão negativa de débitos - Construtora Vale do Amparo', 'Certidao', 'Licitacoes', 'publico',
                [], 'CERTIDÃO NEGATIVA DE DÉBITOS MUNICIPAIS',
                ['Certifica-se que não constam débitos tributários municipais em nome de Construtora Vale do Amparo Ltda.']],
        ];

        foreach ($lista as [$chave, $nome, $tipo, $pasta, $classificacao, $meta, $titulo, $paragrafos]) {
            $arquivo = $this->pdf($nome, $titulo, $paragrafos);
            $this->chamar($this->u['gestor'], Controllers\CapturaController::class, 'upload', [
                'tipo_documental_id' => $t[$tipo],
                'pasta_id'           => $this->pasta[$pasta] ?? null,
                'classificacao'      => $classificacao,
                'descricao'          => $paragrafos[0],
                'metadados'          => $meta,
            ], ['files' => [$arquivo]]);
            $this->doc[$chave] = (int) DB::table('ged_documentos')->where('nome', $nome)->orderByDesc('id')->value('id');
            DB::table('ged_documentos')->where('id', $this->doc[$chave])->update(['status' => 'publicado']);
        }

        // Relatório com segunda versão (correção após vistoria complementar)
        $v2 = $this->pdf('Relatório de vistoria - Obra da Rua José Coutinho', 'RELATÓRIO DE VISTORIA Nº 07/2026 (RETIFICADO)', [
            'Vistoria complementar realizada após a limpeza das bocas de lobo.',
            'Retificação: pendência sanada. A etapa de base e sub-base está concluída e liberada para imprimação.',
        ]);
        $this->chamar($this->u['obras'], Controllers\DocumentoController::class, 'novaVersao',
            ['comentario' => 'Retificação após vistoria complementar'], ['arquivo' => $v2], $this->doc['vistoria']);

        // Algumas leituras, para "Recentes" e "Mais acessados" terem conteúdo.
        foreach (['saude' => ['ata3', 'contrato045', 'oficio210'], 'obras' => ['contrato031', 'vistoria'], 'gestor' => ['decreto022', 'lei1845', 'contrato031']] as $quem => $docs) {
            foreach ($docs as $c) {
                $this->chamar($this->u[$quem], Controllers\DocumentoController::class, 'show', [], [], $this->doc[$c], verbo: 'GET');
            }
        }

        return true;
    }

    private function assinaturas(): bool
    {
        // Decreto 022: gestora e procuradora assinam → concluída, com manifesto.
        $this->solicitar($this->doc['decreto022'], ['gestor', 'procuradoria'], 'Assinatura do decreto de assinatura eletrônica.');
        $this->assinarSimples('procuradoria', $this->doc['decreto022']);
        $this->assinarSimples('gestor', $this->doc['decreto022']);

        // Contrato 031, EM ORDEM: obras assina; a gestora assina ao vivo na demonstração.
        $this->solicitar($this->doc['contrato031'], ['obras', 'gestor'], 'Contrato de pavimentação — fiscal e ordenadora de despesa.', sequencial: true);
        $this->assinarSimples('obras', $this->doc['contrato031']);

        // Portaria 104: pendente da gestora (para demonstrar o painel de assinaturas).
        $this->solicitar($this->doc['portaria104'], ['gestor'], 'Portaria de designação da comissão de obras.');

        return true;
    }

    private function solicitar(int $documentoId, array $quem, string $mensagem, bool $sequencial = false): void
    {
        $this->chamar($this->u['protocolo'], Controllers\AssinaturaController::class, 'solicitar', [
            'signatarios' => array_map(fn ($k) => $this->u[$k]->id, $quem),
            'mensagem'    => $mensagem,
            'prazo'       => now()->addDays(5)->toDateString(),
            'sequencial'  => $sequencial,
        ], [], $documentoId);
    }

    private function assinarSimples(string $quem, int $documentoId): void
    {
        $id = DB::table('ged_assinaturas')->where('documento_id', $documentoId)
            ->where('signatario_id', $this->u[$quem]->id)->where('status', 'pendente')->value('id');
        $this->chamar($this->u[$quem], Controllers\AssinaturaController::class, 'assinar',
            ['cpf' => preg_replace('/\D/', '', (string) $this->u[$quem]->cpf)], [], $id);
    }

    private array $tipo = [];

    private function tiposProcesso(): bool
    {
        $setorNome = fn (string $k) => (string) DB::table('ug_organograma')->where('id', $this->setor[$k])->value('nome');
        $tipos = [
            'REQ' => ['Requerimento Administrativo', 'administrativo', 72, 'Pedidos gerais de servidores e cidadãos à Administração.',
                [['campo' => 'documento_requerente', 'tipo' => 'text', 'label' => 'CPF/CNPJ do requerente', 'obrigatorio' => true]],
                [['Análise do pedido', 'analise', 'administracao', 48], ['Parecer jurídico', 'parecer', 'procuradoria', 72], ['Decisão', 'aprovacao', 'gabinete', 24]]],
            'SCS' => ['Solicitação de Compra ou Serviço', 'compras', 120, 'Demanda de aquisição ou contratação de serviço pelas secretarias.',
                [['campo' => 'valor_estimado', 'tipo' => 'money', 'label' => 'Valor estimado (R$)', 'obrigatorio' => true],
                    ['campo' => 'justificativa', 'tipo' => 'textarea', 'label' => 'Justificativa da necessidade', 'obrigatorio' => true],
                    ['campo' => 'dotacao', 'tipo' => 'text', 'label' => 'Dotação orçamentária sugerida', 'obrigatorio' => false]],
                [['Análise da demanda', 'analise', 'administracao', 24], ['Disponibilidade orçamentária', 'parecer', 'fazenda', 48],
                    ['Parecer jurídico', 'parecer', 'procuradoria', 72], ['Autorização', 'aprovacao', 'gabinete', 24]]],
            'LAS' => ['Licença ou Afastamento de Servidor', 'rh', 48, 'Férias, licenças e afastamentos de servidores.',
                [['campo' => 'modalidade', 'tipo' => 'select', 'label' => 'Modalidade', 'obrigatorio' => true, 'opcoes' => 'Férias,Licença médica,Licença prêmio'],
                    ['campo' => 'inicio', 'tipo' => 'date', 'label' => 'Início', 'obrigatorio' => true],
                    ['campo' => 'fim', 'tipo' => 'date', 'label' => 'Fim', 'obrigatorio' => true]],
                [['Análise do RH', 'analise', 'administracao', 24], ['Ciência da chefia', 'despacho', 'administracao', 24]]],
            'ALO' => ['Alvará de Licença para Obra', 'outro', 240, 'Licenciamento de construção, reforma ou demolição.',
                [['campo' => 'endereco_obra', 'tipo' => 'text', 'label' => 'Endereço da obra', 'obrigatorio' => true],
                    ['campo' => 'area', 'tipo' => 'number', 'label' => 'Área construída (m²)', 'obrigatorio' => true]],
                [['Análise técnica do projeto', 'analise', 'obras', 120], ['Vistoria', 'analise', 'obras', 72], ['Emissão do alvará', 'despacho', 'obras', 48]]],
        ];

        foreach ($tipos as $sigla => [$nome, $categoria, $sla, $descricao, $form, $etapas]) {
            $this->chamar($this->u['gestor'], Controllers\Admin\TipoProcessoController::class, 'store', [
                'nome' => $nome, 'sigla' => $sigla, 'descricao' => $descricao, 'categoria' => $categoria, 'sla_padrao_horas' => $sla,
                'schema_formulario' => $form,
                'templates_despacho' => ['Encaminho para análise e manifestação.', 'De acordo. Prossiga-se.', 'Restituo para complementação da instrução.'],
                'etapas' => array_map(fn ($e, $i) => ['nome' => $e[0], 'tipo' => $e[1], 'setor_destino' => $setorNome($e[2]), 'sla_horas' => $e[3], 'ordem' => $i + 1, 'obrigatorio' => true], $etapas, array_keys($etapas)),
            ]);
            $this->tipo[$sigla] = (int) DB::table('proc_tipos_processo')->where('sigla', $sigla)->value('id');
        }

        return true;
    }

    private function processos(): bool
    {
        // P1 — compra de medicamentos: Saúde abre → Fazenda recebe e despacha → aguardando a Procuradoria.
        $p1 = $this->abrirProcesso('saude', 'SCS', 'Aquisição de medicamentos da atenção básica', 'fazenda', [
            'valor_estimado' => '58420.00', 'justificativa' => 'Reposição do estoque das unidades de Saúde da Família para o 2º semestre.', 'dotacao' => '02.05.01.10.301.0012.3.3.90.30',
        ], 'alta');
        $this->despachar('fazenda', $p1, 'procuradoria', 'Há dotação orçamentária suficiente. Encaminho para parecer jurídico.');
        $this->chamar($this->u['saude'], Controllers\ProcessoController::class, 'comentar',
            ['conteudo' => 'Anexaremos as cotações atualizadas assim que recebidas dos fornecedores.'], [], $p1);

        // P2 — requerimento recém-protocolado, aguardando a Administração.
        $this->abrirProcesso('protocolo', 'REQ', 'Certidão de tempo de serviço', 'administracao', [
            'documento_requerente' => '529.982.247-25',
        ], 'normal', ['requerente_nome' => 'José Carlos Ribeiro', 'requerente_email' => 'jose.ribeiro@email.com']);

        // P3 — férias: Obras abre → Administração decide (deferido), assina a decisão → concluído.
        $p3 = $this->abrirProcesso('obras', 'LAS', 'Férias regulamentares — julho/2026', 'administracao', [
            'modalidade' => 'Férias', 'inicio' => now()->addDays(20)->toDateString(), 'fim' => now()->addDays(49)->toDateString(),
        ]);
        $this->chamar($this->u['gestor'], Controllers\ProcessoController::class, 'concluir',
            ['decisao' => 'deferido', 'observacao_conclusao' => 'Deferidas as férias no período requerido, conforme escala do setor.'], [], $p3);
        $assinatura = DB::table('ged_assinaturas')->where('signatario_id', $this->u['gestor']->id)->where('status', 'pendente')
            ->whereIn('solicitacao_id', DB::table('proc_processos')->where('id', $p3)->pluck('solicitacao_assinatura_id'))->value('id');
        if ($assinatura) {
            $this->chamar($this->u['gestor'], Controllers\AssinaturaController::class, 'assinar',
                ['cpf' => preg_replace('/\D/', '', (string) $this->u['gestor']->cpf)], [], $assinatura);
        }

        // P4 — compra indeferida: a decisão fica aguardando a assinatura da gestora (assinar ao vivo).
        $p4 = $this->abrirProcesso('obras', 'SCS', 'Locação de retroescavadeira por 12 meses', 'administracao', [
            'valor_estimado' => '216000.00', 'justificativa' => 'Atender à manutenção das estradas vicinais.',
        ]);
        $this->chamar($this->u['gestor'], Controllers\ProcessoController::class, 'concluir',
            ['decisao' => 'indeferido', 'observacao_conclusao' => 'Indeferido: o Município possui equipamento próprio disponível no período.', 'pular_assinatura' => true], [], $p4);

        return true;
    }

    private function abrirProcesso(string $quem, string $sigla, string $assunto, string $destino, array $form, string $prioridade = 'normal', array $requerente = []): int
    {
        $antes = (int) DB::table('proc_processos')->max('id');
        $this->chamar($this->u[$quem], Controllers\ProcessoController::class, 'store', $requerente + [
            'assunto' => $assunto, 'tipo_processo_id' => $this->tipo[$sigla], 'descricao' => $assunto,
            'dados_formulario' => $form, 'prioridade' => $prioridade,
            'setor_origem' => (string) DB::table('ug_organograma')->where('id', $this->u[$quem]->unidade_id)->value('nome'),
            'setor_destino_inicial' => $this->setor[$destino],
        ]);
        $id = (int) DB::table('proc_processos')->where('id', '>', $antes)->orderByDesc('id')->value('id');
        if (! $id) {
            throw new RuntimeException("Processo '{$assunto}' não foi criado.");
        }

        return $id;
    }

    private function despachar(string $quem, int $processoId, string $destino, string $texto): void
    {
        $etapa = (int) DB::table('proc_tramitacoes')->where('processo_id', $processoId)
            ->whereIn('status', ['pendente', 'recebido'])->orderByDesc('id')->value('id');
        $this->chamar($this->u[$quem], Controllers\TramitacaoController::class, 'receber', [], [], $etapa);
        $this->chamar($this->u[$quem], Controllers\TramitacaoController::class, 'despachar',
            ['despacho' => $texto, 'setor_destino' => $this->setor[$destino]], [], $etapa);
    }

    private function comunicacoes(): bool
    {
        $this->chamar($this->u['gestor'], Controllers\MemorandoController::class, 'store', [
            'assunto' => 'Atualização do cadastro funcional dos servidores da Saúde',
            'conteudo' => '<p>Solicitamos a conferência e atualização dos dados funcionais dos servidores lotados nas unidades de Saúde da Família até o dia 30 deste mês.</p>',
            'tipo_destino' => 'setor', 'unidade_id' => $this->setor['saude'],
        ]);
        $this->chamar($this->u['obras'], Controllers\MemorandoController::class, 'store', [
            'assunto' => 'Reunião de planejamento das obras do 2º semestre',
            'conteudo' => '<p>Convocamos para reunião de planejamento das obras do 2º semestre, na próxima terça-feira, às 9h, na sala de reuniões do Gabinete.</p>',
            'tipo_destino' => 'usuario', 'destinatarios' => [$this->u['gestor']->id, $this->u['fazenda']->id],
        ]);

        $this->chamar($this->u['gestor'], Controllers\CircularController::class, 'store', [
            'assunto' => 'Implantação da gestão eletrônica de documentos',
            'conteudo' => '<p>Informamos que, a partir desta data, os memorandos, ofícios e processos administrativos passam a tramitar exclusivamente pelo sistema de gestão eletrônica de documentos.</p>',
            'destino_tipo' => 'usuarios',
            'destinatarios' => array_map(fn ($k) => $this->u[$k]->id, ['protocolo', 'saude', 'obras', 'procuradoria', 'fazenda']),
        ]);
        $circular = (int) DB::table('proc_circulares')->max('id');
        foreach (['saude', 'obras', 'fazenda'] as $quem) {
            $this->chamar($this->u[$quem], Controllers\CircularController::class, 'show', [], [], $circular, verbo: 'GET');
        }

        $this->chamar($this->u['saude'], Controllers\OficioController::class, 'store', [
            'assunto' => 'Indicação do responsável técnico pela campanha de vacinação',
            'conteudo' => '<p>Em resposta ao Ofício SES/MG nº 210/2026, indicamos como responsável técnico municipal a enfermeira coordenadora da Atenção Primária.</p>',
            'destinatario_nome' => 'Superintendência Regional de Saúde de Divinópolis',
            'destinatario_cargo' => 'Superintendente',
            'destinatario_orgao' => 'Secretaria de Estado de Saúde de Minas Gerais',
            'destinatario_email' => 'srs.divinopolis@saude.mg.gov.br',
            'modo_envio' => 'fisico',
        ]);

        return true;
    }

    private function portal(string $senha): bool
    {
        // Serviços que viram processo automaticamente.
        Servico::withoutGlobalScope('ug')->where('ug_id', $this->ugId)->orderBy('id')->get()
            ->each(function (Servico $s, $i) {
                $obra = Str::contains(Str::lower(Str::ascii($s->titulo)), ['obra', 'alvara', 'constru']);
                $s->update([
                    'tipo_processo_id'     => $obra ? $this->tipo['ALO'] : $this->tipo['REQ'],
                    'setor_responsavel_id' => $obra ? $this->setor['obras'] : $this->setor['administracao'],
                ]);
            });

        $servico = Servico::withoutGlobalScope('ug')->where('ug_id', $this->ugId)->where('tipo_processo_id', $this->tipo['ALO'])->first()
            ?? Servico::withoutGlobalScope('ug')->where('ug_id', $this->ugId)->orderBy('id')->first();

        $cidadaoId = DB::table('portal_cidadaos')->insertGetId([
            'nome' => 'Beatriz Moreira Santos', 'email' => 'cidadao@gpedocs.demo', 'cpf' => '111.444.777-35',
            'telefone' => '(35) 99876-5432', 'senha' => Hash::make($senha), 'email_verificado_em' => now(), 'ativo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($servico) {
            $sol = Solicitacao::query()->withoutGlobalScope('ug')->create([
                'codigo' => Solicitacao::gerarCodigo($this->ugId), 'ug_id' => $this->ugId, 'servico_id' => $servico->id,
                'cidadao_id' => $cidadaoId, 'anonima' => false, 'status' => 'aberta',
                'descricao' => 'Solicito alvará para ampliação de residência (cozinha e área de serviço, 32 m²) na Rua Coronel Joaquim Antônio, 210.',
                'telefone_contato' => '(35) 99876-5432', 'email_contato' => 'cidadao@gpedocs.demo',
            ]);
            SolicitacaoEvento::create(['solicitacao_id' => $sol->id, 'tipo' => 'criada', 'autor_tipo' => 'cidadao',
                'autor_nome' => 'Beatriz Moreira Santos', 'autor_cidadao_id' => $cidadaoId, 'status_novo' => 'aberta', 'mensagem' => 'Solicitação registrada pelo portal.']);
            $processo = app(\App\Services\AbrirProcessoDoPortalService::class)->abrir($sol, $servico);
            if ($processo) {
                $sol->update(["processo_id" => $processo->id]); // como o SolicitacaoController
            }
        }

        return true;
    }

    /** @return list<array{0:string,1:string}> */
    private function sistemasIntegrados(): array
    {
        $tokens = [];
        foreach (['gpe2' => 'GPE2 — Gestão Pública (contabilidade, compras, RH)', 'tributario' => 'Tributário — Gestão da Receita Municipal'] as $codigo => $nome) {
            $sistema = SistemaIntegrado::firstOrNew(['codigo' => $codigo]);
            $sistema->fill(['nome' => $nome, 'descricao' => 'Cadastrado para a demonstração integrada', 'ativo' => true]);
            $token = $sistema->gerarToken();
            $sistema->save();
            $tokens[] = [$codigo, $token];
        }

        return $tokens;
    }

    // ─────────────────────────────── utilitários ─────────────────────────────

    /**
     * Chama a ação de um controller como se fosse a requisição do usuário: autenticado,
     * com a UG na sessão. Erro de validação vira exceção com a mensagem — a demonstração
     * não pode sair pela metade em silêncio.
     */
    private function chamar(User $user, string $controller, string $metodo, array $dados = [], array $arquivos = [], ...$args)
    {
        $verbo = $args['verbo'] ?? 'POST';
        unset($args['verbo']);

        Auth::setUser($user);
        $request = Request::create('/demonstracao', $verbo, $dados, [], $arquivos, ['HTTP_HOST' => 'localhost']);
        $request->setUserResolver(fn () => $user);
        $sessao = app('session')->driver();
        $sessao->put('ug_id', $this->ugId);
        $request->setLaravelSession($sessao);
        app()->instance('request', $request);

        // As ações variam: (Request $r, $id), ($id) ou (Request $r). O Request entra onde
        // o parâmetro é tipado como Request; os demais recebem os valores na ordem.
        $valores = array_values($args);
        $parametros = [];
        foreach ((new \ReflectionMethod($controller, $metodo))->getParameters() as $p) {
            $tipo = $p->getType();
            if ($tipo instanceof \ReflectionNamedType && is_a($tipo->getName(), Request::class, true)) {
                $parametros[] = $request;
            } elseif ($valores) {
                $parametros[] = array_shift($valores);
            } elseif ($tipo instanceof \ReflectionNamedType && ! $tipo->isBuiltin()) {
                $parametros[] = app($tipo->getName()); // serviços injetados (ex.: validador)
            }
        }

        try {
            $resposta = app($controller)->{$metodo}(...$parametros);
        } catch (ValidationException $e) {
            throw new RuntimeException("{$controller}@{$metodo}: " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE));
        }

        if ($erro = $sessao->pull('error')) {
            throw new RuntimeException("{$controller}@{$metodo}: {$erro}");
        }

        return $resposta;
    }

    /** PDF timbrado do município com o texto do ato. */
    private function pdf(string $nomeArquivo, string $titulo, array $paragrafos): UploadedFile
    {
        $ug = DB::table('ugs')->where('id', $this->ugId)->first();
        $endereco = DB::table('ugs as u')
            ->leftJoin('logradouro as l', 'l.id', '=', 'u.logradouro_id')
            ->leftJoin('bairro as b', 'b.id', '=', 'l.bairro_id')
            ->leftJoin('municipio as m', 'm.id', '=', 'b.municipio_id')
            ->where('u.id', $this->ugId)
            ->selectRaw("concat_ws(', ', l.nome, u.numero, b.nome, m.nome || ' - ' || m.uf_id, l.cep) as e")
            ->value('e');
        $corpo = implode('', array_map(fn ($p) => '<p>' . e($p) . '</p>', $paragrafos));
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;margin:40px;color:#222}'
            . '.cab{text-align:center;border-bottom:2px solid #1f3864;padding-bottom:10px;margin-bottom:30px}'
            . '.cab h1{font-size:15px;margin:0;color:#1f3864}.cab p{margin:2px 0;font-size:10px;color:#555}'
            . 'h2{text-align:center;font-size:14px;margin:20px 0}p{text-align:justify;line-height:1.6}'
            . '.ass{margin-top:60px;text-align:center}</style></head><body>'
            . '<div class="cab"><h1>' . e(mb_strtoupper((string) $ug->nome)) . '</h1><p>CNPJ ' . e((string) $ug->cnpj) . '</p><p>' . e((string) $endereco) . '</p></div>'
            . '<h2>' . e($titulo) . '</h2>' . $corpo
            . '<p style="margin-top:30px">Santo Antônio do Amparo, ' . now()->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY') . '.</p>'
            . '<div class="ass">______________________________<br>Documento de demonstração do GPE Docs</div></body></html>';

        $caminho = tempnam(sys_get_temp_dir(), 'demo') . '.pdf';
        file_put_contents($caminho, Pdf::loadHTML($html)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output());

        return new UploadedFile($caminho, $nomeArquivo . '.pdf', 'application/pdf', null, true);
    }
}
