<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\PssInscricao;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Situação e resposta da inscrição no processo seletivo ao e-mail do candidato (TR 4.41.6). */
class RespostaInscricaoPss extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro, Concerns\ComRastreio;

    public function __construct(public PssInscricao $inscricao)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();
        $pss = $this->inscricao->pss;
        $orgao = Ug::withoutGlobalScopes()->find($pss?->ug_id)?->nome ?? config('app.name');
        $r = $this->aplicarRastreio(url('/'));

        return $this->subject("[{$orgao}] Inscrição {$this->inscricao->numero} — {$pss?->titulo}")
            ->view('emails.resposta_inscricao_pss')
            ->with(['orgao' => $orgao, 'i' => $this->inscricao, 'pss' => $pss, 'pixel' => $r['pixel'],
                'situacao' => \App\Models\Processo\PssInscricao::SITUACOES[$this->inscricao->situacao] ?? $this->inscricao->situacao]);
    }
}