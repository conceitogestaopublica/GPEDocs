<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Models\Notificacao;
use App\Support\Notificacoes;

/**
 * Forma da notificação para a tela de notificações e para a prévia do sino — um formato só.
 *
 * `href` já vem pronto: o link que marca como lida e leva ao assunto (Notificacao::destino),
 * ou null quando a notificação não tem assunto — o frontend não monta a URL.
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
            'categoria'  => $n->categoria,
            'icone'      => Notificacoes::CATEGORIAS[$n->categoria][1] ?? 'fa-bell',
            'urgente'    => (bool) $n->urgente,
            'setor'      => $n->alcance === 'setor',
            'lida'       => (bool) $n->lida,
            'created_at' => $n->created_at,
            'href'       => $n->destino() !== null ? "/notificacoes/{$n->id}/abrir" : null,
        ];
    }
}
