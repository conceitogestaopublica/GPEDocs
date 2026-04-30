/**
 * Selecao de Modulos — Conceito Gestao Publica
 */
import { Head, Link, usePage } from '@inertiajs/react';

const MODULOS = [
    {
        key: 'ged',
        nome: 'Gestao Eletronica de Documentos',
        sigla: 'GPE Docs',
        descricao: 'Repositorio, captura, busca e controle de documentos',
        icon: 'fas fa-archive',
        cor: 'from-blue-500 to-indigo-600',
        corLight: 'bg-blue-50 text-blue-600',
        href: '/dashboard',
    },
    {
        key: 'gepsp',
        nome: 'Processos e Servicos Publicos',
        sigla: 'GEPSP',
        descricao: 'Protocolo eletronico, tramitacao e fluxos administrativos',
        icon: 'fas fa-project-diagram',
        cor: 'from-teal-500 to-emerald-600',
        corLight: 'bg-teal-50 text-teal-600',
        href: '/processos/dashboard',
    },
];

export default function Modulos() {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
            <Head title="Modulos" />

            {/* Header */}
            <header className="bg-white border-b border-gray-200 shadow-sm">
                <div className="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center shadow-md">
                            <i className="fas fa-cubes text-white text-sm" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-gray-800 leading-tight">Conceito Gestao Publica</p>
                            <p className="text-[10px] text-gray-400 leading-tight">Plataforma Digital Integrada</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="text-right hidden sm:block">
                            <p className="text-sm font-semibold text-gray-700">{user?.name || 'Usuario'}</p>
                            <p className="text-[11px] text-gray-400">{user?.email}</p>
                        </div>
                        <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                            <span className="text-white font-bold text-sm">{(user?.name || 'U').substring(0, 2).toUpperCase()}</span>
                        </div>
                        <Link href="/logout" method="post" as="button"
                            className="text-gray-400 hover:text-red-500 transition-colors ml-2" title="Sair">
                            <i className="fas fa-sign-out-alt" />
                        </Link>
                    </div>
                </div>
            </header>

            {/* Conteudo */}
            <main className="max-w-4xl mx-auto px-6 py-12">
                <div className="text-center mb-10">
                    <h1 className="text-2xl font-bold text-gray-800">Selecionar Modulo</h1>
                    <p className="text-sm text-gray-500 mt-1">Escolha o modulo que deseja acessar</p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {MODULOS.map(mod => (
                        <Link
                            key={mod.key}
                            href={mod.href}
                            className="group bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-xl hover:border-transparent hover:ring-2 hover:ring-blue-200 transition-all duration-300 text-center"
                        >
                            <div className={`w-16 h-16 bg-gradient-to-br ${mod.cor} rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg group-hover:scale-110 transition-transform duration-300`}>
                                <i className={`${mod.icon} text-white text-2xl`} />
                            </div>
                            <h3 className="text-sm font-bold text-gray-800 mb-1">{mod.sigla}</h3>
                            <p className="text-xs text-gray-500 leading-relaxed">{mod.nome}</p>
                            <p className="text-[10px] text-gray-400 mt-2">{mod.descricao}</p>
                        </Link>
                    ))}

                    {/* Modulos futuros (desabilitados) */}
                    {[
                        { sigla: 'Portal', nome: 'Portal do Cidadao', icon: 'fas fa-globe', desc: 'Ouvidoria, E-SIC, Carta de Servicos e solicitacoes online' },
                        { sigla: 'EAD', nome: 'Capacitacao EAD', icon: 'fas fa-graduation-cap', desc: 'Treinamentos com certificacao para servidores' },
                        { sigla: 'BI', nome: 'Relatorios e BI', icon: 'fas fa-chart-bar', desc: 'Dashboards e indicadores gerenciais' },
                    ].map(mod => (
                        <div
                            key={mod.sigla}
                            className="bg-gray-50 rounded-2xl border border-dashed border-gray-200 p-6 text-center opacity-50 cursor-not-allowed"
                        >
                            <div className="w-16 h-16 bg-gray-200 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <i className={`${mod.icon} text-gray-400 text-2xl`} />
                            </div>
                            <h3 className="text-sm font-bold text-gray-500 mb-1">{mod.sigla}</h3>
                            <p className="text-xs text-gray-400">{mod.nome}</p>
                            <span className="inline-block mt-2 text-[10px] bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full">Em breve</span>
                        </div>
                    ))}
                </div>
            </main>

            {/* Footer */}
            <footer className="text-center py-6 text-xs text-gray-400">
                <span className="font-medium text-gray-500">Conceito Gestao Publica</span> — Plataforma Digital Integrada &copy; {new Date().getFullYear()}
            </footer>
        </div>
    );
}
