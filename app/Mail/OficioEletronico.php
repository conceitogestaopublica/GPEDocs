<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\Oficio;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Ofício eletrônico ao destinatário externo (OficioEmailService::enviar). */
class OficioEletronico extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Oficio $oficio, private string $pdf)
    {
    }

    public function build(): self
    {
        $orgao = Ug::withoutGlobalScopes()->find($this->oficio->ug_id)?->nome ?? config('app.name');
        $link = url("/oficios/rastrear/{$this->oficio->rastreio_token}");
        $arquivo = 'oficio-' . str_replace(['/', '\\'], '-', $this->oficio->numero) . '.pdf';

        return $this->subject("[{$orgao}] Ofício {$this->oficio->numero}: {$this->oficio->assunto}")
            ->view('emails.oficio_eletronico')
            ->with([
                'orgao'    => $orgao,
                'oficio'   => $this->oficio,
                'conteudo' => $this->oficio->conteudoFinal(),
                'link'     => $link,
                'pixel'    => "{$link}/aberto",
            ])
            ->attachData($this->pdf, $arquivo, ['mime' => 'application/pdf']);
    }
}
