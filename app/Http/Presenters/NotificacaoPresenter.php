<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Models\Notificacao;

/**
 * Forma da notificação para a tela de notificações e para a prévia do sino.
 *
 * `href` já vem pronto: o link que marca como lida e leva ao assunto, ou null quando a
 * notificação não tem assunto — o frontend não monta a URL.
 */
final class NotificacaoPresenter
{
    public static function resumo(Notificacao $n): array
    {
        return [
            'id'         => $n->id,
            'titulo'     => $n->titulo,
            'mensagem'   => $n->mensagem,
            'tipo'       => $n->tipo,
            'lida'       => (bool) $n->lida,
            'created_at' => $n->created_at,
            'href'       => self::destino($n) !== null ? "/notificacoes/{$n->id}/abrir" : null,
        ];
    }

    /** Tela do assunto a que a notificação se refere, ou null. */
    public static function destino(Notificacao $n): ?string
    {
        if (! $n->referencia_id) {
            return null;
        }

        return match ($n->referencia_tipo) {
            'documento' => "/documentos/{$n->referencia_id}",
            'processo'  => "/processos/{$n->referencia_id}",
            'memorando' => "/memorandos/{$n->referencia_id}",
            'oficio'    => "/oficios/{$n->referencia_id}",
            'circular'  => "/circulares/{$n->referencia_id}",
            default     => null,
        };
    }
}
