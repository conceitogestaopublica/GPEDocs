<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\Processo;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Documento recusado na análise, com a cópia anotada, ao requerente (TR 4.48.38). */
class AnexoRecusado extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro, Concerns\ComRastreio;

    public function __construct(public Processo $processo, public string $documento, public string $motivo, public string $pdf, public string $nomeArquivo)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();
        $orgao = Ug::withoutGlobalScopes()->find($this->processo->ug_id)?->nome ?? config('app.name');
        $r = $this->aplicarRastreio(url('/'));

        return $this->subject("[{$orgao}] Documento recusado no protocolo {$this->processo->numero_protocolo}")
            ->view('emails.anexo_recusado')
            ->with(['orgao' => $orgao, 'p' => $this->processo, 'documento' => $this->documento, 'motivo' => $this->motivo, 'pixel' => $r['pixel']])
            ->attachData($this->pdf, $this->nomeArquivo, ['mime' => 'application/pdf']);
    }
}