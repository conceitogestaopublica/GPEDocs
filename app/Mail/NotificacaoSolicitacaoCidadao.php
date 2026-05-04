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
        /** @var array{0:string,1:string}|null $pdfAssinado [path_temporario_local, nome_amigavel] */
        public ?array $pdfAssinado = null,
    ) {}

    public function build(): self
    {
        $assunto = match ($this->evento) {
            'criada'           => "[{$this->ug->nome}] Solicitacao {$this->solicitacao->codigo} registrada",
            'status_alterado'  => "[{$this->ug->nome}] Atualizacao na solicitacao {$this->solicitacao->codigo}",
            'comentario'       => "[{$this->ug->nome}] Nova mensagem na solicitacao {$this->solicitacao->codigo}",
            default            => "[{$this->ug->nome}] Solicitacao {$this->solicitacao->codigo}",
        };

        $mail = $this->subject($assunto)
            ->view('emails.solicitacao_cidadao')
            ->with([
                'solicitacao'    => $this->solicitacao,
                'servico'        => $this->servico,
                'ug'             => $this->ug,
                'evento'         => $this->evento,
                'mensagemExtra'  => $this->mensagemExtra,
                'statusLabel'    => Solicitacao::STATUS[$this->solicitacao->status] ?? $this->solicitacao->status,
                'urlPortal'      => 'http://'.$this->ug->portal_slug.'.'.parse_url(config('app.url'), PHP_URL_HOST),
                'temPdfAssinado' => $this->pdfAssinado !== null,
            ]);

        if ($this->pdfAssinado) {
            [$path, $nome] = $this->pdfAssinado;
            $mail->attach($path, ['as' => $nome, 'mime' => 'application/pdf']);
        }

        return $mail;
    }
}
