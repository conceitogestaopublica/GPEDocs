<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Processo\Processo;
use App\Models\Ug;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Confirmação do protocolo ao requerente, com cópia dos anexos (TR 4.48.33). Anexos acima do
 * limite do e-mail vão só listados.
 */
class ConfirmacaoProtocolo extends Mailable
{
    use Queueable, SerializesModels;
    use Concerns\ComDescadastro, Concerns\ComRastreio;

    private const LIMITE = 15 * 1024 * 1024;

    public function __construct(public Processo $processo)
    {
    }

    public function build(): self
    {
        $this->comDescadastro();
        $orgao = Ug::withoutGlobalScopes()->find($this->processo->ug_id)?->nome ?? config('app.name');
        $r = $this->aplicarRastreio(url('/'));
        $total = 0;
        $anexados = [];
        $listados = [];
        foreach ($this->processo->anexos()->orderBy('id')->get() as $a) {
            if ($total + $a->tamanho <= self::LIMITE && Storage::disk('documentos')->exists($a->arquivo_path)) {
                $this->attachData(Storage::disk('documentos')->get($a->arquivo_path), $a->nome, ['mime' => $a->mime_type ?: 'application/octet-stream']);
                $total += $a->tamanho;
                $anexados[] = $a->nome;
            } else {
                $listados[] = $a->nome;
            }
        }

        return $this->subject("[{$orgao}] Protocolo {$this->processo->numero_protocolo} registrado")
            ->view('emails.confirmacao_protocolo')
            ->with(['orgao' => $orgao, 'p' => $this->processo, 'anexados' => $anexados, 'listados' => $listados, 'pixel' => $r['pixel']]);
    }
}