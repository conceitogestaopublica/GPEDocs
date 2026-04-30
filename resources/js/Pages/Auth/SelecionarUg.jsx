/**
 * Selecionar Unidade Gestora (apos login).
 * Aparece quando o usuario tem acesso a mais de uma UG.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function SelecionarUg({ ugs, ug_atual }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    const [busca, setBusca] = useState('');
    const filtradas = ugs.filter(u => {
        if (! busca.trim()) return true;
        const t = busca.toLowerCase();
        return u.nome.toLowerCase().includes(t) || (u.codigo || '').toLowerCase().includes(t);
    });

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const escolher = (id) => {
        // Inertia.post nao vai navegar entre dominios; usamos form submit padrao
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/selecionar-ug/${id}`;
        form.innerHTML = `<input type="hidden" name="_token" value="${csrf()}">`;
        document.body.appendChild(form);
        form.submit();
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
            <Head title="Selecionar UG" />

            <header className="bg-white border-b border-gray-200 shadow-sm">
                <div className="max-w-5xl mx-auto px-6 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center shadow-md">
                            <i className="fas fa-building text-white text-sm" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-gray-800 leading-tight">Selecione a Unidade Gestora</p>
                            <p className="text-[11px] text-gray-400">Voce tem acesso a {ugs.length} UG(s)</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="text-right hidden sm:block">
                            <p className="text-sm font-semibold text-gray-700">{user?.name}</p>
                            <p className="text-[11px] text-gray-400">{user?.email}</p>
                        </div>
                        <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                            <span className="text-white font-bold text-sm">{(user?.name || 'U').substring(0, 2).toUpperCase()}</span>
                        </div>
                        <button onClick={() => router.post('/logout')}
                            className="text-gray-400 hover:text-red-500 transition-colors ml-2" title="Sair">
                            <i className="fas fa-sign-out-alt" />
                        </button>
                    </div>
                </div>
            </header>

            <main className="max-w-5xl mx-auto px-6 py-10">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-gray-800">Em qual UG voce quer trabalhar agora?</h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Cada UG tem seus proprios documentos e processos. Voce pode trocar a qualquer momento.
                    </p>
                </div>

                {ugs.length > 4 && (
                    <div className="max-w-md mx-auto mb-6">
                        <div className="relative">
                            <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                            <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                                placeholder="Filtrar UGs..."
                                className="w-full pl-9 pr-3 py-2.5 rounded-xl border border-gray-200 bg-white text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {filtradas.map(ug => {
                        const ativa = ug.id === ug_atual;
                        return (
                            <button key={ug.id} onClick={() => escolher(ug.id)}
                                className={`text-left bg-white rounded-2xl border-2 p-5 transition-all hover:shadow-xl group ${
                                    ativa
                                        ? 'border-blue-500 shadow-lg ring-2 ring-blue-200'
                                        : 'border-gray-100 hover:border-blue-300'
                                }`}>
                                <div className="flex items-center gap-3 mb-3">
                                    <div className={`w-12 h-12 rounded-xl flex items-center justify-center shadow ${
                                        ativa ? 'bg-blue-600' : 'bg-gradient-to-br from-blue-500 to-indigo-600 group-hover:scale-110 transition-transform'
                                    }`}>
                                        <i className="fas fa-building text-white" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-[10px] text-gray-400 font-mono uppercase">{ug.codigo}</p>
                                        <p className="text-sm font-bold text-gray-800 leading-tight truncate">{ug.nome}</p>
                                    </div>
                                </div>
                                <div className="text-[11px] text-gray-500 space-y-0.5">
                                    {ug.cnpj && <p><i className="fas fa-id-card-alt mr-1.5 text-gray-400" />{ug.cnpj}</p>}
                                    {ug.cidade && (
                                        <p><i className="fas fa-map-marker-alt mr-1.5 text-gray-400" />{ug.cidade}{ug.uf && `/${ug.uf}`}</p>
                                    )}
                                </div>
                                <div className="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                                    {ativa ? (
                                        <span className="text-[11px] text-blue-700 font-bold">
                                            <i className="fas fa-check-circle mr-1" />
                                            UG Atual
                                        </span>
                                    ) : (
                                        <span className="text-[11px] text-gray-400">Clique para entrar</span>
                                    )}
                                    <i className="fas fa-arrow-right text-blue-500 group-hover:translate-x-1 transition-transform" />
                                </div>
                            </button>
                        );
                    })}
                </div>

                {filtradas.length === 0 && (
                    <div className="text-center py-12 text-gray-400">
                        <i className="fas fa-search text-3xl mb-2 block" />
                        Nenhuma UG corresponde ao filtro.
                    </div>
                )}
            </main>

            <footer className="text-center py-6 text-xs text-gray-400">
                <span className="font-medium text-gray-500">Conceito Gestao Publica</span> — Plataforma Digital Integrada &copy; {new Date().getFullYear()}
            </footer>
        </div>
    );
}
