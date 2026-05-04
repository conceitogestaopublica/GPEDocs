<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacaoSolicitacaoCidadao extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Solicitacao $solicitacao,
        public Servico $servico,
        public Ug $ug,
        public string $evento, // criada, status_alterado, comentario
        public ?string $mensagemExtra = null,
    ) {}

    public function build(): self
    {
        $assunto = match ($this->evento) {
            'criada'           => "[{$this->ug->nome}] Solicitacao {$this->solicitacao->codigo} registrada",
            'status_alterado'  => "[{$this->ug->nome}] Atualizacao na solicitacao {$this->solicitacao->codigo}",
            'comentario'       => "[{$this->ug->nome}] Nova mensagem na solicitacao {$this->solicitacao->codigo}",
            default            => "[{$this->ug->nome}] Solicitacao {$this->solicitacao->codigo}",
        };

        return $this->subject($assunto)
            ->view('emails.solicitacao_cidadao')
            ->with([
                'solicitacao'   => $this->solicitacao,
                'servico'       => $this->servico,
                'ug'            => $this->ug,
                'evento'        => $this->evento,
                'mensagemExtra' => $this->mensagemExtra,
                'statusLabel'   => Solicitacao::STATUS[$this->solicitacao->status] ?? $this->solicitacao->status,
                'urlPortal'     => 'http://'.$this->ug->portal_slug.'.'.parse_url(config('app.url'), PHP_URL_HOST),
            ]);
    }
}
