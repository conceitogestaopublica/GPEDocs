<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail de redefinição de senha (o padrão do framework é em inglês). */
class RedefinirSenha extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Endereço do próprio ente (subdomínio da requisição), não o APP_URL genérico.
        $url = url('/reset-password/' . $this->token) . '?email=' . urlencode($notifiable->getEmailForPasswordReset());
        $minutos = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage())
            ->subject('Redefinição de senha')
            ->greeting('Olá, ' . $notifiable->name . '.')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Definir nova senha', $url)
            ->line("Este link vale por {$minutos} minutos e só pode ser usado uma vez.")
            ->line('Se você não pediu a redefinição, ignore esta mensagem: sua senha continua a mesma.')
            ->salutation('Atenciosamente');
    }
}
