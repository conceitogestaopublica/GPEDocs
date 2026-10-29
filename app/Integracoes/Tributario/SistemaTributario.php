<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

/**
 * Contrato do GED com qualquer sistema tributário (GPE Tributário, de terceiros ou nenhum).
 * Cada adaptador diz em recursos() o que oferece; o que não oferece, o servidor informa à mão.
 *
 * Os retornos são arrays normalizados (ver Normalizado), iguais para todo adaptador.
 */
interface SistemaTributario
{
    public const IMOVEL = 'imovel';                         // consultar imóvel pela inscrição
    public const GUIA_EMITIR = 'guia_emitir';               // emitir guia de taxa para o processo
    public const GUIA_CONSULTAR = 'guia_consultar';         // situação da guia (paga, vencida…)
    public const DOCUMENTO_SOLICITAR = 'documento_solicitar'; // alvará de construção, habite-se, certidão
    public const DOCUMENTO_CONSULTAR = 'documento_consultar';
    public const FISCALIZACAO_ABRIR = 'fiscalizacao_abrir'; // ordem de fiscalização (obras e posturas)

    public const RECURSOS = [
        self::IMOVEL => 'Consultar imóvel pela inscrição',
        self::GUIA_EMITIR => 'Emitir guia de taxa',
        self::GUIA_CONSULTAR => 'Consultar situação da guia',
        self::DOCUMENTO_SOLICITAR => 'Solicitar alvará, habite-se ou certidão',
        self::DOCUMENTO_CONSULTAR => 'Consultar documento emitido',
        self::FISCALIZACAO_ABRIR => 'Abrir ordem de fiscalização',
    ];

    public function nome(): string;

    /** @return list<string> recursos que este sistema oferece */
    public function recursos(): array;

    public function oferece(string $recurso): bool;

    /** @return array{ok: bool, mensagem: string} */
    public function testarConexao(): array;

    /** @return array<string, mixed>|null imóvel normalizado (Normalizado::IMOVEL) ou null se não existe */
    public function consultarImovel(string $inscricao): ?array;

    /**
     * @param array{taxa: string, valor?: float|null, documento: string, nome: string, inscricao?: ?string, processo: string, descricao?: ?string, vencimento?: ?string} $dados
     * @return array<string, mixed> guia normalizada (Normalizado::GUIA)
     */
    public function emitirGuia(array $dados): array;

    /** @return array<string, mixed> guia normalizada */
    public function consultarGuia(string $idExterno): array;

    /**
     * @param string $tipo alvara_construcao | habitese | certidao
     * @param array<string, mixed> $dados inscricao, documento, nome, processo, area, responsavel_tecnico…
     * @return array<string, mixed> documento normalizado (Normalizado::DOCUMENTO)
     */
    public function solicitarDocumento(string $tipo, array $dados): array;

    /** @return array<string, mixed> documento normalizado */
    public function consultarDocumento(string $tipo, string $idExterno): array;

    /** Bytes do PDF de um documento/guia (URL devolvida pelo próprio sistema). */
    public function baixarPdf(string $url): string;

    /**
     * @param array<string, mixed> $dados motivo, inscricao, endereco, processo, descricao, latitude, longitude
     * @return array<string, mixed> {id_externo, numero, situacao}
     */
    public function abrirFiscalizacao(array $dados): array;
}
