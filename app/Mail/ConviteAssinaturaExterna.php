<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Assinatura;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Link de assinatura para o signatário externo (ConviteAssinaturaService::enviar). */
class ConviteAssinaturaExterna extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro, Concerns\ComRastreio;

    public function __construct(public Assinatura $assinatura)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();   // link "deixar de receber" + List-Unsubscribe

        $documento = $this->assinatura->documento;
        $orgao = Ug::withoutGlobalScopes()->find($documento?->ug_id)?->nome ?? config('app.name');
        $r = $this->aplicarRastreio($this->assinatura->linkConvite());   // TR 4.11.12

        return $this->subject("[{$orgao}] Documento para sua assinatura: {$documento?->nome}")
            ->view('emails.convite_assinatura')
            ->with([
                'nome'      => $this->assinatura->nome_signatario,
                'orgao'     => $orgao,
                'documento' => $documento?->nome,
                'mensagem'  => $this->assinatura->solicitacao?->mensagem,
                'link'      => $r['link'],
                'pixel'     => $r['pixel'],
                'expira_em' => $this->assinatura->convite_expira_em?->format('d/m/Y'),
            ]);
    }
}
