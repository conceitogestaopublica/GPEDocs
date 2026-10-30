<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Preferências do servidor (users.preferencias):
 *   editor  → avancado (formatação) | basico (texto simples)
 *   anexos  → visualizar (abre no navegador) | baixar
 *   canais  → abrangência do e-mail e do SMS (TR 4.11.4–5): tudo | direcionado | urgentes | nada
 *   email_mencao / email_assinatura → e-mail em menção e em pedido de assinatura (TR 4.11.6)
 */
final class PreferenciasUsuario
{
    /**
     * Padrão de quem nunca mexeu: e-mail do que é direcionado a ele (pedidos de assinatura e
     * menções incluídos), SMS desligado.
     */
    public const PADRAO = [
        'editor'           => 'avancado',
        'anexos'           => 'visualizar',
        'canais'           => ['email' => 'direcionado', 'sms' => 'nada'],
        'email_mencao'     => true,
        'email_assinatura' => true,
        // E-mails que enviei para fora: de quais módulos quero aviso em cada evento (TR 4.11.7–4.11.10)
        'rastreio'         => ['falha' => ['oficio', 'mencao', 'assinatura', 'cidadao'], 'entrega' => [], 'abertura' => ['oficio'], 'clique' => ['oficio', 'assinatura']],
    ];

    /** @return array{editor: string, anexos: string, canais: array{email: string, sms: string}, email_mencao: bool, email_assinatura: bool} */
    public static function de(User $user): array
    {
        $salvas = is_array($user->preferencias) ? $user->preferencias : [];
        // Quem escolheu os grupos antigos (`avisos`) sem marcar nenhum não queria e-mail
        if (! isset($salvas['canais']) && array_key_exists('avisos', $salvas)) {
            $salvas['canais'] = ['email' => $salvas['avisos'] ? 'direcionado' : 'nada', 'sms' => 'nada'];
        }
        $p = array_replace(self::PADRAO, array_intersect_key($salvas, self::PADRAO));
        $p['canais'] = array_replace(self::PADRAO['canais'], array_intersect_key((array) $p['canais'], self::PADRAO['canais']));
        $p['rastreio'] = array_replace(self::PADRAO['rastreio'], array_intersect_key((array) $p['rastreio'], self::PADRAO['rastreio']));

        return $p;
    }
}