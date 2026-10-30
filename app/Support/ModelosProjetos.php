<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\TipoProcesso;

/**
 * Modelos de assunto para aprovação de projetos (TR 4.48.1, 4.48.2, 4.48.15): construção (por uso)
 * e parcelamento do solo, com os índices urbanísticos calculados no formulário (CA, TO, TP;
 * percentuais de áreas públicas) e os documentos exigidos (projeto em PDF ou DWG, ART/RRT,
 * matrícula). Cria só o que não existe (pela sigla); o município ajusta depois na tela.
 */
final class ModelosProjetos
{
    /** @return list<string> nomes criados */
    public static function criar(?int $userId): array
    {
        $criados = [];
        foreach (self::modelos() as $sigla => $m) {
            if (TipoProcesso::withoutGlobalScopes()->where('sigla', $sigla)->exists()) {
                continue;
            }
            TipoProcesso::withoutGlobalScopes()->create($m + ['sigla' => $sigla, 'categoria' => 'administrativo', 'ativo' => true, 'criado_por' => $userId,
                'avaliacao' => true, 'localizacao' => 'opcional']);
            $criados[] = $m['nome'];
        }

        return $criados;
    }

    private static function modelos(): array
    {
        $comuns = [
            ['campo' => 'inscricao', 'label' => 'Inscrição imobiliária', 'tipo' => 'text', 'obrigatorio' => true, 'ajuda' => 'Como está no carnê do IPTU.'],
            ['campo' => 'endereco_obra', 'label' => 'Endereço', 'tipo' => 'cep', 'obrigatorio' => true],
            ['campo' => 'documento_proprietario', 'label' => 'CPF/CNPJ do proprietário', 'tipo' => 'cpf_cnpj', 'obrigatorio' => true],
            ['campo' => 'responsavel_tecnico', 'label' => 'Responsável técnico', 'tipo' => 'text', 'obrigatorio' => true],
            ['campo' => 'registro_profissional', 'label' => 'Registro (CREA/CAU) e ART/RRT', 'tipo' => 'text', 'obrigatorio' => true, 'ajuda' => 'Ex.: CAU A12345-6, RRT 1234567'],
        ];
        $docs = fn (array $extra) => [...$extra,
            ['nome' => 'ART ou RRT do responsável técnico', 'extensoes' => 'pdf', 'obrigatorio' => true],
            ['nome' => 'Matrícula atualizada do imóvel', 'extensoes' => 'pdf', 'obrigatorio' => true]];

        return [
            'APC' => [
                'nome' => 'Aprovação de projeto de construção', 'sla_padrao_horas' => 720,
                'descricao' => 'Construção unifamiliar, multifamiliar, institucional, comercial, de serviço ou industrial (TR 4.48.1).',
                'schema_formulario' => [
                    ['campo' => 'uso', 'label' => 'Uso da edificação', 'tipo' => 'select', 'opcoes' => 'Unifamiliar,Multifamiliar,Institucional,Comércio,Serviço,Indústria', 'obrigatorio' => true],
                    ...$comuns,
                    ['campo' => 'area_terreno', 'label' => 'Área do terreno (m²)', 'tipo' => 'number', 'obrigatorio' => true],
                    ['campo' => 'area_projecao', 'label' => 'Área de projeção da edificação (m²)', 'tipo' => 'number', 'obrigatorio' => true, 'ajuda' => 'Área ocupada no térreo, vista de cima.'],
                    ['campo' => 'area_construida', 'label' => 'Área construída total (m²)', 'tipo' => 'number', 'obrigatorio' => true],
                    ['campo' => 'area_permeavel', 'label' => 'Área permeável (m²)', 'tipo' => 'number', 'obrigatorio' => true],
                    ['campo' => 'pavimentos', 'label' => 'Número de pavimentos', 'tipo' => 'number', 'obrigatorio' => true],
                    ['campo' => 'ca', 'label' => 'Coeficiente de aproveitamento (CA)', 'tipo' => 'calculo', 'formula' => '{area_construida} / {area_terreno}'],
                    ['campo' => 'to', 'label' => 'Taxa de ocupação (%)', 'tipo' => 'calculo', 'formula' => '{area_projecao} / {area_terreno} * 100'],
                    ['campo' => 'tp', 'label' => 'Taxa de permeabilidade (%)', 'tipo' => 'calculo', 'formula' => '{area_permeavel} / {area_terreno} * 100'],
                ],
                'anexos_exigidos' => $docs([['nome' => 'Projeto arquitetônico', 'extensoes' => 'pdf,dwg', 'obrigatorio' => true],
                    ['nome' => 'Memorial descritivo', 'extensoes' => 'pdf', 'obrigatorio' => false]]),
            ],
            'PSO' => [
                'nome' => 'Parcelamento do solo', 'sla_padrao_horas' => 1440,
                'descricao' => 'Desmembramento, remembramento, retificação de área, loteamento e condomínio de lotes (TR 4.48.2).',
                'schema_formulario' => [
                    ['campo' => 'modalidade_parcelamento', 'label' => 'Modalidade', 'tipo' => 'select', 'opcoes' => 'Desmembramento,Remembramento,Retificação de área,Loteamento,Condomínio de lotes', 'obrigatorio' => true],
                    ...$comuns,
                    ['campo' => 'area_gleba', 'label' => 'Área total da gleba (m²)', 'tipo' => 'number', 'obrigatorio' => true],
                    ['campo' => 'numero_lotes', 'label' => 'Número de lotes resultantes', 'tipo' => 'number', 'obrigatorio' => true],
                    ['campo' => 'area_lotes', 'label' => 'Área dos lotes (m²)', 'tipo' => 'number', 'obrigatorio' => true],
                    ['campo' => 'area_vias', 'label' => 'Área de vias (m²)', 'tipo' => 'number', 'obrigatorio' => false],
                    ['campo' => 'area_institucional', 'label' => 'Área institucional (m²)', 'tipo' => 'number', 'obrigatorio' => false],
                    ['campo' => 'area_verde', 'label' => 'Área verde (m²)', 'tipo' => 'number', 'obrigatorio' => false],
                    ['campo' => 'perc_vias', 'label' => 'Vias (% da gleba)', 'tipo' => 'calculo', 'formula' => '{area_vias} / {area_gleba} * 100'],
                    ['campo' => 'perc_institucional', 'label' => 'Área institucional (% da gleba)', 'tipo' => 'calculo', 'formula' => '{area_institucional} / {area_gleba} * 100'],
                    ['campo' => 'perc_verde', 'label' => 'Área verde (% da gleba)', 'tipo' => 'calculo', 'formula' => '{area_verde} / {area_gleba} * 100'],
                ],
                'anexos_exigidos' => $docs([['nome' => 'Levantamento planialtimétrico', 'extensoes' => 'pdf,dwg', 'obrigatorio' => true],
                    ['nome' => 'Projeto de parcelamento', 'extensoes' => 'pdf,dwg', 'obrigatorio' => true]]),
            ],
        ];
    }
}