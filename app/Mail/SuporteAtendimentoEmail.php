<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail do atendimento de suporte (TR 4.26.1, 4.26.8, 4.26.9): na abertura, o número para retomar;
 * na resolução, o resumo (atendentes e horários) com os botões de avaliar ou reabrir. O Reply-To leva a
 * resposta de volta para a conversa.
 */
class SuporteAtendimentoEmail extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComRastreio;

    public function __construct(public object $atendimento, public string $tipo, public array $resumo, public ?string $responderPara)
    {
    }

    public function build(): self
    {
        $a = $this->atendimento;
        $link = url("/suporte/atendimentos/{$a->id}");
        $r = $this->aplicarRastreio($link);
        $m = $this->subject(($this->tipo === 'resolvido' ? "Atendimento {$a->numero} resolvido — avalie" : "Atendimento {$a->numero} aberto") . ": {$a->titulo}")
            ->view('emails.suporte_atendimento')
            ->with(['a' => $a, 'link' => $r['link'], 'pixel' => $r['pixel'], 'avaliar' => url("/suporte/avaliar/{$a->token}"),
                'reabrir' => url("/suporte/avaliar/{$a->token}?reabrir=1"),
                'mensagens' => \Illuminate\Support\Facades\DB::table('sup_mensagens as m')->leftJoin('users as u', 'u.id', '=', 'm.autor_id')
                    ->where('m.atendimento_id', $a->id)->where('m.tipo', '<>', 'nota')->orderByDesc('m.id')->limit(6)->get(['m.tipo', 'm.conteudo', 'm.created_at', 'u.name'])->reverse()]);

        return $this->responderPara ? $m->replyTo($this->responderPara) : $m;
    }
}
