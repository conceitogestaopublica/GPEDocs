<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Preferências do servidor (users.preferencias):
 *   editor  → avancado (formatação) | basico (texto simples)
 *   anexos  → visualizar (abre no navegador) | baixar
 *   avisos  → grupos de notificação que também chegam por e-mail
 */
final class PreferenciasUsuario
{
    /** Grupos de aviso por e-mail → tipos de Notificacao que cada um cobre. */
    public const AVISOS = [
        'assinatura' => ['rotulo' => 'Assinaturas (pedidos, assinadas e recusadas)', 'tipos' => ['assinatura_pendente', 'assinatura_realizada', 'assinatura_recusada']],
        'processo'   => ['rotulo' => 'Processos e fluxos tramitados para mim', 'tipos' => ['processo', 'fluxo']],
        'memorando'  => ['rotulo' => 'Memorandos (recebidos, respostas e tramitação)', 'tipos' => ['memorando_recebido', 'memorando_resposta', 'memorando_tramitado']],
        'circular'   => ['rotulo' => 'Circulares recebidas', 'tipos' => ['circular_recebida']],
        'oficio'     => ['rotulo' => 'Ofícios (enviados, lidos e falhas de e-mail)', 'tipos' => ['oficio_enviado', 'oficio_lido', 'oficio_email_falhou']],
    ];

    /** Padrão de quem nunca mexeu: só o que pede ação dele. */
    public const PADRAO = [
        'editor' => 'avancado',
        'anexos' => 'visualizar',
        'avisos' => ['assinatura', 'processo'],
    ];

    /** @return array{editor: string, anexos: string, avisos: array<int, string>} */
    public static function de(User $user): array
    {
        $salvas = is_array($user->preferencias) ? $user->preferencias : [];

        return array_replace(self::PADRAO, array_intersect_key($salvas, self::PADRAO));
    }

    /** O tipo de notificação vai por e-mail para este servidor? */
    public static function querEmail(User $user, string $tipoNotificacao): bool
    {
        foreach (self::de($user)['avisos'] as $grupo) {
            if (in_array($tipoNotificacao, self::AVISOS[$grupo]['tipos'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
