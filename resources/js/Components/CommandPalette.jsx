import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import useEscape from '../Hooks/useEscape';
import useFavoritoRotina from '../Hooks/useFavoritoRotina';
import { cap, norm, termosDe, trechosRealcados } from '../utils/busca';

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
    const { ehFavorito, alternar } = useFavoritoRotina();

    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [ativo, setAtivo] = useState(0);
    const listRef = useRef(null);

    // Ctrl/⌘+K abre/fecha em qualquer tela. Esc fecha (só esta camada — pilha do useEscape).
    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                setOpen((v) => !v);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);
    useEscape(() => setOpen(false), open);

    // Ao abrir: zera o destaque (o foco vai pelo autoFocus do campo).
    useEffect(() => {
        if (open) setAtivo(0);
    }, [open]);

    // Resultado: sem busca → favoritos no topo + demais; com busca → filtro achatado.
    const resultado = useMemo(() => {
        const termos = termosDe(query);
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
    const buscaDocs = useMemo(
        () => (termo ? { href: `/busca?q=${encodeURIComponent(termo)}`, label: termo, buscaDocs: true } : null),
        [termo],
    );
    const flat = useMemo(
        () => [...resultado.favs, ...resultado.resto, ...(buscaDocs ? [buscaDocs] : [])],
        [resultado, buscaDocs],
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

    const onKey = (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setAtivo((i) => Math.min(flat.length - 1, i + 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setAtivo((i) => Math.max(0, i - 1)); }
        else if (e.key === 'Enter') { e.preventDefault(); ir(flat[ativo]); }
        // Ctrl/⌘+D favorita o item destacado (a linha "buscar nos documentos" não é rotina).
        else if ((e.ctrlKey || e.metaKey) && (e.key === 'd' || e.key === 'D')) {
            e.preventDefault();
            const r = flat[ativo];
            if (r && !r.buscaDocs) alternar(r);
        }
    };

    // Renderiza uma linha; `gi` = índice global (destaque + navegação por teclado). A estrela
    // é um botão IRMÃO do botão da linha (interativo aninhado em <button> é HTML inválido).
    const linha = (r, gi) => {
        const isFav = ehFavorito(r.href);
        return (
            <div key={`${r.href}-${gi}`} data-idx={gi} onMouseEnter={() => setAtivo(gi)}
                className={`flex items-center pr-3 transition-colors ${gi === ativo ? 'bg-blue-50' : ''}`}>
                <button type="button" onClick={() => ir(r)} tabIndex={-1}
                    className="flex-1 min-w-0 flex items-center gap-3 pl-4 py-2.5 text-left">
                    <span className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${gi === ativo ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400'}`}>
                        <i className={`${r.icon || 'fas fa-circle'} text-xs`} />
                    </span>
                    <span className="flex-1 min-w-0">
                        <span className="block text-sm font-medium text-gray-800 truncate"><Realce texto={r.label} query={query} /></span>
                        {r.grupo && r.grupo !== 'Favoritos' && <span className="block text-[10px] text-gray-400 truncate">{cap(r.grupo)}</span>}
                    </span>
                    {r.modulo && <span className="text-[11px] text-gray-400 shrink-0 hidden sm:block">{r.modulo}</span>}
                </button>
                <button type="button" tabIndex={-1} onClick={() => alternar(r)}
                    title={isFav ? 'Remover dos favoritos (Ctrl+D)' : 'Adicionar aos favoritos (Ctrl+D)'}
                    aria-label={isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos'} aria-pressed={isFav}
                    className="shrink-0 w-7 h-7 ml-2 rounded-lg flex items-center justify-center hover:bg-amber-50">
                    <i className={`fas fa-star text-xs ${isFav ? 'text-amber-400' : 'text-gray-300 hover:text-amber-300'}`} />
                </button>
            </div>
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
                            <input autoFocus type="text" value={query}
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
                            <span><i className="fas fa-star text-amber-400" /> Ctrl D favoritar</span>
                            <span className="ml-auto">{rotinas.length} rotinas</span>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </>
    );
}

/** Rótulo com os termos da busca realçados (comparação sem acento — utils/busca). */
function Realce({ texto, query }) {
    return trechosRealcados(texto, query).map((t, i) => (t.realce
        ? <mark key={i} className="bg-yellow-200 text-gray-900 rounded px-0.5">{t.texto}</mark>
        : t.texto));
}
