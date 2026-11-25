<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\Processo;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Código e chave de quem pediu pelo Portal da Transparência, sem conta. Sem rastreio: o destinatário ficaria à vista no processo sigiloso. */
class ProtocoloTransparencia extends Mailable
{
    use Concerns\ComDescadastro;
    use Queueable, SerializesModels;

    public function __construct(public Processo $processo, public string $codigo, public string $chave, public ?string $url) {}

    public function build(): self
    {
        $this->comDescadastro();
        $orgao = Ug::withoutGlobalScopes()->find($this->processo->ug_id)?->nome ?? config('app.name');

        return $this->subject("[{$orgao}] Protocolo {$this->codigo}")
            ->view('emails.protocolo_transparencia')
            ->with(['orgao' => $orgao, 'p' => $this->processo]);
    }
}
