<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notificacao;
use App\Models\Processo\Arvore;
use App\Services\ArvoreDoProcesso;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gera a árvore do processo em segundo plano e avisa quem pediu (TR 4.38.30).
 *
 * Processo grande pode passar do retry_after da fila (90 s): a troca na_fila → gerando é
 * condicional, então uma segunda tentativa enquanto a primeira roda só sai. Árvore presa
 * em "gerando" há mais de 30 min (worker caiu) pode ser retomada.
 */
class GerarArvoreProcesso extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $timeout = 900;

    public int $tries = 3;

    public function __construct(public int $arvoreId)
    {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        $pegou = Arvore::whereKey($this->arvoreId)
            ->where(fn ($q) => $q->where('status', 'na_fila')
                ->orWhere(fn ($q) => $q->where('status', 'gerando')->where('updated_at', '<', now()->subMinutes(30))))
            ->update(['status' => 'gerando', 'updated_at' => now()]);
        if (! $pegou) {
            return;
        }
        $arvore = Arvore::with('processo:id,numero_protocolo')->findOrFail($this->arvoreId);

        try {
            app(ArvoreDoProcesso::class)->gerar($arvore);
            $this->avisar($arvore, 'Árvore do processo pronta', 'está pronta para baixar.');
        } catch (Throwable $e) {
            Log::warning('Árvore do processo não gerada', ['arvore' => $this->arvoreId, 'erro' => $e->getMessage()]);
            $arvore->update(['status' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 1000)]);
            $this->avisar($arvore, 'Árvore do processo não gerada', 'não pôde ser gerada. Veja o motivo no processo.');
        }
    }

    private function avisar(Arvore $arvore, string $titulo, string $complemento): void
    {
        Notificacao::create([
            'usuario_id'      => $arvore->solicitado_por,
            'tipo'            => 'exportacao',
            'titulo'          => $titulo,
            'mensagem'        => "A árvore do processo {$arvore->processo?->numero_protocolo} {$complemento}",
            'referencia_tipo' => 'processo',
            'referencia_id'   => $arvore->processo_id,
            'lida'            => false,
        ]);
    }
}
