<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\Processo;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Decisão final da manifestação ou do pedido de informação ao e-mail do manifestante (TR 4.33.7, 4.33.23). */
class DecisaoManifestacao extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro, Concerns\ComRastreio;

    public function __construct(public Processo $processo, public string $decisao)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();
        $orgao = Ug::withoutGlobalScopes()->find($this->processo->ug_id)?->nome ?? config('app.name');
        $r = $this->aplicarRastreio(url('/'));

        return $this->subject("[{$orgao}] Resposta ao protocolo {$this->processo->numero_protocolo}")
            ->view('emails.decisao_manifestacao')
            ->with(['orgao' => $orgao, 'p' => $this->processo, 'decisao' => $this->decisao, 'pixel' => $r['pixel']]);
    }
}
