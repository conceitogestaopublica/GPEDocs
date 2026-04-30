/**
 * Selecao de Modulos — Conceito Gestao Publica
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import ModuloIcon from '../Components/ModuloIcon';

const MODULOS = [
    {
        key: 'ged',
        nome: 'Gestao Eletronica de Documentos',
        sigla: 'GPE Docs',
        descricao: 'Repositorio, captura, busca e controle de documentos',
        iconText: 'Docs',
        href: '/dashboard',
    },
    {
        key: 'gepsp',
        nome: 'Fluxos e Tramitacao Eletronica',
        sigla: 'GPE Flow',
        descricao: 'Protocolo eletronico, processos e fluxos administrativos',
        iconText: 'Flow',
        href: '/processos/dashboard',
    },
    {
        key: 'configuracoes',
        nome: 'Configuracoes do Sistema',
        sigla: 'GPE Config',
        descricao: 'Unidades gestoras, organograma, usuarios e perfis',
        iconText: 'Conf',
        href: '/configuracoes',
    },
];

export default function Modulos() {
    const { auth, tenant } = usePage().props;
    const user = auth?.user;
    const ugAtual = tenant?.atual;

    const trocarUg = () => router.post('/trocar-ug');

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

                    <div className="flex items-center gap-3">
                        {/* UG ativa */}
                        {ugAtual ? (
                            <div className="hidden md:flex items-center gap-2 bg-indigo-50 border border-indigo-200 rounded-xl px-3 py-1.5">
                                <div className="w-7 h-7 bg-indigo-600 rounded-lg flex items-center justify-center">
                                    <i className="fas fa-building text-white text-[10px]" />
                                </div>
                                <div className="leading-tight">
                                    <p className="text-[10px] text-indigo-500 font-mono uppercase tracking-wide">{ugAtual.codigo}</p>
                                    <p className="text-[11px] text-indigo-900 font-semibold truncate max-w-[180px]">{ugAtual.nome}</p>
                                </div>
                                {tenant?.multiplas && (
                                    <button onClick={trocarUg}
                                        className="ml-1 px-2 py-1 rounded-md text-[10px] text-indigo-700 hover:bg-indigo-100 font-medium transition-colors"
                                        title="Trocar UG">
                                        <i className="fas fa-exchange-alt" />
                                    </button>
                                )}
                            </div>
                        ) : (user?.super_admin && (
                            <button onClick={trocarUg}
                                className="hidden md:flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-3 py-1.5 hover:bg-amber-100 transition-colors"
                                title="Selecionar UG">
                                <i className="fas fa-exclamation-triangle text-amber-600 text-[11px]" />
                                <span className="text-[11px] text-amber-800 font-medium">Selecionar UG</span>
                            </button>
                        ))}

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
                            <div className="flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                <ModuloIcon texto={mod.iconText} size={72} />
                            </div>
                            <h3 className="text-sm font-bold text-gray-800 mb-1">{mod.sigla}</h3>
                            <p className="text-xs text-gray-500 leading-relaxed">{mod.nome}</p>
                            <p className="text-[10px] text-gray-400 mt-2">{mod.descricao}</p>
                        </Link>
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
