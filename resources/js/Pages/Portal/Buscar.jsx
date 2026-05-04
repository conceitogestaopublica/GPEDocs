/**
 * Portal — busca/listagem geral de servicos com filtros.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';

export default function PortalBuscar({ ug, servicos, categorias, publicos, filtros }) {
    const [q, setQ] = useState(filtros?.q || '');
    const [categoriaId, setCategoriaId] = useState(filtros?.categoria_id || '');
    const [publicoAlvo, setPublicoAlvo] = useState(filtros?.publico_alvo || '');

    const aplicar = (e) => {
        e?.preventDefault();
        const params = {};
        if (q) params.q = q;
        if (categoriaId) params.categoria_id = categoriaId;
        if (publicoAlvo) params.publico_alvo = publicoAlvo;
        router.get('/buscar', params, { preserveState: true });
    };

    const limpar = () => {
        setQ(''); setCategoriaId(''); setPublicoAlvo('');
        router.get('/buscar');
    };

    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title={`Buscar servicos — ${ug.nome}`} />

            <h1 className="text-2xl font-bold text-gray-800 mb-1">Todos os servicos</h1>
            <p className="text-sm text-gray-500 mb-6">Use os filtros para encontrar o que precisa</p>

            <form onSubmit={aplicar} className="bg-white rounded-2xl border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-12 gap-3">
                <div className="md:col-span-5 relative">
                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Palavra-chave, titulo ou descricao..."
                        className="w-full pl-10 pr-3 py-2.5 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-300 text-sm"
                    />
                </div>
                <select
                    value={categoriaId}
                    onChange={(e) => setCategoriaId(e.target.value)}
                    className="md:col-span-3 px-3 py-2.5 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-300 text-sm"
                >
                    <option value="">Todas categorias</option>
                    {(categorias || []).map(c => (
                        <option key={c.id} value={c.id}>{c.nome}</option>
                    ))}
                </select>
                <select
                    value={publicoAlvo}
                    onChange={(e) => setPublicoAlvo(e.target.value)}
                    className="md:col-span-2 px-3 py-2.5 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-300 text-sm"
                >
                    <option value="">Todos publicos</option>
                    {Object.entries(publicos || {}).map(([v, l]) => (
                        <option key={v} value={v}>{l}</option>
                    ))}
                </select>
                <div className="md:col-span-2 flex gap-2">
                    <button type="submit" className="flex-1 px-4 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 text-sm">
                        Filtrar
                    </button>
                    <button type="button" onClick={limpar}
                        className="px-3 py-2.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm" title="Limpar">
                        <i className="fas fa-times" />
                    </button>
                </div>
            </form>

            <div className="space-y-3">
                {(servicos.data || []).map(servico => (
                    <Link
                        key={servico.id}
                        href={`/servico/${servico.slug}`}
                        className="bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-200 hover:ring-2 hover:ring-blue-100 transition-all flex items-start gap-4 group"
                    >
                        <div className="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
                            <i className={`${servico.icone || 'fas fa-file-alt'} text-lg`} />
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1 flex-wrap">
                                <h3 className="text-base font-bold text-gray-800 group-hover:text-blue-700">{servico.titulo}</h3>
                                {servico.categoria && (
                                    <span className="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-semibold">
                                        {servico.categoria.nome}
                                    </span>
                                )}
                                <span className="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full font-semibold">
                                    {publicos[servico.publico_alvo] || servico.publico_alvo}
                                </span>
                            </div>
                            <p className="text-sm text-gray-600 line-clamp-2">{servico.descricao_curta}</p>
                        </div>
                    </Link>
                ))}

                {(servicos.data || []).length === 0 && (
                    <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                        <i className="fas fa-search text-4xl text-gray-300 mb-3" />
                        <p className="text-gray-500">Nenhum servico encontrado com os filtros aplicados.</p>
                    </div>
                )}
            </div>

            {/* Paginacao simples */}
            {servicos.data && servicos.last_page > 1 && (
                <div className="flex justify-center gap-1 mt-8">
                    {servicos.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                ${link.active
                                    ? 'bg-blue-600 text-white'
                                    : link.url
                                        ? 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'
                                        : 'text-gray-300 cursor-default'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </PortalLayout>
    );
}
