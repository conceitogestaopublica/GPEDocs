<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

/**
 * Formato único dos dados que vêm de qualquer sistema tributário. O mapeamento do adaptador
 * REST genérico aponta, para cada campo daqui, onde ele está na resposta do sistema.
 */
final class Normalizado
{
    public const IMOVEL = [
        'inscricao' => 'Inscrição imobiliária', 'proprietario_nome' => 'Proprietário', 'proprietario_documento' => 'CPF/CNPJ do proprietário',
        'logradouro' => 'Logradouro', 'numero' => 'Número', 'complemento' => 'Complemento', 'bairro' => 'Bairro', 'cep' => 'CEP',
        'zona' => 'Zona / setor', 'quadra' => 'Quadra', 'lote' => 'Lote', 'area_terreno' => 'Área do terreno (m²)',
        'area_construida' => 'Área construída (m²)', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'situacao' => 'Situação',
    ];

    public const GUIA = [
        'id_externo' => 'Identificador da guia', 'numero' => 'Número', 'valor' => 'Valor', 'vencimento' => 'Vencimento',
        'situacao' => 'Situação (emitida, paga, vencida, cancelada)', 'linha_digitavel' => 'Linha digitável', 'codigo_barras' => 'Código de barras',
        'pix' => 'PIX copia e cola', 'pdf_url' => 'Endereço do PDF',
    ];

    public const DOCUMENTO = [
        'id_externo' => 'Identificador do documento', 'numero' => 'Número', 'situacao' => 'Situação (solicitado, emitido, cancelado)',
        'emitido_em' => 'Data de emissão', 'validade' => 'Validade', 'pdf_url' => 'Endereço do PDF',
    ];

    public const DOCUMENTOS = ['alvara_construcao' => 'Alvará de construção', 'habitese' => 'Habite-se', 'certidao' => 'Certidão'];

    /** Situações reconhecidas da guia (o resto vira "emitida"). */
    public static function situacaoGuia(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));

        return match (true) {
            in_array($s, ['paga', 'pago', 'quitada', 'quitado', 'liquidada', 'baixada', 'paid'], true) => 'paga',
            in_array($s, ['vencida', 'vencido', 'expired'], true) => 'vencida',
            in_array($s, ['cancelada', 'cancelado', 'canceled', 'cancelled'], true) => 'cancelada',
            default => 'emitida',
        };
    }

    /**
     * @param array<string, mixed> $origem
     * @param array<string, string> $campos campo normalizado => caminho na origem (notação de ponto)
     * @return array<string, mixed>
     */
    public static function mapear(array $origem, array $campos, array $modelo): array
    {
        $saida = [];
        foreach (array_keys($modelo) as $campo) {
            $caminho = $campos[$campo] ?? null;
            $saida[$campo] = $caminho !== null && $caminho !== '' ? data_get($origem, $caminho) : null;
        }

        return $saida;
    }
}