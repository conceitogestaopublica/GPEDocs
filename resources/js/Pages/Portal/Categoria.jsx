/**
 * Portal — Lista de servicos de uma categoria.
 */
import { Head, Link } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';

const COR_BG = {
    red: 'bg-red-100 text-red-600', blue: 'bg-blue-100 text-blue-600',
    amber: 'bg-amber-100 text-amber-600', indigo: 'bg-indigo-100 text-indigo-600',
    orange: 'bg-orange-100 text-orange-600', green: 'bg-green-100 text-green-600',
    pink: 'bg-pink-100 text-pink-600', cyan: 'bg-cyan-100 text-cyan-600',
};

export default function PortalCategoria({ ug, categoria, servicos, publicos }) {
    const corCat = COR_BG[categoria.cor] || 'bg-gray-100 text-gray-600';

    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title={`${categoria.nome} — ${ug.nome}`} />

            {/* Breadcrumb */}
            <nav className="text-xs text-gray-500 mb-4">
                <Link href="/" className="hover:text-blue-600">Inicio</Link>
                <i className="fas fa-chevron-right mx-2 text-[10px]" />
                <span className="text-gray-700 font-medium">{categoria.nome}</span>
            </nav>

            {/* Hero */}
            <div className="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                <div className="flex items-start gap-4">
                    <div className={`w-16 h-16 rounded-2xl flex items-center justify-center shrink-0 ${corCat}`}>
                        <i className={`${categoria.icone || 'fas fa-folder'} text-2xl`} />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-800 mb-1">{categoria.nome}</h1>
                        <p className="text-sm text-gray-600">{categoria.descricao}</p>
                        <p className="text-xs text-gray-400 mt-2">{servicos.length} servico(s) disponivel(eis)</p>
                    </div>
                </div>
            </div>

            <div className="space-y-3">
                {servicos.map(servico => (
                    <Link
                        key={servico.id}
                        href={`/servico/${servico.slug}`}
                        className="bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-200 hover:ring-2 hover:ring-blue-100 hover:shadow-md transition-all flex items-start gap-4 group"
                    >
                        <div className={`w-12 h-12 rounded-xl flex items-center justify-center shrink-0 ${corCat}`}>
                            <i className={`${servico.icone || 'fas fa-file-alt'} text-lg`} />
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1">
                                <h3 className="text-base font-bold text-gray-800 group-hover:text-blue-700">{servico.titulo}</h3>
                                <span className="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full font-semibold">
                                    {publicos[servico.publico_alvo] || servico.publico_alvo}
                                </span>
                            </div>
                            <p className="text-sm text-gray-600 line-clamp-2">{servico.descricao_curta}</p>
                        </div>
                        <i className="fas fa-arrow-right text-gray-300 group-hover:text-blue-600 mt-3" />
                    </Link>
                ))}

                {servicos.length === 0 && (
                    <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                        <i className="fas fa-folder-open text-4xl text-gray-300 mb-3" />
                        <p className="text-gray-500">Nenhum servico publicado nesta categoria.</p>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
