import { Link } from '@inertiajs/react';

/**
 * Uma notificação: ponto de não lida, título, mensagem e quando. Usado pelo sino da topbar
 * (prévia) e pela tela de notificações.
 *
 * Com `n.href` (vem pronto do backend: marca como lida e leva ao assunto) o item inteiro é
 * o link, com a seta. Sem assunto, vira link para `semAssunto` — ou fica só texto.
 *
 * Props: n, quando (texto da data), resumida (mensagem em até 2 linhas), semAssunto,
 *        onClick, className (do elemento clicável).
 */
export default function ItemNotificacao({ n, quando, resumida = false, semAssunto = null, onClick, className = '' }) {
    const href = n.href ?? semAssunto;
    const conteudo = (
        <>
            <span className={`mt-1.5 w-2 h-2 rounded-full shrink-0 ${n.lida ? 'bg-gray-200' : 'bg-primary-500'}`} />
            <span className="flex-1 min-w-0">
                <span className={`block text-sm ${resumida ? 'truncate' : ''} ${n.lida ? 'text-gray-700' : 'text-gray-900 font-semibold'}`}>{n.titulo}</span>
                {n.mensagem && <span className={`block text-xs text-gray-500 mt-0.5 ${resumida ? 'line-clamp-2' : ''}`}>{n.mensagem}</span>}
                <span className="block text-[11px] text-gray-400 mt-1">{quando}</span>
            </span>
            {n.href && <i className="fas fa-chevron-right text-[10px] text-gray-300 mt-1.5 shrink-0" />}
        </>
    );
    const classes = `flex items-start gap-3 ${className}`;

    return href
        ? <Link href={href} onClick={onClick} className={classes}>{conteudo}</Link>
        : <div className={classes}>{conteudo}</div>;
}
