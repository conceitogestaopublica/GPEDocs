<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Contato externo mencionado (@) num documento do editor: link temporário para o PDF (TR 4.16.17). */
class MencaoExterna extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nome,
        public string $autor,
        public string $documento,
        public string $assunto,
        public string $link,
        public ?int $ugId,
    ) {
    }

    public function build(): self
    {
        $orgao = ($this->ugId ? Ug::withoutGlobalScopes()->find($this->ugId)?->nome : null) ?? config('app.name');

        return $this->subject("[{$orgao}] Você foi citado no {$this->documento}")
            ->view('emails.mencao_externa')
            ->with(['orgao' => $orgao]);
    }
}
