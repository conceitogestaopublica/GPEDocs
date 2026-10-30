<?php

declare(strict_types=1);

namespace App\Integracoes\Tramita;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Barramento real (Processo Eletrônico Nacional / Tramita GOV.BR), autenticado com o certificado do ente
 * (mTLS). A conexão (URL + certificado) já é testável; as operações são implementadas na homologação, com a
 * especificação oficial entregue junto com o termo de adesão — até lá cada uma avisa em vez de inventar
 * o protocolo. O resto do GED (telas, NRE/IDT, recibo, recusa) já trabalha sobre a interface Barramento.
 */
class Pen implements Barramento
{
    public function __construct(private object $config)
    {
    }

    public function testar(): string
    {
        if (! $this->config->url || ! $this->config->certificado_path) {
            throw new FalhaTramita('Informe a URL do barramento e o certificado do ente recebidos na adesão.');
        }
        $r = $this->http()->get(rtrim($this->config->url, '/'));

        return "Conexão com o barramento: HTTP {$r->status()} (certificado aceito pelo servidor TLS). As operações são ativadas na homologação.";
    }

    public function estruturas(string $busca): array
    {
        $this->pendente('consulta ao repositório de estruturas');
    }

    public function enviar(array $metadados, array $componentes, array $destino): array
    {
        $this->pendente('envio do trâmite');
    }

    public function situacao(string $idt): array
    {
        $this->pendente('consulta do recibo de conclusão');
    }

    public function pendencias(): array
    {
        $this->pendente('listagem de pendências');
    }

    public function receber(string $idt): array
    {
        $this->pendente('download dos metadados e componentes');
    }

    public function confirmarRecebimento(string $idt, array $hashes): array
    {
        $this->pendente('envio do recibo de recebimento');
    }

    public function recusar(string $idt, string $motivo, string $justificativa): void
    {
        $this->pendente('recusa do trâmite');
    }

    private function http()
    {
        $cert = Storage::disk('local')->path($this->config->certificado_path);
        $senha = $this->config->certificado_senha ? Crypt::decryptString($this->config->certificado_senha) : null;

        return Http::timeout(20)->withOptions(['cert' => $senha ? [$cert, $senha] : $cert]);
    }

    private function pendente(string $operacao): never
    {
        throw new FalhaTramita("Barramento real: a {$operacao} é ativada na homologação do Tramita GOV.BR (falta a especificação oficial da adesão). Use o simulador para testar.");
    }
}
