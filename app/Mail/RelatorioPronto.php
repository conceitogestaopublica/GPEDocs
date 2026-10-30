<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Relatório agendado entregue por e-mail (TR 4.25.15): o CSV vai anexo quando cabe; senão, só o link. */
class RelatorioPronto extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComRastreio;

    public function __construct(public string $nome, public string $titulo, public string $filtros, public int $linhas, public string $link,
        public ?string $conteudo, public string $arquivo, public string $orgao)
    {
    }

    public function build(): self
    {
        $r = $this->aplicarRastreio($this->link);
        $m = $this->subject("[{$this->orgao}] Relatório agendado: {$this->nome}")->view('emails.relatorio_pronto')
            ->with(['link' => $r['link'] ?? $this->link, 'pixel' => $r['pixel']]);

        return $this->conteudo !== null ? $m->attachData($this->conteudo, $this->arquivo, ['mime' => 'text/csv']) : $m;
    }
}
