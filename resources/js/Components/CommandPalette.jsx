import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';

/**
 * CommandPalette — busca GLOBAL de rotinas (Ctrl/⌘+K) + FAVORITOS por usuário. Padrão gpe2.
 *
 * Renderiza o gatilho na topbar + um modal que indexa TODAS as rotinas dos módulos
 * (prop `rotinas`, montada pelo AdminLayout a partir dos mesmos menus da sidebar) e navega
 * direto para a escolhida. Filtro em memória, case-insensitive e SEM acento. Cada linha tem
 * uma ESTRELA para favoritar; com a busca vazia, os favoritos aparecem no topo.
 *
 * GPEDocs: com texto digitado, a última linha busca o termo nos DOCUMENTOS (/busca) — a
 * busca de conteúdo que antes ficava no campo da topbar continua a um Enter de distância.
 *
 * Props: rotinas = Array<{ href, label, icon, modulo, grupo }>
 */
export default function CommandPalette({ rotinas = [] }) {
    const favoritos = usePage().props.favoritos || [];
    const favSet = useMemo(() => new Set(favoritos.map((f) => f.href)), [favoritos]);

    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [ativo, setAtivo] = useState(0);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    // Ctrl/⌘+K abre/fecha em qualquer tela; Esc fecha.
    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                setOpen((v) => !v);
            } else if (e.key === 'Escape') {
                setOpen(false);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    // Ao abrir: foca a busca e zera o destaque.
    useEffect(() => {
        if (!open) return;
        setAtivo(0);
        setTimeout(() => inputRef.current?.focus(), 20);
    }, [open]);

    // Resultado: sem busca → favoritos no topo + demais; com busca → filtro achatado.
    const resultado = useMemo(() => {
        const termos = norm(query).split(/\s+/).filter(Boolean);
        if (termos.length === 0) {
            const favHrefs = new Set(favoritos.map((f) => f.href));
            const favs = favoritos.map((f) => ({ href: f.href, label: f.label, icon: f.icon, modulo: '', grupo: 'Favoritos' }));
            const resto = rotinas.filter((r) => !favHrefs.has(r.href)).slice(0, 60);
            return { favs, resto };
        }
        const filtr = rotinas
            .filter((r) => {
                const txt = norm(`${r.label} ${r.modulo} ${r.grupo || ''} ${r.keywords || ''}`);
                return termos.every((t) => txt.includes(t));
            })
            .slice(0, 60);
        return { favs: [], resto: filtr };
    }, [rotinas, query, favoritos]);

    // Linha "buscar nos documentos": só com texto; sempre a última (Enter nela quando não há rotina).
    const termo = query.trim();
    const buscaDocs = termo ? { href: `/busca?q=${encodeURIComponent(termo)}`, label: termo, buscaDocs: true } : null;
    const flat = useMemo(
        () => [...resultado.favs, ...resultado.resto, ...(buscaDocs ? [buscaDocs] : [])],
        [resultado, buscaDocs?.href],
    );

    // Mantém o item destacado visível.
    useEffect(() => {
        if (!open || !listRef.current) return;
        listRef.current.querySelector(`[data-idx="${ativo}"]`)?.scrollIntoView({ block: 'nearest' });
    }, [ativo, open, flat.length]);

    const ir = (r) => {
        if (!r) return;
        setOpen(false);
        setQuery('');
        router.visit(r.href);
    };

    // Favoritar/desfavoritar sem sair do modal (só recarrega a prop `favoritos`).
    const toggleFav = (e, r) => {
        e.stopPropagation();
        e.preventDefault();
        const opts = { preserveState: true, preserveScroll: true };
        if (favSet.has(r.href)) {
            router.post('/rotinas/favoritos/remover', { href: r.href }, opts);
        } else {
            router.post('/rotinas/favoritos', { href: r.href, label: r.label, icon: r.icon || 'fas fa-circle' }, opts);
        }
    };

    const onKey = (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setAtivo((i) => Math.min(flat.length - 1, i + 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setAtivo((i) => Math.max(0, i - 1)); }
        else if (e.key === 'Enter') { e.preventDefault(); ir(flat[ativo]); }
    };

    // Renderiza uma linha; `gi` = índice global (destaque + navegação por teclado).
    const linha = (r, gi) => {
        const isFav = favSet.has(r.href);
        return (
            <button key={`${r.href}-${gi}`} type="button" data-idx={gi}
                onClick={() => ir(r)}
                onMouseEnter={() => setAtivo(gi)}
                className={`w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors ${gi === ativo ? 'bg-blue-50' : ''}`}>
                <span className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${gi === ativo ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400'}`}>
                    <i className={`${r.icon || 'fas fa-circle'} text-xs`} />
                </span>
                <span className="flex-1 min-w-0">
                    <span className="block text-sm font-medium text-gray-800 truncate">{realce(r.label, query)}</span>
                    {r.grupo && r.grupo !== 'Favoritos' && <span className="block text-[10px] text-gray-400 truncate">{cap(r.grupo)}</span>}
                </span>
                {r.modulo && <span className="text-[11px] text-gray-400 shrink-0 hidden sm:block">{r.modulo}</span>}
                {/* Estrela — favoritar/desfavoritar */}
                <span role="button" tabIndex={-1} onClick={(e) => toggleFav(e, r)}
                    title={isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
                    className="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center hover:bg-amber-50">
                    <i className={`fas fa-star text-xs ${isFav ? 'text-amber-400' : 'text-gray-300 hover:text-amber-300'}`} />
                </span>
            </button>
        );
    };

    const linhaDocs = (gi) => (
        <button key="busca-docs" type="button" data-idx={gi}
            onClick={() => ir(buscaDocs)}
            onMouseEnter={() => setAtivo(gi)}
            className={`w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors border-t border-gray-100 ${gi === ativo ? 'bg-blue-50' : ''}`}>
            <span className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${gi === ativo ? 'bg-blue-600 text-white' : 'bg-cyan-50 text-cyan-600'}`}>
                <i className="fas fa-file-magnifying-glass text-xs" />
            </span>
            <span className="flex-1 min-w-0 text-sm text-gray-700 truncate">
                Buscar <strong className="font-semibold text-gray-900">“{termo}”</strong> nos documentos
            </span>
            <span className="text-[11px] text-gray-400 shrink-0 hidden sm:block">Busca</span>
        </button>
    );

    const qtdRotinas = resultado.favs.length + resultado.resto.length;

    return (
        <>
            {/* Gatilho na topbar */}
            <button type="button" onClick={() => setOpen(true)}
                className="hidden md:flex items-center bg-[#f5f5f9] rounded-xl px-4 py-2.5 w-80 text-left hover:bg-[#ececf3] transition-colors">
                <i className="fas fa-search text-gray-400 text-sm mr-3" />
                <span className="text-sm text-gray-400 flex-1">Buscar rotina ou documento...</span>
                <span className="text-[10px] font-semibold text-gray-400 bg-white border border-gray-200 rounded px-1.5 py-0.5">Ctrl K</span>
            </button>

            {open && createPortal(
                <div className="fixed inset-0 z-[100] flex items-start justify-center p-4 pt-[12vh] bg-black/40 backdrop-blur-sm"
                    onMouseDown={() => setOpen(false)}>
                    <div className="w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden animate-fadeIn"
                        onMouseDown={(e) => e.stopPropagation()}>
                        {/* Busca */}
                        <div className="flex items-center gap-3 px-4 border-b border-gray-100">
                            <i className="fas fa-search text-gray-400 text-sm" />
                            <input ref={inputRef} type="text" value={query}
                                onChange={(e) => { setQuery(e.target.value); setAtivo(0); }}
                                onKeyDown={onKey}
                                placeholder="Buscar rotina em todos os módulos..."
                                style={{ outline: 'none', boxShadow: 'none' }}
                                className="flex-1 py-4 text-sm placeholder-gray-400" />
                            <span className="text-[10px] text-gray-400">esc</span>
                        </div>

                        {/* Resultados */}
                        <div ref={listRef} className="max-h-80 overflow-y-auto py-2">
                            {qtdRotinas === 0 && !buscaDocs ? (
                                <div className="px-4 py-8 text-center text-sm text-gray-400">
                                    <i className="fas fa-search-minus text-2xl mb-2 block text-gray-300" />
                                    Nenhuma rotina encontrada.
                                </div>
                            ) : (
                                <>
                                    {resultado.favs.length > 0 && (
                                        <>
                                            <div className="px-4 pt-1 pb-1 text-[10px] font-bold uppercase tracking-wide text-amber-500">
                                                <i className="fas fa-star mr-1" />Favoritos
                                            </div>
                                            {resultado.favs.map((r, i) => linha(r, i))}
                                            {resultado.resto.length > 0 && (
                                                <div className="px-4 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                                    Todas as rotinas
                                                </div>
                                            )}
                                        </>
                                    )}
                                    {resultado.resto.map((r, i) => linha(r, resultado.favs.length + i))}
                                    {qtdRotinas === 0 && (
                                        <div className="px-4 pt-2 pb-3 text-center text-xs text-gray-400">Nenhuma rotina com esse nome.</div>
                                    )}
                                    {buscaDocs && linhaDocs(qtdRotinas)}
                                </>
                            )}
                        </div>

                        {/* Rodapé */}
                        <div className="flex items-center gap-4 px-4 py-2 border-t border-gray-100 text-[10px] text-gray-400">
                            <span><i className="fas fa-arrow-up" /> <i className="fas fa-arrow-down" /> navegar</span>
                            <span><i className="fas fa-turn-down fa-rotate-90" /> abrir</span>
                            <span><i className="fas fa-star text-amber-400" /> favoritar</span>
                            <span className="ml-auto">{rotinas.length} rotinas</span>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </>
    );
}

/** Caixa de título para exibir o grupo (seções vêm em CAIXA ALTA). */
function cap(s) {
    return String(s ?? '').toLowerCase().replace(/(^|\s|\/|\()\p{L}/gu, (c) => c.toUpperCase());
}

/** minúsculas + sem acento (filtro insensível a caixa/acento). */
function norm(s) {
    return String(s ?? '').toLowerCase().normalize('NFD').replace(/\p{M}/gu, '');
}

/** Realça os termos da busca no rótulo. */
function realce(texto, query) {
    if (!texto) return texto;
    const termos = (query ?? '').trim().toLowerCase().split(/\s+/).filter((t) => t.length >= 2);
    if (termos.length === 0) return texto;
    const padrao = termos.map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');
    const partes = String(texto).split(new RegExp(`(${padrao})`, 'ig'));
    return partes.map((p, i) =>
        termos.some((t) => p.toLowerCase() === t)
            ? <mark key={i} className="bg-yellow-200 text-gray-900 rounded px-0.5">{p}</mark>
            : p
    );
}
