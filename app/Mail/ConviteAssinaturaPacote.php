<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Assinatura;
use App\Models\PacoteConvite;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Link único de um pacote de documentos para o signatário externo. */
class ConviteAssinaturaPacote extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro;

    public function __construct(public PacoteConvite $convite)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();   // link "deixar de receber" + List-Unsubscribe

        $assinaturas = Assinatura::with(['documento' => fn ($q) => $q->withoutGlobalScopes(['ug', 'sigilo'])])
            ->where('pacote_convite_id', $this->convite->id)
            ->where('status', 'pendente')
            ->get();
        $ugId = $assinaturas->first()?->documento?->ug_id;
        $orgao = Ug::withoutGlobalScopes()->find($ugId)?->nome ?? config('app.name');

        return $this->subject("[{$orgao}] Documentos para sua assinatura: {$this->convite->pacote->titulo}")
            ->view('emails.convite_pacote')
            ->with([
                'nome'       => $this->convite->nome,
                'orgao'      => $orgao,
                'titulo'     => $this->convite->pacote->titulo,
                'documentos' => $assinaturas->map(fn ($a) => $a->documento?->nome)->filter()->take(15)->values(),
                'total'      => $assinaturas->count(),
                'link'       => $this->convite->link(),
                'expira_em'  => $this->convite->expira_em?->format('d/m/Y'),
            ]);
    }
}
