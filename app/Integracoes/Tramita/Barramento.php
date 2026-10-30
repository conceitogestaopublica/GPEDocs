<?php

declare(strict_types=1);

namespace App\Integracoes\Tramita;

/**
 * Operações do barramento Tramita GOV.BR (TR 4.28) de que o GED precisa: achar a estrutura de destino,
 * enviar (devolve NRE e IDT), acompanhar até o recibo de conclusão ou a recusa, listar e baixar o que outros
 * entes enviaram, confirmar o recebimento (recibo) e recusar.
 *
 * Componentes: [{nome, mime, conteudo (bytes), hash (sha256)}]. Destino/remetente: {repositorio, estrutura, nome}.
 */
interface Barramento
{
    /** Diz se a conexão/credencial funciona (texto para a tela de configuração). */
    public function testar(): string;

    /** @return list<array{repositorio: string, estrutura: string, nome: string}> */
    public function estruturas(string $busca): array;

    /** @return array{nre: string, idt: string, enviado_em: string} */
    public function enviar(array $metadados, array $componentes, array $destino): array;

    /** @return array{status: 'enviado'|'concluido'|'recusado', recibo?: array, motivo?: string, justificativa?: string} */
    public function situacao(string $idt): array;

    /** @return list<array{idt: string, nre: string}> trâmites que outros entes enviaram para nós */
    public function pendencias(): array;

    /** @return array{metadados: array, componentes: array, remetente: array, nre: string} */
    public function receber(string $idt): array;

    /** Recibo de conclusão do recebimento (data/hora, NRE, IDT, hashes). */
    public function confirmarRecebimento(string $idt, array $hashes): array;

    public function recusar(string $idt, string $motivo, string $justificativa): void;
}
