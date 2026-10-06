/**
 * Layout Admin — GPE Docs
 *
 * Mesmo desenho do gpe2 e do Tributário (paleta GestãoPub): sidebar escura com faixa de
 * seções + painel, topbar branca com a UG ativa (pílula verde), busca Ctrl+K, favoritos,
 * notificações e perfil; avisos como toast no canto e rodapé com a marca GPE Cloud.
 */
import { useState, useEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import FlashMessage from '../Components/FlashMessage';
import Sidebar from '../Components/Sidebar';
import ChatFlutuante from '../Components/ChatFlutuante';
import CommandPalette from '../Components/CommandPalette';
import { rotinasDe } from '../menus';

// Detectar modulo pela URL
function getModulo(url) {
    if (url.startsWith('/configuracoes') || url.startsWith('/perfil/certificados')) return 'configuracoes';
    if (url.startsWith('/flow') || url.startsWith('/processos') || url.startsWith('/tramitacoes') || url.startsWith('/memorandos') || url.startsWith('/circulares') || url.startsWith('/oficios') || url === '/admin/tipos-processo' || url.startsWith('/admin/oficios-modelos')) return 'gepsp';
    return 'ged';
}

export default function AdminLayout({ children }) {
    const { auth, flash, notificacoes_pendentes, tenant, favoritos } = usePage().props;
    const { url } = usePage();
    const modulo = getModulo(url);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    // Sidebar começa MINIMIZADA (só a faixa); abre expandida se o usuário a expandiu nesta
    // sessão do navegador (sessionStorage — some ao fechar o navegador/aba).
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

    const contentMargin = isMobile ? 'ml-0' : (collapsed ? 'ml-[84px]' : 'ml-[300px]');

    return (
        <div className="min-h-screen bg-mist-50">
            {/* Overlay mobile */}
            {isMobile && sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
                    onClick={() => setSidebarOpen(false)} />
            )}

            <Sidebar
                modulo={modulo}
                collapsed={collapsed}
                isMobile={isMobile}
                sidebarOpen={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
            />

            {/* Conteudo */}
            <div className={`${contentMargin} print:!ml-0 min-w-0 overflow-x-clip min-h-screen flex flex-col transition-all duration-300`}>
                <header className="h-[70px] bg-white border-b border-mist-200 flex items-center justify-between gap-2 px-4 sm:px-5 lg:px-8 sticky top-0 z-30 has-[[data-topbar-dropdown]]:z-[60] no-print">
                    {/* ESQUERDA — menu + contexto (UG ativa) */}
                    <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                        <button onClick={toggleSidebar} title="Menu"
                            className="w-9 h-9 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors shrink-0">
                            <i className="fas fa-bars text-sm" />
                        </button>

                        {tenant?.atual && (
                            <div className="hidden sm:flex items-center gap-1 rounded-2xl border border-gray-200 bg-gray-50/70 p-1 min-w-0">
                                <UgPill tenant={tenant} />
                            </div>
                        )}
                    </div>

                    {/* DIREITA — busca + ações do usuário */}
                    <div className="flex items-center gap-2 sm:gap-3 shrink-0">
                        {/* Busca global de rotinas + documentos (command palette, Ctrl+K) */}
                        <CommandPalette rotinas={rotinasDe(auth?.user?.permissoes)} />

                        <div className="hidden sm:block w-px h-8 bg-gray-200" />

                        <FavoritosMenu favoritos={favoritos || []} />
                        <NotificacoesDropdown count={notificacoes_pendentes || 0} />

                        <UserMenu user={auth?.user} />
                    </div>
                </header>

                {/* Avisos como TOAST (fixo, canto superior direito): em linha no topo, sumiam
                    fora da tela quando a ação saía de um formulário lá embaixo. */}
                {(flash?.success || flash?.error || flash?.warning) && (
                    <div className="fixed top-20 right-4 sm:right-6 z-[60] w-[min(28rem,calc(100vw-2rem))] space-y-2 pointer-events-none [&>*]:pointer-events-auto [&>*]:shadow-lg">
                        {flash?.success && <FlashMessage type="success" message={flash.success} />}
                        {flash?.error && <FlashMessage type="error" message={flash.error} />}
                        {flash?.warning && <FlashMessage type="warning" message={flash.warning} />}
                    </div>
                )}

                <main className="flex-1 px-4 sm:px-5 lg:px-8 pt-4 pb-6 min-w-0 max-w-full">
                    {children}
                </main>

                <footer className="px-5 lg:px-8 py-4 flex flex-col items-center gap-1.5 text-xs text-gray-400 no-print">
                    <img src="/images/logo-gpe-cloud-full.png" alt="GPE Cloud — Gestão Pública Eficiente" className="h-10 w-auto" />
                    <span>&copy; {new Date().getFullYear()} <span className="font-medium text-gray-500">Conceito Gestão Pública</span></span>
                </footer>
            </div>

            {/* Chat interno flutuante */}
            {auth?.user && <ChatFlutuante />}
        </div>
    );
}

/** Fecha o dropdown ao clicar fora. */
function useFora(setOpen) {
    const ref = useRef(null);
    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [setOpen]);
    return ref;
}

/**
 * UG ativa — pílula verde como a gestora do gpe2. Com mais de uma UG, leva à tela de troca.
 */
function UgPill({ tenant }) {
    const { atual, multiplas } = tenant;
    const conteudo = (
        <>
            <i className="fas fa-building text-[10px] shrink-0" />
            <span className="truncate min-w-0">{atual.nome}</span>
            {multiplas && <i className="fas fa-exchange-alt text-[9px] shrink-0 opacity-70" />}
        </>
    );
    const classe = 'flex items-center gap-2 bg-emerald-50 text-emerald-700 px-3 py-2 rounded-xl text-xs font-semibold max-w-48 lg:max-w-80 xl:max-w-[30rem]';

    return multiplas ? (
        <button type="button" onClick={() => router.post('/trocar-ug')} title={`${atual.codigo} · ${atual.nome} — trocar UG`}
            className={`${classe} hover:bg-emerald-100 transition-colors`}>
            {conteudo}
        </button>
    ) : (
        <span className={classe} title={`${atual.codigo} · ${atual.nome}`}>{conteudo}</span>
    );
}

/** Rotinas favoritas (marcadas com a estrela na busca Ctrl+K). */
function FavoritosMenu({ favoritos }) {
    const [open, setOpen] = useState(false);
    const ref = useFora(setOpen);
    const remover = (e, href) => {
        e.preventDefault();
        e.stopPropagation();
        router.post('/rotinas/favoritos/remover', { href }, { preserveScroll: true, preserveState: true, only: ['favoritos'] });
    };

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)} title="Favoritos"
                className="relative w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors">
                <i className="fas fa-star text-sm" />
                {favoritos.length > 0 && (
                    <span className="absolute -top-1 -right-1 w-5 h-5 bg-amber-400 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                        {favoritos.length}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden" data-topbar-dropdown>
                    <div className="px-4 py-2.5 border-b border-gray-100">
                        <span className="text-xs font-bold uppercase tracking-wide text-gray-500">
                            <i className="fas fa-star text-amber-400 mr-1.5" />Favoritos
                        </span>
                    </div>
                    <div className="max-h-80 overflow-y-auto py-1">
                        {favoritos.length === 0 ? (
                            <div className="px-4 py-6 text-center text-xs text-gray-400">
                                Abra a busca (<span className="font-semibold">Ctrl K</span>) e clique na
                                <i className="fas fa-star text-amber-400 mx-1" />da rotina.
                            </div>
                        ) : favoritos.map((f) => (
                            <Link key={f.href} href={f.href} onClick={() => setOpen(false)}
                                className="group flex items-center gap-3 px-4 py-2 hover:bg-primary-50 transition-colors">
                                <span className="w-8 h-8 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center shrink-0 group-hover:bg-primary-500 group-hover:text-white">
                                    <i className={`${f.icon || 'fas fa-circle'} text-xs`} />
                                </span>
                                <span className="flex-1 min-w-0 text-sm font-medium text-gray-800 truncate">{f.label}</span>
                                <button type="button" onClick={(e) => remover(e, f.href)} title="Remover dos favoritos"
                                    className="shrink-0 w-6 h-6 rounded flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50">
                                    <i className="fas fa-times text-xs" />
                                </button>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Dropdown de Notificacoes
 */
function NotificacoesDropdown({ count }) {
    const [open, setOpen] = useState(false);
    const ref = useFora(setOpen);

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)} title="Notificações"
                className="relative w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors">
                <i className="fas fa-bell text-sm" />
                {count > 0 && (
                    <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 animate-fadeIn" data-topbar-dropdown>
                    <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <p className="text-sm font-semibold text-gray-800">Notificações</p>
                        {count > 0 && <span className="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold">{count} novas</span>}
                    </div>
                    <div className="max-h-64 overflow-y-auto">
                        {count === 0 && (
                            <div className="px-4 py-6 text-center text-gray-400">
                                <i className="fas fa-bell-slash text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma notificação nova</p>
                            </div>
                        )}
                        <Link href={count > 0 ? '/notificacoes?nao_lidas=1' : '/notificacoes'}
                            className="block px-4 py-3 text-sm text-primary-600 hover:bg-primary-50 text-center font-medium">
                            {count > 0 ? 'Ver notificações novas' : 'Ver todas as notificações'}
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
    const ref = useFora(setOpen);
    const initials = (user?.name || 'U').substring(0, 2).toUpperCase();

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)}
                className="flex items-center gap-3 pl-1 pr-3 py-1 rounded-xl hover:bg-gray-50 transition-colors">
                <div className="w-10 h-10 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-md shadow-cyan-200">
                    <span className="text-white font-bold text-sm">{initials}</span>
                </div>
                <div className="hidden md:block text-left">
                    <p className="text-sm font-semibold text-gray-800 leading-tight">{user?.name || 'Usuário'}</p>
                    <p className="text-[11px] text-gray-400 leading-tight">{user?.perfil_nome || 'Usuário'}</p>
                </div>
                <i className={`fas fa-chevron-down text-[10px] text-gray-400 hidden md:block transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 animate-fadeIn" data-topbar-dropdown>
                    <div className="px-4 py-3 border-b border-gray-100">
                        <p className="text-sm font-semibold text-gray-800">{user?.name}</p>
                        <p className="text-xs text-gray-400">{user?.email}</p>
                    </div>
                    <div className="py-1">
                        <Link href="/perfil/certificados"
                            className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                            <div className="w-8 h-8 bg-gray-50 rounded-lg flex items-center justify-center">
                                <i className="fas fa-id-card text-xs text-gray-400" />
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
