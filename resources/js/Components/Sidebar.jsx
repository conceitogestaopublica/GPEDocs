/**
 * Sidebar no padrão do gpe2 e do Tributário (paleta GestãoPub):
 *   fundo primary-900 · ativo primary-500 · caixa/hover primary-700 · texto mist-50.
 *
 *   FAIXA (84px): logo GPE Cloud (→ /modulos) + uma aba por seção do módulo.
 *   PAINEL (216px): seletor de módulo (Docs / Flow / Config), título da seção e os itens.
 *
 * As seções vêm do menus.js: um rótulo `{ section: 'label' }` abre seção, e um grupo com
 * `children` vira seção própria. Itens antes do primeiro rótulo ficam em "Início".
 * O menu chega já filtrado pelas permissões do usuário.
 */
import { useState, useEffect, useRef } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { MODULO_CONFIG, filtrarMenu, entradaDoModulo } from '../menus';

const ICONE_SECAO = {
    'Início': 'fas fa-house',
    'Documentos': 'fas fa-folder-open',
    'Ações': 'fas fa-bolt',
    'Administração': 'fas fa-cog',
    'Estrutura': 'fas fa-sitemap',
    'Acessos': 'fas fa-user-shield',
    'Portal do Cidadão': 'fas fa-globe',
    'Integrações': 'fas fa-plug',
};

/** Rótulo curto da aba (o completo fica no título do painel e no tooltip). */
const ROTULO_ABA = {
    'Caixa de Entrada': 'Entrada',
    'Processos Administrativos': 'Processos',
    'Portal do Cidadão': 'Portal',
    'Administração': 'Admin.',
    'Em Andamento': 'Andamento',
};

/** @returns {{section: string, icon: string, items: object[]}[]} */
export function agruparMenu(menu) {
    const grupos = [];
    let atual = null;
    for (const item of menu) {
        if (item.section === 'label') {
            atual = { section: item.label, icon: ICONE_SECAO[item.label] || 'fas fa-folder', items: [] };
            grupos.push(atual);
        } else if (item.section) {
            continue;
        } else if (Array.isArray(item.children)) {
            grupos.push({ section: item.title, icon: item.icon, items: item.children });
            atual = null;
        } else {
            if (!atual) {
                atual = { section: 'Início', icon: ICONE_SECAO['Início'], items: [] };
                grupos.push(atual);
            }
            atual.items.push(item);
        }
    }
    return grupos.filter((g) => g.items.length > 0);
}

/** Item ativo: href com query só casa exato; sem query, casa também o caminho filho. */
function ativo(href, url) {
    if (!href) return false;
    if (href.includes('?')) return url === href || url.startsWith(href + '&');
    const caminho = url.split('?')[0];
    return caminho === href || caminho.startsWith(href + '/');
}

export default function Sidebar({ modulo, collapsed, isMobile, sidebarOpen, onClose }) {
    const { url, props } = usePage();
    const permissoes = props.auth?.user?.permissoes;
    const cfg = MODULO_CONFIG[modulo];
    const grupos = agruparMenu(filtrarMenu(cfg.menu, permissoes));

    // Seção da página atual = a que tem o item de href mais específico que casa.
    let secaoAtiva = -1;
    let melhor = -1;
    grupos.forEach((g, i) => g.items.forEach((it) => {
        if (ativo(it.href, url) && it.href.length > melhor) { melhor = it.href.length; secaoAtiva = i; }
    }));

    const [secaoSel, setSecaoSel] = useState(secaoAtiva >= 0 ? secaoAtiva : 0);
    const [hovered, setHovered] = useState(false);
    useEffect(() => { if (secaoAtiva >= 0) setSecaoSel(secaoAtiva); }, [secaoAtiva]);

    const mostrarPainel = isMobile ? sidebarOpen : (!collapsed || hovered);
    const largura = isMobile
        ? (sidebarOpen ? 'w-[300px] translate-x-0' : 'w-[300px] -translate-x-full')
        : (mostrarPainel ? 'w-[300px]' : 'w-[84px]');
    const sombra = !isMobile && collapsed && hovered ? 'shadow-2xl' : 'shadow-sm';
    const grupo = grupos[secaoSel] || grupos[0];

    return (
        <aside
            onMouseEnter={() => !isMobile && collapsed && setHovered(true)}
            onMouseLeave={() => !isMobile && setHovered(false)}
            className={`fixed top-0 left-0 z-50 h-full bg-primary-900 text-mist-50 border-r border-primary-800 rounded-r-xl overflow-hidden no-print transition-all duration-300 ${sombra} ${largura}`}>
            <div className="flex h-full">
                {/* ═══ FAIXA — logo e abas das seções ═══ */}
                <div className="flex flex-col items-center border-r border-primary-800/70 shrink-0 w-[84px]">
                    <Link href="/modulos" className="w-full flex items-center justify-center h-[70px] hover:bg-primary-700 transition-colors" title="Módulos">
                        <img src="/images/logo-gpe-cloud-icon.png" alt="GPE Cloud" className="w-12 h-12 object-contain" />
                    </Link>
                    <div className="flex flex-col gap-1.5 mt-3 px-1.5 w-full overflow-y-auto">
                        {grupos.map((g, i) => (
                            <button key={g.section} type="button" onClick={() => setSecaoSel(i)} title={g.section}
                                aria-current={i === secaoSel ? 'page' : undefined}
                                className={`w-full flex flex-col items-center gap-1 py-2.5 px-1 rounded-lg text-[10px] leading-tight text-center transition-colors
                                    ${i === secaoSel ? 'bg-primary-500 text-mist-50 shadow-sm' : 'text-mist-50/60 hover:bg-primary-700 hover:text-mist-50'}`}>
                                <i className={`${g.icon} text-lg`} />
                                <span className="break-words hyphens-auto">{ROTULO_ABA[g.section] || g.section}</span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* ═══ PAINEL — módulo, seção e itens ═══ */}
                {mostrarPainel && (
                    <div className="flex-1 flex flex-col overflow-hidden">
                        <div className="relative min-h-[70px] flex flex-col justify-center px-5 py-2.5 shrink-0 border-b border-primary-800">
                            <ModuloSwitcher modulo={modulo} permissoes={permissoes} />
                            {isMobile && (
                                <button type="button" onClick={onClose} className="absolute top-6 right-4 text-mist-50/60 hover:text-mist-50">
                                    <i className="fas fa-times text-lg" />
                                </button>
                            )}
                        </div>

                        {grupo && (
                            <div className="px-4 pt-4">
                                <div className="flex items-center gap-2 rounded-lg bg-primary-700 px-3 py-2">
                                    <i className={`${grupo.icon} text-mist-50 text-xs`} />
                                    <p className="text-[11px] font-bold text-mist-50 uppercase tracking-wide">{grupo.section}</p>
                                </div>
                            </div>
                        )}

                        <nav className="flex-1 overflow-y-auto px-3 py-3">
                            {(grupo?.items || []).map((item) => {
                                const on = ativo(item.href, url) && !(grupo.items.some((o) => o !== item && o.href.length > item.href.length && ativo(o.href, url)));
                                return (
                                    <Link key={item.href} href={item.href}
                                        className={`group flex items-center gap-2 px-2 py-2 rounded-md mb-1 text-sm transition-colors
                                            ${on ? 'bg-primary-500 text-mist-50 shadow-sm' : 'text-mist-50 hover:bg-primary-700'}`}>
                                        <span className={`flex h-7 w-7 items-center justify-center rounded-lg shrink-0 ${on ? 'bg-mist-50/15' : 'bg-primary-700'}`}>
                                            <i className={`${item.icon} text-sm`} />
                                        </span>
                                        <span className="flex-1 text-sm leading-tight">{item.title}</span>
                                    </Link>
                                );
                            })}
                        </nav>

                        <div className="px-4 py-3 border-t border-primary-800 shrink-0">
                            <p className="text-[10px] text-mist-50/70 font-semibold">GPE Cloud</p>
                            <p className="text-[10px] text-mist-50/45">Gestão Pública Eficiente</p>
                        </div>
                    </div>
                )}
            </div>
        </aside>
    );
}

/** Cabeçalho do painel: módulo atual, com troca inline para os módulos que o perfil alcança. */
function ModuloSwitcher({ modulo, permissoes }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const atual = MODULO_CONFIG[modulo];

    useEffect(() => {
        const fora = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', fora);
        return () => document.removeEventListener('mousedown', fora);
    }, []);

    const outros = Object.entries(MODULO_CONFIG)
        .map(([chave, cfg]) => ({ chave, cfg, href: entradaDoModulo(chave, permissoes) }))
        .filter((m) => m.href);

    return (
        <div ref={ref}>
            <button type="button" onClick={() => setOpen((o) => !o)} title="Trocar módulo" className="w-full text-left group">
                <span className="text-[10px] font-semibold uppercase tracking-wide text-mist-50/55 group-hover:text-mist-50 flex items-center gap-1">
                    Módulo <i className={`fas fa-chevron-down text-[8px] transition-transform ${open ? 'rotate-180' : ''}`} />
                </span>
                <span className="mt-0.5 flex items-start gap-2 min-w-0">
                    <i className={`${atual.icon} text-mist-50 text-sm shrink-0 mt-0.5`} />
                    <span className="text-[15px] font-semibold text-mist-50 leading-tight">{atual.nome}</span>
                </span>
                <span className="block text-[11px] text-mist-50/55 mt-0.5 ml-6">{atual.subtitulo}</span>
            </button>

            {open && (
                <div className="absolute left-3 right-3 top-full mt-1 z-50 bg-white rounded-xl shadow-2xl border border-gray-100 py-2">
                    <p className="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Trocar módulo</p>
                    {outros.map(({ chave, cfg, href }) => (
                        <Link key={chave} href={href} onClick={() => setOpen(false)}
                            className={`w-full flex items-center gap-2.5 px-3 py-2 text-sm ${chave === modulo ? 'bg-cyan-50 text-cyan-700 font-semibold' : 'text-gray-600 hover:bg-gray-50'}`}>
                            <span className={`w-7 h-7 rounded-lg flex items-center justify-center shrink-0 ${chave === modulo ? 'bg-cyan-600 text-white' : 'bg-gray-100 text-gray-400'}`}>
                                <i className={`${cfg.icon} text-xs`} />
                            </span>
                            <span className="flex-1 min-w-0">
                                <span className="block truncate">{cfg.nome}</span>
                                <span className="block text-[10px] text-gray-400 font-normal">{cfg.subtitulo}</span>
                            </span>
                            {chave === modulo && <i className="fas fa-check text-cyan-600 text-xs" />}
                        </Link>
                    ))}
                    <div className="border-t border-gray-100 mt-1 pt-1">
                        <Link href="/modulos" className="flex items-center gap-2 px-3 py-2 text-sm text-gray-500 hover:bg-gray-50">
                            <i className="fas fa-th-large text-xs" /> Ver todos os módulos
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
