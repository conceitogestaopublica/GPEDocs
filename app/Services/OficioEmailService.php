<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OficioEletronico;
use App\Models\Notificacao;
use App\Models\Processo\Oficio;
use App\Models\Ug;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Envia o ofício eletrônico por e-mail ao destinatário (conteúdo + PDF + link de rastreio) e registra
 * o resultado no próprio ofício. Falha não desfaz o ofício: fica registrada com o motivo, o remetente
 * é avisado e pode reenviar.
 *
 * "Enviado" quer dizer aceito pelo servidor de e-mail; a confirmação de que chegou à caixa do
 * destinatário vem da abertura (imagem de rastreio) ou do acesso ao link.
 */
class OficioEmailService
{
    public function enviar(Oficio $oficio): bool
    {
        if (! $oficio->destinatario_email) {
            return $this->registrarFalha($oficio, 'O ofício não tem e-mail do destinatário.');
        }

        $oficio->increment('email_tentativas');

        try {
            Mail::to($oficio->destinatario_email, $oficio->destinatario_nome)
                ->send(new OficioEletronico($oficio, $this->pdf($oficio)));
        } catch (\Throwable $e) {
            Log::warning('Ofício não enviado por e-mail', ['oficio_id' => $oficio->id, 'erro' => $e->getMessage()]);

            return $this->registrarFalha($oficio, $this->motivo($e));
        }

        $agora = now();
        $oficio->forceFill([
            'email_status'       => 'enviado',
            'email_enviado_em'   => $agora,
            'email_falha_motivo' => null,
            'entregue_em'        => $oficio->entregue_em ?? $agora,
            'status'             => in_array($oficio->status, ['enviado', null], true) ? 'entregue' : $oficio->status,
        ])->save();

        return true;
    }

    /** PDF oficial do ofício, o mesmo do botão "PDF" da tela. */
    public function pdf(Oficio $oficio): string
    {
        return Pdf::loadView('pdf.oficio', [
            'oficio'    => $oficio->loadMissing('remetente', 'respostas.usuario'),
            'qrCodeUrl' => url("/oficios/verificar/{$oficio->qr_code_token}"),
            'ug'        => Ug::withoutGlobalScopes()->find($oficio->ug_id),
        ])->setPaper('A4', 'portrait')->output();
    }

    /** Aberto: a imagem de rastreio do e-mail foi carregada. Só a primeira vez conta. */
    public function registrarAbertura(Oficio $oficio): void
    {
        if ($oficio->email_aberto_em) {
            return;
        }
        $oficio->forceFill([
            'email_aberto_em' => now(),
            'entregue_em'     => $oficio->entregue_em ?? now(),
        ])->save();
    }

    /** Link do e-mail acessado: é a leitura do ofício. Avisa o remetente na primeira vez. */
    public function registrarLeitura(Oficio $oficio): void
    {
        if ($oficio->lido_em) {
            return;
        }
        $oficio->forceFill([
            'lido_em'     => now(),
            'entregue_em' => $oficio->entregue_em ?? now(),
            'status'      => in_array($oficio->status, ['enviado', 'entregue'], true) ? 'lido' : $oficio->status,
        ])->save();

        Notificacao::create([
            'usuario_id'      => $oficio->remetente_id,
            'tipo'            => 'oficio_lido',
            'titulo'          => 'Ofício lido pelo destinatário',
            'mensagem'        => "O ofício {$oficio->numero} foi aberto por {$oficio->destinatario_nome}.",
            'referencia_tipo' => 'oficio',
            'referencia_id'   => $oficio->id,
            'lida'            => false,
        ]);
    }

    private function registrarFalha(Oficio $oficio, string $motivo): bool
    {
        $oficio->forceFill(['email_status' => 'falhou', 'email_falha_motivo' => $motivo])->save();

        Notificacao::create([
            'usuario_id'      => $oficio->remetente_id,
            'tipo'            => 'oficio_email_falhou',
            'titulo'          => 'Ofício não enviado por e-mail',
            'mensagem'        => "O e-mail do ofício {$oficio->numero} para {$oficio->destinatario_nome} não foi enviado: {$motivo}",
            'referencia_tipo' => 'oficio',
            'referencia_id'   => $oficio->id,
            'lida'            => false,
        ]);

        return false;
    }

    /** Motivo legível para o remetente, sem credencial nem caminho interno. */
    private function motivo(\Throwable $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            (bool) preg_match('/connection (could not be established|refused|timed out)|getaddrinfo|unable to connect/i', $msg)
                => 'o servidor de e-mail não respondeu. Tente reenviar mais tarde.',
            (bool) preg_match('/(550|553|554|5\.1\.1|user unknown|mailbox unavailable|recipient)/i', $msg)
                => 'o endereço do destinatário foi recusado pelo servidor de e-mail. Confira o e-mail.',
            (bool) preg_match('/(535|auth|credentials|password)/i', $msg)
                => 'o servidor de e-mail recusou a autenticação do sistema. Avise o suporte.',
            default => Str::limit(preg_replace('/\s+/', ' ', strip_tags($msg)), 200),
        };
    }
}
