/**
 * Layout Admin — GED
 *
 * Sidebar com menu de navegacao do GED, topbar clean com busca,
 * notificacoes e perfil. Fundo cinza claro (#f5f5f9).
 * Baseado no layout do GPE2 (estilo Modernize).
 */
import { useState, useEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import FlashMessage from '../Components/FlashMessage';

// Menus separados por modulo
const MENU_GED = [
    { title: 'Dashboard', icon: 'fas fa-tachometer-alt', href: '/dashboard', color: 'text-blue-600 bg-blue-100' },
    { section: 'label', label: 'Documentos' },
    { title: 'Meus Documentos', icon: 'fas fa-file-alt', href: '/documentos', color: 'text-emerald-600 bg-emerald-100' },
    { title: 'Favoritos', icon: 'fas fa-star', href: '/documentos?filtro=favoritos', color: 'text-yellow-600 bg-yellow-100' },
    { title: 'Ultimos Acessados', icon: 'fas fa-clock', href: '/documentos?filtro=recentes', color: 'text-cyan-600 bg-cyan-100' },
    { title: 'Mais Acessados', icon: 'fas fa-fire', href: '/documentos?filtro=populares', color: 'text-orange-600 bg-orange-100' },
    { title: 'Arquivados', icon: 'fas fa-archive', href: '/documentos?filtro=arquivados', color: 'text-gray-600 bg-gray-200' },
    { section: 'label', label: 'Gestao' },
    { title: 'Capturar', icon: 'fas fa-camera', href: '/capturar', color: 'text-purple-600 bg-purple-100' },
    { title: 'Assinaturas', icon: 'fas fa-file-signature', href: '/assinaturas', color: 'text-emerald-600 bg-emerald-100' },
    { title: 'Busca Avancada', icon: 'fas fa-search', href: '/busca', color: 'text-indigo-600 bg-indigo-100' },
    { section: 'label', label: 'Administracao' },
    { title: 'Acervo', icon: 'fas fa-sitemap', href: '/repositorio', color: 'text-amber-600 bg-amber-100' },
    { title: 'Tipos Documentais', icon: 'fas fa-file-signature', href: '/admin/tipos-documentais', color: 'text-violet-600 bg-violet-100' },
    { title: 'Usuarios', icon: 'fas fa-users', href: '/admin/usuarios', color: 'text-red-600 bg-red-100' },
    { title: 'Perfis e Permissoes', icon: 'fas fa-shield-alt', href: '/admin/roles', color: 'text-slate-600 bg-slate-100' },
];

const MENU_GEPSP = [
    { title: 'Painel', icon: 'fas fa-tachometer-alt', href: '/processos/dashboard', color: 'text-teal-600 bg-teal-100' },
    { section: 'label', label: 'Processos' },
    { title: 'Caixa de Entrada', icon: 'fas fa-inbox', href: '/processos/inbox', color: 'text-blue-600 bg-blue-100' },
    { title: 'Abrir Processo', icon: 'fas fa-plus-circle', href: '/processos/create', color: 'text-green-600 bg-green-100' },
    { title: 'Todos Processos', icon: 'fas fa-folder-open', href: '/processos', color: 'text-indigo-600 bg-indigo-100' },
    { section: 'label', label: 'Comunicacao' },
    { title: 'Memorandos', icon: 'fas fa-envelope', href: '/memorandos', color: 'text-amber-600 bg-amber-100' },
    { title: 'Circulares', icon: 'fas fa-bullhorn', href: '/circulares', color: 'text-rose-600 bg-rose-100' },
    { title: 'Oficios', icon: 'fas fa-paper-plane', href: '/oficios', color: 'text-cyan-600 bg-cyan-100' },
    { section: 'label', label: 'Administracao' },
    { title: 'Tipos de Processo', icon: 'fas fa-cogs', href: '/admin/tipos-processo', color: 'text-teal-600 bg-teal-100' },
    { title: 'Usuarios', icon: 'fas fa-users', href: '/admin/usuarios', color: 'text-red-600 bg-red-100' },
    { title: 'Perfis e Permissoes', icon: 'fas fa-shield-alt', href: '/admin/roles', color: 'text-slate-600 bg-slate-100' },
];

// Detectar modulo pela URL
function getModulo(url) {
    if (url.startsWith('/processos') || url.startsWith('/tramitacoes') || url.startsWith('/memorandos') || url.startsWith('/circulares') || url.startsWith('/oficios') || url === '/admin/tipos-processo') return 'gepsp';
    return 'ged';
}

const MODULO_CONFIG = {
    ged:   { nome: 'GPE Docs', subtitulo: 'Gestao Documental', icon: 'fas fa-archive', cor: 'from-blue-600 to-indigo-700', shadow: 'shadow-blue-200', menu: MENU_GED },
    gepsp: { nome: 'GEPSP', subtitulo: 'Processos e Servicos', icon: 'fas fa-project-diagram', cor: 'from-teal-600 to-emerald-700', shadow: 'shadow-teal-200', menu: MENU_GEPSP },
};

export default function AdminLayout({ children }) {
    const { auth, flash, notificacoes_pendentes } = usePage().props;
    const { url } = usePage();
    const modulo = getModulo(url);
    const moduloConfig = MODULO_CONFIG[modulo];
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('ged_sidebar_collapsed') === 'true';
        }
        return false;
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
            localStorage.setItem('ged_sidebar_collapsed', String(next));
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

                        <form onSubmit={(e) => {
                            e.preventDefault();
                            const val = e.target.elements.globalSearch.value;
                            if (val) router.get('/busca', { q: val });
                        }} className="hidden md:flex items-center bg-[#f5f5f9] rounded-xl px-4 py-2.5 w-80">
                            <i className="fas fa-search text-gray-400 text-sm mr-3" />
                            <input name="globalSearch" type="text" placeholder="Buscar documentos, pastas, conteudo..."
                                className="bg-transparent text-sm text-gray-700 outline-none w-full placeholder-gray-400" />
                        </form>
                    </div>

                    <div className="flex items-center gap-2">
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
        </div>
    );
}

/**
 * Sidebar do GED
 */
function GedSidebar({ collapsed, isMobile, sidebarOpen, onClose, moduloConfig }) {
    const { url } = usePage();
    const MENU_ITEMS = moduloConfig.menu;

    const sidebarWidth = isMobile
        ? (sidebarOpen ? 'w-[260px] translate-x-0' : 'w-[260px] -translate-x-full')
        : (collapsed ? 'w-[68px]' : 'w-[260px]');

    return (
        <aside className={`fixed top-0 left-0 z-50 h-full bg-white border-r border-gray-100 overflow-hidden no-print transition-all duration-300 shadow-sm ${sidebarWidth}`}>
            <div className="flex flex-col h-full">
                {/* Logo */}
                <div className="h-[70px] flex items-center justify-between px-4 shrink-0 border-b border-gray-50">
                    <Link href="/modulos" className="flex items-center gap-3">
                        <div className={`w-10 h-10 bg-gradient-to-br ${moduloConfig.cor} rounded-xl flex items-center justify-center shadow-md ${moduloConfig.shadow} shrink-0`}>
                            <i className={`${moduloConfig.icon} text-white text-sm`} />
                        </div>
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
                    {MENU_ITEMS.map((item, i) => {
                        if (item.section === 'separator') {
                            return <div key={i} className="my-3 mx-2 border-t border-gray-100" />;
                        }
                        if (item.section === 'label') {
                            return !collapsed
                                ? <p key={i} className="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 pt-4 pb-1">{item.label}</p>
                                : <div key={i} className="my-3 mx-2 border-t border-gray-100" />;
                        }

                        // Itens com query string: match exato. Itens sem: match por prefixo (mas não se outro item com query bate exato).
                        const hasQuery = item.href.includes('?');
                        const exactMatch = url === item.href;
                        const prefixMatch = !hasQuery && (url.startsWith(item.href + '/') || url.startsWith(item.href + '?'));
                        const isActive = exactMatch || (prefixMatch && !MENU_ITEMS.some(m => m.href?.includes('?') && url === m.href));

                        const [iconColor, iconBg] = (item.color || 'text-gray-500 bg-gray-100').split(' ');

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`group flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 transition-all duration-200
                                    ${isActive
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-200'
                                        : 'text-gray-600 hover:bg-gray-50'
                                    }`}
                                title={collapsed ? item.title : undefined}
                            >
                                <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors
                                    ${isActive ? 'bg-white/20' : iconBg}`}>
                                    <i className={`${item.icon} text-xs ${isActive ? 'text-white' : iconColor}`} />
                                </div>
                                {!collapsed && (
                                    <span className={`text-[13px] truncate ${isActive ? 'font-semibold' : 'font-medium'}`}>
                                        {item.title}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
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
                        {count === 0 ? (
                            <div className="px-4 py-8 text-center text-gray-400">
                                <i className="fas fa-bell-slash text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma notificacao</p>
                            </div>
                        ) : (
                            <Link href="/notificacoes" className="block px-4 py-3 text-sm text-blue-600 hover:bg-blue-50 text-center font-medium">
                                Ver todas as notificacoes
                            </Link>
                        )}
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
