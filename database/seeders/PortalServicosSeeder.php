<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\CategoriaServico;
use App\Models\Portal\Servico;
use App\Models\Ug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PortalServicosSeeder extends Seeder
{
    public function run(): void
    {
        $ug = Ug::query()->orderBy('id')->first();
        if (! $ug) {
            $this->command?->warn('Nenhuma UG encontrada — pule o seeder Portal/Servicos.');
            return;
        }

        $categorias = [
            ['Saúde',         'fas fa-heartbeat',  'red',     'Atendimento medico, exames, vacinacao e medicamentos'],
            ['Educação',      'fas fa-graduation-cap', 'blue',    'Matriculas, transporte escolar, bolsas e creches'],
            ['Tributos',      'fas fa-file-invoice-dollar', 'amber',   'IPTU, ISS, taxas, certidoes e parcelamentos'],
            ['Cidadania',     'fas fa-id-card',    'indigo',  'Documentos, registros e direitos do cidadao'],
            ['Obras',         'fas fa-hard-hat',   'orange',  'Alvaras, habite-se, regularizacao e fiscalizacao'],
            ['Meio Ambiente', 'fas fa-leaf',       'green',   'Licenciamento ambiental, podas, denuncias e limpeza'],
            ['Assistência Social', 'fas fa-hands-helping', 'pink', 'CRAS, beneficios, programas sociais e cadastros'],
            ['Transporte',    'fas fa-bus',        'cyan',    'Transporte publico, estacionamento e mobilidade urbana'],
        ];

        $catModels = [];
        foreach ($categorias as $i => [$nome, $icone, $cor, $desc]) {
            $catModels[$nome] = CategoriaServico::query()->withoutGlobalScope('ug')->updateOrCreate(
                ['ug_id' => $ug->id, 'slug' => Str::slug($nome)],
                [
                    'nome'      => $nome,
                    'icone'     => $icone,
                    'cor'       => $cor,
                    'descricao' => $desc,
                    'ordem'     => $i,
                    'ativo'     => true,
                ]
            );
        }

        $servicos = [
            [
                'titulo'             => '2ª via de IPTU',
                'categoria'          => 'Tributos',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Emissao da 2ª via do carne do IPTU para pagamento ou comprovacao.',
                'descricao_completa' => 'Servico para emissao da segunda via do Imposto Predial e Territorial Urbano (IPTU). O contribuinte pode emitir o boleto atualizado para pagamento ou para comprovacao de regularidade fiscal.',
                'requisitos'         => 'Ser proprietario do imovel ou ter procuracao do proprietario.',
                'documentos_necessarios' => ['CPF do proprietario', 'Numero da inscricao imobiliaria ou endereco completo do imovel'],
                'prazo_entrega'      => 'Imediato (online) / Ate 1 dia util (presencial)',
                'custo'              => 'Gratuito',
                'canais'             => ['online' => true, 'presencial' => true, 'telefone' => '0800-000-0000'],
                'orgao_responsavel'  => 'Secretaria de Fazenda',
                'legislacao'         => 'Codigo Tributario Municipal',
                'palavras_chave'     => ['iptu', 'imposto', 'imovel', 'segunda via', 'boleto'],
                'icone'              => 'fas fa-file-invoice-dollar',
            ],
            [
                'titulo'             => 'Matrícula em Creche Municipal',
                'categoria'          => 'Educação',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Solicitacao de vaga em creche para criancas de 0 a 3 anos.',
                'descricao_completa' => 'Inscricao para vaga em creches municipais, com prioridade definida por criterios socioeconomicos e proximidade da residencia.',
                'requisitos'         => 'Crianca com idade entre 0 e 3 anos e 11 meses. Familia residente no municipio.',
                'documentos_necessarios' => ['Certidao de nascimento da crianca', 'Comprovante de residencia', 'CPF dos responsaveis', 'Comprovante de renda familiar'],
                'prazo_entrega'      => 'Conforme calendario letivo / lista de espera',
                'custo'              => 'Gratuito',
                'canais'             => ['online' => true, 'presencial' => true],
                'orgao_responsavel'  => 'Secretaria de Educacao',
                'legislacao'         => 'LDB 9.394/96 — Lei de Diretrizes e Bases da Educacao',
                'palavras_chave'     => ['creche', 'matricula', 'crianca', 'educacao infantil', 'vaga'],
                'icone'              => 'fas fa-baby',
            ],
            [
                'titulo'             => 'Marcação de Consulta - UBS',
                'categoria'          => 'Saúde',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Agendamento de consulta medica nas Unidades Basicas de Saude.',
                'descricao_completa' => 'Marcacao de consultas com clinico geral, pediatra, ginecologista e outras especialidades disponiveis nas UBS do municipio.',
                'requisitos'         => 'Estar cadastrado em uma UBS do municipio (Cartao SUS).',
                'documentos_necessarios' => ['Cartao SUS', 'Documento de identidade com foto', 'Comprovante de residencia'],
                'prazo_entrega'      => 'Conforme disponibilidade da agenda',
                'custo'              => 'Gratuito',
                'canais'             => ['online' => true, 'presencial' => true, 'telefone' => '136'],
                'orgao_responsavel'  => 'Secretaria de Saude',
                'legislacao'         => 'Lei 8.080/90 — Lei do SUS',
                'palavras_chave'     => ['saude', 'consulta', 'ubs', 'medico', 'agendamento', 'sus'],
                'icone'              => 'fas fa-stethoscope',
            ],
            [
                'titulo'             => 'Alvará de Construção',
                'categoria'          => 'Obras',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Autorizacao para construcao, reforma ou ampliacao de imovel.',
                'descricao_completa' => 'Documento obrigatorio para inicio de obras de construcao, reforma ou ampliacao em imoveis localizados no municipio. Verifica conformidade com o Plano Diretor.',
                'requisitos'         => 'Imovel regularizado, projeto arquitetonico assinado por profissional habilitado.',
                'documentos_necessarios' => ['Projeto arquitetonico em PDF', 'ART/RRT do responsavel tecnico', 'Escritura ou contrato do imovel', 'IPTU em dia', 'Memorial descritivo'],
                'prazo_entrega'      => 'Ate 30 dias uteis',
                'custo'              => 'Conforme tabela de taxas — varia com o tamanho da obra',
                'canais'             => ['online' => true, 'presencial' => true],
                'orgao_responsavel'  => 'Secretaria de Obras e Urbanismo',
                'legislacao'         => 'Lei do Plano Diretor Municipal',
                'palavras_chave'     => ['alvara', 'construcao', 'obra', 'reforma', 'urbanismo'],
                'icone'              => 'fas fa-hard-hat',
            ],
            [
                'titulo'             => 'Cadastro Único (CadÚnico)',
                'categoria'          => 'Assistência Social',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Inscricao no Cadastro Unico para acesso a programas sociais.',
                'descricao_completa' => 'O CadUnico e a porta de entrada para mais de 30 programas sociais do governo federal, como Bolsa Familia, Tarifa Social de Energia e Minha Casa Minha Vida.',
                'requisitos'         => 'Familia com renda mensal de ate meio salario minimo por pessoa, ou ate 3 salarios minimos no total.',
                'documentos_necessarios' => ['CPF de todos da familia', 'Documento de identidade do responsavel familiar', 'Comprovante de residencia recente', 'Carteira de trabalho (se houver)'],
                'prazo_entrega'      => 'Ate 30 dias para inclusao no sistema federal',
                'custo'              => 'Gratuito',
                'canais'             => ['presencial' => true, 'observacoes' => 'Atendimento exclusivo nos CRAS'],
                'orgao_responsavel'  => 'Secretaria de Assistencia Social',
                'legislacao'         => 'Decreto 6.135/2007',
                'palavras_chave'     => ['cadunico', 'cadastro unico', 'bolsa familia', 'cras', 'beneficio'],
                'icone'              => 'fas fa-id-card-alt',
            ],
            [
                'titulo'             => 'Poda de Árvore em Via Pública',
                'categoria'          => 'Meio Ambiente',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Solicitacao de poda ou supressao de arvore em area publica.',
                'descricao_completa' => 'Para arvores em via publica que oferecam risco a fiacao eletrica, edificacoes ou pedestres, ou que estejam doentes. A avaliacao e feita por equipe tecnica.',
                'requisitos'         => 'Arvore em via publica (logradouros, pracas). Para arvores em area privada, e necessaria autorizacao ambiental.',
                'documentos_necessarios' => ['Endereco completo da arvore', 'Foto da arvore (opcional, agiliza)', 'Justificativa do pedido'],
                'prazo_entrega'      => 'Ate 60 dias (apos vistoria tecnica)',
                'custo'              => 'Gratuito',
                'canais'             => ['online' => true, 'telefone' => '0800-000-0000'],
                'orgao_responsavel'  => 'Secretaria de Meio Ambiente',
                'legislacao'         => 'Codigo Florestal Municipal',
                'palavras_chave'     => ['poda', 'arvore', 'meio ambiente', 'verde', 'via publica'],
                'icone'              => 'fas fa-tree',
            ],
            [
                'titulo'             => 'Certidão Negativa de Débitos Municipais',
                'categoria'          => 'Tributos',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Documento que comprova inexistencia de debitos com a prefeitura.',
                'descricao_completa' => 'Documento exigido em processos de venda de imovel, abertura de empresa, financiamentos e licitacoes. Validade de 60 dias.',
                'requisitos'         => 'Estar em dia com tributos municipais (IPTU, ISS, taxas).',
                'documentos_necessarios' => ['CPF/CNPJ do solicitante'],
                'prazo_entrega'      => 'Imediato (online) / Ate 2 dias uteis (presencial)',
                'custo'              => 'Gratuito',
                'canais'             => ['online' => true, 'presencial' => true],
                'orgao_responsavel'  => 'Secretaria de Fazenda',
                'legislacao'         => 'Codigo Tributario Municipal',
                'palavras_chave'     => ['certidao', 'negativa', 'debitos', 'cnd', 'tributo'],
                'icone'              => 'fas fa-file-contract',
            ],
            [
                'titulo'             => 'Cartão do Transporte Público',
                'categoria'          => 'Transporte',
                'publico_alvo'       => 'cidadao',
                'descricao_curta'    => 'Emissao de cartao para uso em onibus do transporte publico.',
                'descricao_completa' => 'Cartao recarregavel utilizado nos onibus do sistema de transporte publico municipal. Existem versoes comum, estudante, idoso e gratuidade.',
                'requisitos'         => 'Residir no municipio. Para gratuidades, cumprir os requisitos legais.',
                'documentos_necessarios' => ['Documento com foto', 'CPF', 'Comprovante de residencia', 'Comprovante de matricula (estudantes)', 'Foto 3x4'],
                'prazo_entrega'      => 'Ate 10 dias uteis',
                'custo'              => 'R$ 15,00 (primeira via comum) / Gratuito (gratuidades)',
                'canais'             => ['presencial' => true],
                'orgao_responsavel'  => 'Secretaria de Mobilidade',
                'legislacao'         => 'Estatuto do Idoso, Lei Municipal de Transporte',
                'palavras_chave'     => ['transporte', 'onibus', 'cartao', 'passagem', 'gratuidade'],
                'icone'              => 'fas fa-id-badge',
            ],
        ];

        foreach ($servicos as $i => $servico) {
            $cat = $catModels[$servico['categoria']] ?? null;
            Servico::query()->withoutGlobalScope('ug')->updateOrCreate(
                ['ug_id' => $ug->id, 'slug' => Str::slug($servico['titulo'])],
                [
                    'categoria_id'           => $cat?->id,
                    'titulo'                 => $servico['titulo'],
                    'publico_alvo'           => $servico['publico_alvo'],
                    'descricao_curta'        => $servico['descricao_curta'],
                    'descricao_completa'     => $servico['descricao_completa'],
                    'requisitos'             => $servico['requisitos'],
                    'documentos_necessarios' => $servico['documentos_necessarios'],
                    'prazo_entrega'          => $servico['prazo_entrega'],
                    'custo'                  => $servico['custo'],
                    'canais'                 => $servico['canais'],
                    'orgao_responsavel'      => $servico['orgao_responsavel'],
                    'legislacao'             => $servico['legislacao'],
                    'palavras_chave'         => $servico['palavras_chave'],
                    'icone'                  => $servico['icone'],
                    'publicado'              => true,
                    'ordem'                  => $i,
                ]
            );
        }

        $this->command?->info('Carta de Servicos populada para UG '.$ug->codigo.': '.count($categorias).' categorias e '.count($servicos).' servicos.');
    }
}
