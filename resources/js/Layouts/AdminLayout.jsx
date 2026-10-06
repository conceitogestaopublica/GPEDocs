/**
 * Layout Admin — GED
 *
 * Sidebar com menu de navegacao do GED, topbar clean com busca,
 * notificacoes e perfil. Fundo cinza claro (#f5f5f9).
 * Baseado no layout do GPE2 (estilo Modernize).
 */
import { useState, useEffect, useLayoutEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import FlashMessage from '../Components/FlashMessage';
import ModuloIcon from '../Components/ModuloIcon';
import ChatFlutuante from '../Components/ChatFlutuante';
import CommandPalette from '../Components/CommandPalette';
import { MODULO_CONFIG, filtrarMenu, rotinasDe } from '../menus';

// Detectar modulo pela URL
function getModulo(url) {
    if (url.startsWith('/configuracoes') || url.startsWith('/perfil/certificados')) return 'configuracoes';
    if (url.startsWith('/flow') || url.startsWith('/processos') || url.startsWith('/tramitacoes') || url.startsWith('/memorandos') || url.startsWith('/circulares') || url.startsWith('/oficios') || url === '/admin/tipos-processo' || url.startsWith('/admin/oficios-modelos')) return 'gepsp';
    return 'ged';
}

export default function AdminLayout({ children }) {
    const { auth, flash, notificacoes_pendentes, tenant } = usePage().props;
    const { url } = usePage();
    const modulo = getModulo(url);
    const moduloConfig = MODULO_CONFIG[modulo];
    const [sidebarOpen, setSidebarOpen] = useState(false);
    // Sidebar começa MINIMIZADA; só abre expandida se o usuário a expandiu nesta sessão
    // do navegador (sessionStorage — some ao fechar o navegador/aba). A preferência antiga
    // ficava no localStorage para sempre; é descartada.
    const [collapsed, setCollapsed] = useState(() => {
        try {
            localStorage.removeItem('ged_sidebar_collapsed');
            return sessionStorage.getItem('ged_sidebar_expandida') !== 'true';
        } catch (_) {
            return true;
        }
    });
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const check = () => setIsMobile(window.innerWidth < 1024);
        check();
        window.addEventListener('resize', check);
        return () => window.removeEventListener('resize', check);
    }, []);

    const toggleSidebar = () => {
        if (isMobile) {
            setSidebarOpen(!sidebarOpen);
        } else {
            const next = !collapsed;
            setCollapsed(next);
            try { sessionStorage.setItem('ged_sidebar_expandida', String(!next)); } catch (_) { /* sem storage */ }
        }
    };

    const contentMargin = isMobile ? 'ml-0' : (collapsed ? 'ml-[68px]' : 'ml-[260px]');

    return (
        <div className="min-h-screen bg-[#f5f5f9]">
            {/* Overlay mobile */}
            {isMobile && sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
                    onClick={() => setSidebarOpen(false)} />
            )}

            {/* Sidebar */}
            <GedSidebar
                collapsed={collapsed}
                isMobile={isMobile}
                sidebarOpen={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                moduloConfig={moduloConfig}
            />

            {/* Conteudo */}
            <div className={`${contentMargin} min-h-screen flex flex-col transition-all duration-300`}>
                {/* Topbar */}
                <header className="h-[70px] bg-white border-b border-gray-100 flex items-center justify-between px-5 lg:px-8 sticky top-0 z-30 no-print">
                    <div className="flex items-center gap-4">
                        <button onClick={toggleSidebar}
                            className="w-9 h-9 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors">
                            <i className="fas fa-bars text-sm" />
                        </button>

                        {/* Busca global de rotinas + documentos (command palette, Ctrl+K) */}
                        <CommandPalette rotinas={rotinasDe(auth?.user?.permissoes)} />
                    </div>

                    <div className="flex items-center gap-2">
                        {/* UG atual */}
                        {tenant?.atual && (
                            <UgBadge tenant={tenant} />
                        )}

                        {/* Notificacoes */}
                        <NotificacoesDropdown count={notificacoes_pendentes || 0} />

                        <div className="hidden md:block w-px h-8 bg-gray-200" />

                        {/* Perfil */}
                        <UserMenu user={auth?.user} />
                    </div>
                </header>

                {/* Flash messages */}
                <div className="px-5 lg:px-8 mt-5">
                    {flash?.success && <FlashMessage type="success" message={flash.success} />}
                    {flash?.error && <FlashMessage type="error" message={flash.error} />}
                    {flash?.warning && <FlashMessage type="warning" message={flash.warning} />}
                </div>

                {/* Conteudo da pagina */}
                <main className="flex-1 px-5 lg:px-8 py-6">
                    {children}
                </main>

                {/* Footer */}
                <footer className="px-5 lg:px-8 py-4 text-center text-xs text-gray-400">
                    <span className="font-medium text-gray-500">{moduloConfig.nome}</span> &copy; {new Date().getFullYear()} —
                    <span className="font-medium text-gray-500">Conceito Gestao Publica</span>
                </footer>
            </div>

            {/* Chat interno flutuante */}
            {auth?.user && <ChatFlutuante />}
        </div>
    );
}

/**
 * Sidebar do GED
 */
function GedSidebar({ collapsed, isMobile, sidebarOpen, onClose, moduloConfig }) {
    const { url, props } = usePage();
    const MENU_ITEMS = filtrarMenu(moduloConfig.menu, props.auth?.user?.permissoes);

    const sidebarWidth = isMobile
        ? (sidebarOpen ? 'w-[260px] translate-x-0' : 'w-[260px] -translate-x-full')
        : (collapsed ? 'w-[68px]' : 'w-[260px]');

    return (
        <aside className={`fixed top-0 left-0 z-50 h-full bg-white border-r border-gray-100 overflow-hidden no-print transition-all duration-300 shadow-sm ${sidebarWidth}`}>
            <div className="flex flex-col h-full">
                {/* Logo */}
                <div className="h-[70px] flex items-center justify-between px-4 shrink-0 border-b border-gray-50">
                    <Link href="/modulos" className="flex items-center gap-3">
                        <ModuloIcon texto={moduloConfig.iconText || 'Docs'} size={40} className="shrink-0" />
                        {!collapsed && (
                            <div>
                                <p className="text-sm font-bold text-gray-800 leading-tight">{moduloConfig.nome}</p>
                                <p className="text-[10px] text-gray-400 leading-tight">{moduloConfig.subtitulo}</p>
                            </div>
                        )}
                    </Link>
                    {isMobile && (
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600 transition-colors">
                            <i className="fas fa-times text-lg" />
                        </button>
                    )}
                </div>

                {/* Menu */}
                <nav className="flex-1 overflow-y-auto px-3 py-4">
                    {MENU_ITEMS.map((item, i) => (
                        <MenuNode key={item.href || item.title || i}
                            item={item} url={url} collapsed={collapsed} />
                    ))}
                </nav>

                {/* Footer */}
                {!collapsed && (
                    <div className="px-4 py-3 border-t border-gray-100 shrink-0">
                        <p className="text-[10px] text-gray-400 font-medium">Conceito Gestao Publica</p>
                        <p className="text-[10px] text-gray-300">v1.0 — Laravel 13 + React</p>
                    </div>
                )}
            </div>
        </aside>
    );
}

/**
 * Item de menu na sidebar. Renderiza:
 * - Separator / label de secao
 * - Link direto (item simples com href)
 * - Grupo expansivel (item com children)
 */
function MenuNode({ item, url, collapsed }) {
    // Separator
    if (item.section === 'separator') {
        return <div className="my-3 mx-2 border-t border-gray-100" />;
    }
    // Label de secao
    if (item.section === 'label') {
        return !collapsed
            ? <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 pt-4 pb-1">{item.label}</p>
            : <div className="my-3 mx-2 border-t border-gray-100" />;
    }
    // Grupo com filhos
    if (Array.isArray(item.children) && item.children.length > 0) {
        return <MenuGroup item={item} url={url} collapsed={collapsed} />;
    }
    // Link simples
    return <MenuLink item={item} url={url} collapsed={collapsed} />;
}

function MenuLink({ item, url, collapsed, indented = false }) {
    const hasQuery = item.href.includes('?');
    const exactMatch = url === item.href;
    const prefixMatch = !hasQuery && (url.startsWith(item.href + '/') || url.startsWith(item.href + '?'));
    const isActive = exactMatch || prefixMatch;
    const [iconColor, iconBg] = (item.color || 'text-gray-500 bg-gray-100').split(' ');
    const flyout = useFlyout(collapsed);

    return (
        <div ref={flyout.ref} onMouseEnter={flyout.abrir} onMouseLeave={flyout.fechar} onFocus={flyout.abrir} onBlur={flyout.fechar}>
        <Link
            href={item.href}
            className={`group flex items-center gap-3 ${indented ? 'pl-8 pr-3' : 'px-3'} py-2 rounded-xl mb-1 transition-all duration-200
                ${isActive
                    ? 'bg-blue-600 text-white shadow-md shadow-blue-200'
                    : 'text-gray-600 hover:bg-gray-50'
                }`}
            aria-label={collapsed ? item.title : undefined}
        >
            <div className={`${indented ? 'w-6 h-6' : 'w-8 h-8'} rounded-lg flex items-center justify-center shrink-0 transition-colors
                ${isActive ? 'bg-white/20' : iconBg}`}>
                <i className={`${item.icon} text-[10px] ${isActive ? 'text-white' : iconColor}`} />
            </div>
            {!collapsed && (
                <span className={`text-[12.5px] truncate ${isActive ? 'font-semibold' : 'font-medium'}`}>
                    {item.title}
                </span>
            )}
        </Link>
        {flyout.rect && (
            <FlyoutMenu rect={flyout.rect} centralizado onEnter={flyout.abrir} onLeave={flyout.fechar}>
                <span className={`block px-3 py-2 text-[12.5px] font-semibold whitespace-nowrap ${isActive ? 'text-blue-600' : 'text-gray-700'}`}>
                    {item.title}
                </span>
            </FlyoutMenu>
        )}
        </div>
    );
}

function MenuGroup({ item, url, collapsed }) {
    const storageKey = 'menu_group_' + (item.title || '').replace(/\W+/g, '_');
    // Verifica se algum filho está ativo, para auto-expandir
    const hasActiveChild = item.children.some(c => {
        if (!c.href) return false;
        const hasQuery = c.href.includes('?');
        return c.href === url || (!hasQuery && (url.startsWith(c.href + '/') || url.startsWith(c.href + '?')));
    });

    const [open, setOpen] = useState(() => {
        if (typeof window === 'undefined') return hasActiveChild;
        const saved = localStorage.getItem(storageKey);
        return saved === null ? hasActiveChild : saved === 'true';
    });

    // Garante expandir quando um filho fica ativo (apos navegacao)
    useEffect(() => {
        if (hasActiveChild) setOpen(true);
    }, [hasActiveChild]);

    const toggle = () => {
        const next = !open;
        setOpen(next);
        if (typeof window !== 'undefined') localStorage.setItem(storageKey, String(next));
    };

    const [iconColor, iconBg] = (item.color || 'text-gray-500 bg-gray-100').split(' ');
    const flyout = useFlyout(collapsed);

    return (
        <div className="mb-1" ref={flyout.ref} onMouseEnter={flyout.abrir} onMouseLeave={flyout.fechar} onFocus={flyout.abrir} onBlur={flyout.fechar}>
            <button
                type="button"
                onClick={toggle}
                className={`w-full group flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200
                    ${hasActiveChild ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'}`}
                aria-label={collapsed ? item.title : undefined}
                aria-expanded={collapsed ? Boolean(flyout.rect) : open}
            >
                <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${iconBg}`}>
                    <i className={`${item.icon} text-xs ${iconColor}`} />
                </div>
                {!collapsed && (
                    <>
                        <span className={`flex-1 text-left text-[13px] truncate ${hasActiveChild ? 'font-semibold' : 'font-medium'}`}>
                            {item.title}
                        </span>
                        <i className={`fas fa-chevron-${open ? 'down' : 'right'} text-[9px] text-gray-400`} />
                    </>
                )}
            </button>

            {open && !collapsed && (
                <div className="mt-1 ml-1 pl-3 border-l border-gray-100">
                    {item.children.map(child => (
                        <MenuLink key={child.href} item={child} url={url} collapsed={false} indented />
                    ))}
                </div>
            )}

            {/* Minimizada: os filhos só ficariam acessíveis expandindo a sidebar — o
                flyout mostra o grupo inteiro, com os links clicáveis. */}
            {flyout.rect && (
                <FlyoutMenu rect={flyout.rect} onEnter={flyout.abrir} onLeave={flyout.fechar}>
                    <p className="px-3 pt-2.5 pb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">{item.title}</p>
                    <div className="px-1.5 pb-1.5 min-w-[200px]">
                        {item.children.map(child => (
                            <MenuLink key={child.href} item={child} url={url} collapsed={false} />
                        ))}
                    </div>
                </FlyoutMenu>
            )}
        </div>
    );
}

/**
 * Flyout da sidebar MINIMIZADA: ao passar o mouse (ou focar) um ícone, o rótulo do item
 * desliza para fora da barra. Vai por portal com position fixed porque a <aside> tem
 * overflow-hidden e o <nav> rola — dentro deles o rótulo seria cortado.
 *
 * Fechar tem um atraso curto: dá tempo de levar o mouse do ícone até o flyout (grupo com
 * links clicáveis) sem ele sumir no caminho. Rolar o menu fecha (a posição ficaria velha).
 */
function useFlyout(ativo) {
    const ref = useRef(null);
    const timer = useRef(null);
    const [rect, setRect] = useState(null);

    const abrir = () => {
        if (!ativo || !ref.current) return;
        clearTimeout(timer.current);
        setRect(ref.current.getBoundingClientRect());
    };
    const fechar = () => {
        clearTimeout(timer.current);
        timer.current = setTimeout(() => setRect(null), 140);
    };

    useEffect(() => { if (!ativo) setRect(null); }, [ativo]);
    useEffect(() => {
        if (!rect) return;
        const onScroll = () => setRect(null);
        window.addEventListener('scroll', onScroll, true);
        return () => window.removeEventListener('scroll', onScroll, true);
    }, [rect]);
    useEffect(() => () => clearTimeout(timer.current), []);

    return { ref, rect, abrir, fechar };
}

function FlyoutMenu({ rect, centralizado = false, onEnter, onLeave, children }) {
    const painelRef = useRef(null);
    const [top, setTop] = useState(centralizado ? rect.top + rect.height / 2 : rect.top);

    // Grupo: alinhado ao topo do botão e, se não couber, sobe o necessário para caber na tela.
    useLayoutEffect(() => {
        if (centralizado || !painelRef.current) return;
        const h = painelRef.current.offsetHeight;
        setTop(Math.max(16, Math.min(rect.top, window.innerHeight - 16 - h)));
    }, [rect, centralizado]);

    return createPortal(
        <div ref={painelRef} style={{ left: rect.right + 10, top, transform: centralizado ? 'translateY(-50%)' : undefined }}
            onMouseEnter={onEnter} onMouseLeave={onLeave} className="fixed z-[80] no-print">
            <div className="animate-flyoutIn relative bg-white rounded-xl border border-gray-100 shadow-xl shadow-gray-300/40 overflow-y-auto"
                style={{ maxHeight: 'calc(100vh - 32px)' }}>
                {children}
            </div>
        </div>,
        document.body
    );
}

/**
 * Dropdown de Notificacoes
 */
function NotificacoesDropdown({ count }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)}
                className="relative w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors">
                <i className="fas fa-bell text-sm" />
                {count > 0 && (
                    <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 animate-fadeIn">
                    <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <p className="text-sm font-semibold text-gray-800">Notificacoes</p>
                        {count > 0 && <span className="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold">{count} novas</span>}
                    </div>
                    <div className="max-h-64 overflow-y-auto">
                        {count === 0 && (
                            <div className="px-4 py-6 text-center text-gray-400">
                                <i className="fas fa-bell-slash text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma notificacao nova</p>
                            </div>
                        )}
                        <Link href={count > 0 ? '/notificacoes?nao_lidas=1' : '/notificacoes'}
                            className="block px-4 py-3 text-sm text-blue-600 hover:bg-blue-50 text-center font-medium">
                            {count > 0 ? 'Ver notificacoes novas' : 'Ver todas as notificacoes'}
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Menu do usuario
 */
function UserMenu({ user }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const initials = (user?.name || 'U').substring(0, 2).toUpperCase();

    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)}
                className="flex items-center gap-3 pl-1 pr-3 py-1 rounded-xl hover:bg-gray-50 transition-colors">
                <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md shadow-blue-200">
                    <span className="text-white font-bold text-sm">{initials}</span>
                </div>
                <div className="hidden md:block text-left">
                    <p className="text-sm font-semibold text-gray-800 leading-tight">{user?.name || 'Usuario'}</p>
                    <p className="text-[11px] text-gray-400 leading-tight">{user?.email}</p>
                </div>
                <i className={`fas fa-chevron-down text-[10px] text-gray-400 hidden md:block transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 animate-fadeIn">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <p className="text-sm font-semibold text-gray-800">{user?.name}</p>
                        <p className="text-xs text-gray-400">{user?.email}</p>
                    </div>
                    <div className="py-1">
                        <Link href="/perfil/certificados"
                            className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <div className="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                                <i className="fas fa-id-card text-xs text-blue-600" />
                            </div>
                            <span className="font-medium">Meus certificados</span>
                        </Link>
                    </div>
                    <div className="border-t border-gray-100 py-1">
                        <Link href="/logout" method="post" as="button"
                            className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <div className="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center">
                                <i className="fas fa-sign-out-alt text-xs text-red-500" />
                            </div>
                            <span className="font-medium">Sair</span>
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}


function UgBadge({ tenant }) {
    const trocar = () => {
        router.post("/trocar-ug");
    };

    if (! tenant?.atual) return null;

    return (
        <div className="hidden md:flex items-center gap-2 bg-indigo-50 border border-indigo-200 rounded-xl px-3 py-1.5 mr-2"
            title={`UG ativa: ${tenant.atual.codigo} · ${tenant.atual.nome}`}>
            <div className="w-7 h-7 bg-indigo-600 rounded-lg flex items-center justify-center">
                <i className="fas fa-building text-white text-[10px]" />
            </div>
            <div className="leading-tight">
                <p className="text-[10px] text-indigo-500 font-mono uppercase tracking-wide">{tenant.atual.codigo}</p>
                <p className="text-[11px] text-indigo-900 font-semibold truncate max-w-[180px]">{tenant.atual.nome}</p>
            </div>
            {tenant.multiplas && (
                <button onClick={trocar}
                    className="ml-1 px-2 py-1 rounded-md text-[10px] text-indigo-700 hover:bg-indigo-100 font-medium transition-colors"
                    title="Trocar UG">
                    <i className="fas fa-exchange-alt" />
                </button>
            )}
        </div>
    );
}
