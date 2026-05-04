/**
 * Layout do Portal Cidadao — publico, sem auth.
 * Servido por subdominio (ex. pmparaguacu.gpedocs.com.br), entao todas as URLs
 * sao root-relativas (/, /buscar, /categoria/X, /servico/Y).
 */
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function PortalLayout({ ug, children, hideSearchBar = false }) {
    const [busca, setBusca] = useState('');
    const { cidadao } = usePage().props;

    const submeterBusca = (e) => {
        e.preventDefault();
        router.get('/buscar', busca ? { q: busca } : {});
    };

    const sair = () => router.post('/sair');

    return (
        <div className="min-h-screen bg-[#f5f7fb] flex flex-col">
            <header className="bg-white border-b border-gray-200">
                <div className="max-w-6xl mx-auto px-4 lg:px-6 py-4 flex items-center justify-between gap-4">
                    <Link href="/" className="flex items-center gap-3 group">
                        {ug?.brasao ? (
                            <img src={ug.brasao} alt="" className="w-12 h-12 rounded-lg object-cover bg-gray-100" />
                        ) : (
                            <div className="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-lg flex items-center justify-center shadow-md">
                                <i className="fas fa-landmark text-white text-lg" />
                            </div>
                        )}
                        <div className="leading-tight">
                            <p className="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Portal do Cidadao</p>
                            <p className="text-base font-bold text-gray-800 group-hover:text-blue-700 transition-colors">{ug?.nome || 'Carta de Servicos'}</p>
                            {ug?.cidade && <p className="text-[11px] text-gray-500">{ug.cidade}{ug.uf ? ` — ${ug.uf}` : ''}</p>}
                        </div>
                    </Link>

                    <nav className="hidden md:flex items-center gap-1 text-sm">
                        <Link href="/"
                            className="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-blue-700 font-medium">
                            <i className="fas fa-home text-xs mr-1.5" /> Inicio
                        </Link>
                        <Link href="/buscar"
                            className="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-blue-700 font-medium">
                            <i className="fas fa-search text-xs mr-1.5" /> Todos os Servicos
                        </Link>
                        {cidadao ? (
                            <>
                                <Link href="/minhas-solicitacoes"
                                    className="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-blue-700 font-medium">
                                    <i className="fas fa-clipboard-list text-xs mr-1.5" /> Minhas Solicitacoes
                                </Link>
                                <div className="ml-2 flex items-center gap-2 pl-3 border-l border-gray-200">
                                    <div className="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                        {(cidadao.nome || 'C').substring(0, 2).toUpperCase()}
                                    </div>
                                    <div className="leading-tight">
                                        <p className="text-xs font-semibold text-gray-700 max-w-[120px] truncate">{cidadao.nome}</p>
                                        <button onClick={sair} className="text-[10px] text-gray-400 hover:text-red-600">
                                            Sair
                                        </button>
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div className="ml-2 flex items-center gap-2 pl-3 border-l border-gray-200">
                                <Link href="/entrar" className="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50 hover:text-blue-700 text-sm font-medium">
                                    Entrar
                                </Link>
                                <Link href="/cadastrar" className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                    Cadastrar
                                </Link>
                            </div>
                        )}
                    </nav>
                </div>

                {!hideSearchBar && (
                    <div className="bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-600 py-6">
                        <div className="max-w-6xl mx-auto px-4 lg:px-6">
                            <form onSubmit={submeterBusca} className="flex gap-2 max-w-3xl mx-auto">
                                <div className="flex-1 relative">
                                    <i className="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="text"
                                        value={busca}
                                        onChange={(e) => setBusca(e.target.value)}
                                        placeholder="Busque por servico, palavra-chave ou categoria..."
                                        className="w-full pl-12 pr-4 py-3 rounded-xl bg-white text-gray-800 placeholder-gray-500 shadow-md focus:outline-none focus:ring-2 focus:ring-white/60 text-sm"
                                    />
                                </div>
                                <button type="submit"
                                    className="px-6 py-3 rounded-xl bg-white/20 backdrop-blur-sm text-white font-bold border-2 border-white hover:bg-white/30 transition-colors">
                                    Buscar
                                </button>
                            </form>
                        </div>
                    </div>
                )}
            </header>

            <main className="flex-1 max-w-6xl mx-auto w-full px-4 lg:px-6 py-8">
                {children}
            </main>

            <footer className="bg-gray-900 text-gray-400 mt-12">
                <div className="max-w-6xl mx-auto px-4 lg:px-6 py-8 grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                    <div>
                        <p className="text-white font-bold mb-2">{ug?.nome || 'Portal do Cidadao'}</p>
                        {ug?.cidade && <p>{ug.cidade}{ug.uf ? ` — ${ug.uf}` : ''}</p>}
                        {ug?.telefone && <p className="mt-1"><i className="fas fa-phone text-xs mr-2" />{ug.telefone}</p>}
                        {ug?.email && <p className="mt-1"><i className="fas fa-envelope text-xs mr-2" />{ug.email}</p>}
                        {ug?.site && (
                            <a href={ug.site} target="_blank" rel="noreferrer" className="hover:text-white mt-1 inline-block">
                                <i className="fas fa-globe text-xs mr-2" />Site oficial
                            </a>
                        )}
                    </div>
                    <div>
                        <p className="text-white font-bold mb-2">Acesso rapido</p>
                        <ul className="space-y-1">
                            <li><Link href="/" className="hover:text-white">Pagina inicial</Link></li>
                            <li><Link href="/buscar" className="hover:text-white">Todos os servicos</Link></li>
                        </ul>
                    </div>
                    <div>
                        <p className="text-white font-bold mb-2">Legislacao</p>
                        <p className="text-xs">Carta de Servicos publicada nos termos da Lei 13.460/2017 e do Decreto 9.094/2017.</p>
                    </div>
                </div>
                <div className="border-t border-gray-800 py-4 text-center text-xs">
                    &copy; {new Date().getFullYear()} {ug?.nome} — Plataforma desenvolvida por Conceito Gestao Publica
                </div>
            </footer>
        </div>
    );
}
