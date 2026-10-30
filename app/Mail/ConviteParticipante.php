<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\Processo;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Convite para o participante (arquiteto, engenheiro…) confirmar os próprios dados no processo (TR 4.48.31). */
class ConviteParticipante extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro, Concerns\ComRastreio;

    public function __construct(public Processo $processo, public string $nome, public string $papel, public string $link)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();
        $orgao = Ug::withoutGlobalScopes()->find($this->processo->ug_id)?->nome ?? config('app.name');
        $r = $this->aplicarRastreio($this->link);

        return $this->subject("[{$orgao}] Confirme sua participação no protocolo {$this->processo->numero_protocolo}")
            ->view('emails.convite_participante')
            ->with(['orgao' => $orgao, 'p' => $this->processo, 'nome' => $this->nome, 'papel' => $this->papel, 'link' => $r['link'] ?? $this->link, 'pixel' => $r['pixel']]);
    }
}