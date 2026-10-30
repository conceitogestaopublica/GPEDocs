<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

/**
 * Sem sistema tributário integrado: nenhum recurso automático. O servidor informa os dados do
 * imóvel, da guia e dos documentos na tela do processo e anexa os PDFs.
 */
final class SemIntegracao implements SistemaTributario
{
    public function nome(): string
    {
        return 'Sem integração';
    }

    public function recursos(): array
    {
        return [];
    }

    public function oferece(string $recurso): bool
    {
        return false;
    }

    public function testarConexao(): array
    {
        return ['ok' => true, 'mensagem' => 'Sem integração: os dados do tributário são informados à mão no processo.'];
    }

    public function consultarImovel(string $inscricao): ?array
    {
        throw new FalhaTributario('Sem sistema tributário integrado.');
    }

    public function emitirGuia(array $dados): array
    {
        throw new FalhaTributario('Sem sistema tributário integrado.');
    }

    public function consultarGuia(string $idExterno): array
    {
        throw new FalhaTributario('Sem sistema tributário integrado.');
    }

    public function solicitarDocumento(string $tipo, array $dados): array
    {
        throw new FalhaTributario('Sem sistema tributário integrado.');
    }

    public function consultarDocumento(string $tipo, string $idExterno): array
    {
        throw new FalhaTributario('Sem sistema tributário integrado.');
    }

    public function baixarPdf(string $url): string
    {
        throw new FalhaTributario('Sem sistema tributário integrado.');
    }

    public function abrirFiscalizacao(array $dados): array
    {
        throw new FalhaTributario('Sem sistema tributário integrado.');
    }
}