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
    use Concerns\ComDescadastro;

    public function __construct(public Oficio $oficio, private string $pdf)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();   // link "deixar de receber" + List-Unsubscribe

        $orgao = Ug::withoutGlobalScopes()->find($this->oficio->ug_id)?->nome ?? config('app.name');
        $link = url("/oficios/rastrear/{$this->oficio->rastreio_token}");
        $arquivo = 'oficio-' . str_replace(['/', '\\'], '-', $this->oficio->numero) . '.pdf';

        // Responder este e-mail registra a resposta (e os anexos) no ofício (TR 4.8.8–4.8.9)
        if ($resp = \App\Support\RespostaPorEmail::endereco('oficio', $this->oficio->id, $this->oficio->destinatario_email)) {
            $this->replyTo($resp);
        }

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
