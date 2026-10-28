<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Notificacao;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Notificação interna do GED também por e-mail (AvisarServidorPorEmail). */
class AvisoServidor extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Notificacao $notificacao, public ?string $link)
    {
    }

    public function build(): self
    {
        $ugId = $this->notificacao->usuario?->ug_id;
        $orgao = ($ugId ? Ug::withoutGlobalScopes()->find($ugId)?->nome : null) ?? config('app.name');

        return $this->subject("[GPE Docs] {$this->notificacao->titulo}")
            ->view('emails.aviso_servidor')
            ->with([
                'orgao' => $orgao,
                'nome'  => $this->notificacao->usuario?->name,
                'n'     => $this->notificacao,
                'link'  => $this->link,
            ]);
    }
}
