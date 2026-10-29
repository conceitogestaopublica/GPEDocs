<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notificacao;
use App\Models\User;

/**
 * Notificação multicanal (TR 4.11): categorias da página de notificações e a decisão de
 * mandar também por e-mail ou SMS conforme a abrangência que o servidor escolheu para cada
 * canal — tudo do setor, só o direcionado a ele, só urgentes, nada.
 */
final class Notificacoes
{
    /** categoria => [rótulo, ícone, tipos de notificação] (TR 4.11.3). */
    public const CATEGORIAS = [
        'assinaturas_pendentes'   => ['Assinaturas pendentes', 'fa-file-signature', ['assinatura_pendente']],
        'assinaturas_solicitadas' => ['Assinaturas que pedi', 'fa-signature', ['assinatura_realizada', 'assinatura_recusada']],
        'mencoes'                 => ['Menções', 'fa-at', ['mencao']],
        'processos'               => ['Processos e prazos', 'fa-folder-open', ['processo', 'fluxo', 'prazo']],
        'memorandos'              => ['Memorandos', 'fa-envelope', ['memorando_recebido', 'memorando_resposta', 'memorando_tramitado']],
        'circulares'              => ['Circulares', 'fa-bullhorn', ['circular_recebida', 'circular_resposta']],
        'emails'                  => ['E-mails rastreados', 'fa-envelope-circle-check', ['oficio_enviado', 'oficio_lido', 'oficio_email_falhou', 'email_entregue', 'email_aberto', 'email_clicado', 'email_falhou']],
        'cadastro'                => ['Avisos de cadastro', 'fa-id-card', ['cadastro']],
        'troca_setor'             => ['Troca de setor', 'fa-right-left', ['troca_setor']],
        'setor_secundario'        => ['Setor secundário', 'fa-sitemap', ['setor_secundario']],
        'exportacao'              => ['Exportações', 'fa-file-export', ['exportacao']],
        'contrassenha'            => ['Contrassenha', 'fa-key', ['contrassenha_usada']],
    ];

    /** Abrangência por canal (TR 4.11.4–5). */
    public const ABRANGENCIAS = [
        'tudo'        => 'Tudo do meu setor',
        'direcionado' => 'Só o que é direcionado a mim',
        'urgentes'    => 'Só urgentes',
        'nada'        => 'Nada',
    ];

    public static function categoriaDe(string $tipo): string
    {
        foreach (self::CATEGORIAS as $cat => [, , $tipos]) {
            if (in_array($tipo, $tipos, true)) {
                return $cat;
            }
        }

        return 'outros';
    }

    /** A notificação vai também por este canal (email|sms) para o servidor? */
    public static function quer(User $user, Notificacao $n, string $canal): bool
    {
        $p = PreferenciasUsuario::de($user);
        // Menção e pedido de assinatura têm chave própria no e-mail (TR 4.11.6)
        if ($canal === 'email' && $n->tipo === 'mencao') {
            return (bool) $p['email_mencao'];
        }
        if ($canal === 'email' && $n->tipo === 'assinatura_pendente') {
            return (bool) $p['email_assinatura'];
        }

        return match ($p['canais'][$canal] ?? 'nada') {
            'tudo'        => true,
            'direcionado' => $n->alcance !== 'setor',
            'urgentes'    => (bool) $n->urgente,
            default       => false,
        };
    }
}
