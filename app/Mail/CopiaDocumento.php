<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\Processo;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Cópia de um documento do processo ao requerente ou a terceiro (TR 4.49.8). */
class CopiaDocumento extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro, Concerns\ComRastreio;

    public function __construct(public Processo $processo, public string $nome, public string $conteudo, public string $mime, public ?string $mensagem)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();
        $orgao = Ug::withoutGlobalScopes()->find($this->processo->ug_id)?->nome ?? config('app.name');
        $r = $this->aplicarRastreio(url('/'));

        return $this->subject("[{$orgao}] {$this->nome} — protocolo {$this->processo->numero_protocolo}")
            ->view('emails.copia_documento')
            ->with(['orgao' => $orgao, 'p' => $this->processo, 'nome' => $this->nome, 'mensagem' => $this->mensagem, 'pixel' => $r['pixel']])
            ->attachData($this->conteudo, $this->nome, ['mime' => $this->mime]);
    }
}