import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import ItemNotificacao from './ItemNotificacao';
import { haQuantoTempo } from '../utils/datas';
import useClickFora from '../Hooks/useClickFora';

const ATRASO_FECHAR_MS = 180;

/**
 * Sino da topbar. Passar o mouse já abre a PRÉVIA das notificações recentes (sem clicar);
 * o clique no sino também abre (celular não tem hover). Clicar numa notificação vai direto
 * ao assunto dela por /notificacoes/{id}/abrir, que marca como lida e redireciona.
 */
export default function NotificacoesDropdown({ count }) {
    const [open, setOpen] = useState(false);
    // Os avisos vêm do endpoint só quando o sino abre (null = carregando) — não pesam em
    // toda navegação.
    const [recentes, setRecentes] = useState(null);
    useEffect(() => {
        if (!open) return;
        setRecentes(null);
        axios.get('/notificacoes/recentes').then((r) => setRecentes(r.data)).catch(() => setRecentes([]));
    }, [open]);
    const ref = useClickFora(setOpen);
    const timer = useRef(null);

    // Atraso curto ao sair: dá tempo de levar o mouse do sino até o painel.
    const abrir = () => { clearTimeout(timer.current); setOpen(true); };
    const fechar = () => { clearTimeout(timer.current); timer.current = setTimeout(() => setOpen(false), ATRASO_FECHAR_MS); };
    useEffect(() => () => clearTimeout(timer.current), []);

    // Hover só com mouse de verdade: o toque emula mouseenter antes do click, e o click
    // (que alterna) fecharia na hora o que o hover acabou de abrir.
    const doMouse = (e) => e.pointerType === 'mouse';
    // Com mouse, o painel já abriu no hover — o clique só garante aberto. No toque e no
    // teclado (click sem ponteiro: detail 0), alterna. O tipo vem do pointerdown, porque
    // nem todo navegador informa pointerType no próprio click.
    const ponteiro = useRef(null);
    const aoClicar = (e) => (e.detail > 0 && ponteiro.current === 'mouse' ? abrir() : setOpen((o) => !o));

    return (
        <div className="relative" ref={ref}
            onPointerEnter={(e) => doMouse(e) && abrir()}
            onPointerLeave={(e) => doMouse(e) && fechar()}>
            <button onPointerDown={(e) => { ponteiro.current = e.pointerType; }} onClick={aoClicar} title="Notificações" aria-expanded={open}
                className="relative w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors">
                <i className="fas fa-bell text-sm" />
                {count > 0 && (
                    <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-96 max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 animate-fadeIn" data-topbar-dropdown>
                    <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <p className="text-sm font-semibold text-gray-800">Notificações</p>
                        {count > 0 && <span className="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold">{count} {count === 1 ? 'nova' : 'novas'}</span>}
                    </div>
                    <div className="max-h-96 overflow-y-auto">
                        {recentes === null ? (
                            <p className="px-4 py-4 text-xs text-gray-400">Carregando…</p>
                        ) : recentes.length === 0 ? (
                            <div className="px-4 py-6 text-center text-gray-400">
                                <i className="fas fa-bell-slash text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma notificação</p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-50">
                                {recentes.map((n) => (
                                    <li key={n.id}>
                                        <ItemNotificacao n={n} quando={haQuantoTempo(n.created_at)} resumida
                                            semAssunto="/notificacoes" onClick={() => setOpen(false)}
                                            className={`px-4 py-3 transition-colors hover:bg-gray-50 ${n.lida ? '' : 'bg-primary-50/40'}`} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <Link href={count > 0 ? '/notificacoes?nao_lidas=1' : '/notificacoes'} onClick={() => setOpen(false)}
                        className="block px-4 py-3 border-t border-gray-100 text-sm text-primary-600 hover:bg-primary-50 text-center font-medium">
                        {count > 0 ? 'Ver notificações novas' : 'Ver todas as notificações'}
                    </Link>
                </div>
            )}
        </div>
    );
}
