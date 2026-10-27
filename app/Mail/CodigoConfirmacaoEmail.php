<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Código de 6 dígitos que confirma o e-mail do cadastro antes da assinatura por link. */
class CodigoConfirmacaoEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $nome, public string $codigo, public int $minutos)
    {
    }

    public function build(): self
    {
        return $this->subject("Código de confirmação: {$this->codigo}")
            ->view('emails.codigo_confirmacao')
            ->with(['nome' => $this->nome, 'codigo' => $this->codigo, 'minutos' => $this->minutos]);
    }
}
