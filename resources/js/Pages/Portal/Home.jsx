/**
 * Portal — Home da Carta de Servicos de uma UG.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';

function BannerCarrossel({ banners }) {
    const [idx, setIdx] = useState(0);
    const [paused, setPaused] = useState(false);
    const total = banners.length;

    useEffect(() => {
        if (paused || total <= 1) return;
        const id = setInterval(() => setIdx(i => (i + 1) % total), 6000);
        return () => clearInterval(id);
    }, [paused, total]);

    return (
        <div
            className="relative rounded-2xl overflow-hidden shadow-lg"
            style={{ aspectRatio: '5 / 1', minHeight: '160px' }}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
        >
            {banners.map((b, i) => (
                <div key={b.id}
                    className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${i === idx ? 'opacity-100 z-10' : 'opacity-0 z-0'}`}>
                    <img src={b.imagem} alt={b.titulo || `Banner ${i + 1}`} className="absolute inset-0 w-full h-full object-cover" loading={i === 0 ? 'eager' : 'lazy'} />
                    {(b.titulo || b.subtitulo || b.link_url) && (
                        <div className="absolute inset-0 bg-gradient-to-r from-black/70 via-black/40 to-transparent flex items-center">
                            <div className="px-6 lg:px-10 max-w-2xl text-white">
                                {b.titulo && <h2 className="text-2xl lg:text-3xl font-bold drop-shadow-lg">{b.titulo}</h2>}
                                {b.subtitulo && <p className="text-sm lg:text-base text-white/90 mt-2 drop-shadow">{b.subtitulo}</p>}
                                {b.link_url && (
                                    <a href={b.link_url} target="_blank" rel="noreferrer"
                                        className="inline-block mt-4 px-5 py-2.5 rounded-xl bg-white text-blue-700 font-bold text-sm hover:bg-blue-50 transition-colors shadow-md">
                                        {b.link_label || 'Saiba mais'}
                                        <i className="fas fa-arrow-right ml-2 text-xs" />
                                    </a>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            ))}

            {total > 1 && (
                <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-20">
                    {banners.map((_, i) => (
                        <button key={i} type="button" onClick={() => setIdx(i)}
                            aria-label={`Banner ${i + 1}`}
                            className={`h-2 rounded-full transition-all shadow-md ${i === idx ? 'w-8 bg-white' : 'w-2 bg-white/60 hover:bg-white/90'}`} />
                    ))}
                </div>
            )}
        </div>
    );
}

const COR_BG = {
    red:    'from-red-500 to-rose-600',
    blue:   'from-blue-500 to-indigo-600',
    amber:  'from-amber-500 to-orange-600',
    indigo: 'from-indigo-500 to-purple-600',
    orange: 'from-orange-500 to-red-600',
    green:  'from-green-500 to-blue-600',
    pink:   'from-pink-500 to-rose-600',
    cyan:   'from-cyan-500 to-blue-600',
};

const STATUS_CORES = {
    aberta:         'bg-blue-100 text-blue-700',
    em_atendimento: 'bg-amber-100 text-amber-700',
    atendida:       'bg-green-100 text-green-700',
    recusada:       'bg-red-100 text-red-700',
    cancelada:      'bg-gray-100 text-gray-600',
};

export default function PortalHome({ ug, categorias, maisAcessados, totalServicos, minhasSolicitacoes, contagemSolicitacoes, statusList }) {
    const { cidadao } = usePage().props;
    const totalMinhas = (minhasSolicitacoes || []).length;
    const totalAbertas = (contagemSolicitacoes?.aberta || 0) + (contagemSolicitacoes?.em_atendimento || 0);

    return (
        <PortalLayout ug={ug}>
            <Head title={`${ug.nome} — Carta de Servicos`} />

            {ug?.banners?.length > 0 && (
                <div className="mb-8 -mt-4">
                    <BannerCarrossel banners={ug.banners} />
                </div>
            )}

            {cidadao && totalMinhas > 0 && (
                <div className="mb-8">
                    <div className="flex items-center justify-between mb-3">
                        <div>
                            <h2 className="text-lg font-bold text-gray-800">Ola, {cidadao.nome.split(' ')[0]}!</h2>
                            <p className="text-sm text-gray-500">
                                {totalAbertas > 0
                                    ? <>Voce tem <strong className="text-blue-600">{totalAbertas}</strong> solicitacao(oes) em andamento</>
                                    : 'Suas solicitacoes recentes'}
                            </p>
                        </div>
                        <Link href="/minhas-solicitacoes"
                            className="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1">
                            Ver todas <i className="fas fa-arrow-right text-[10px]" />
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {minhasSolicitacoes.map(sol => (
                            <Link key={sol.id} href={`/minhas-solicitacoes/${sol.id}`}
                                className="bg-white rounded-xl border border-gray-200 p-4 hover:border-blue-200 hover:ring-2 hover:ring-blue-100 transition-all flex items-start gap-3 group">
                                <div className="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0">
                                    <i className={`${sol.servico?.icone || 'fas fa-file-alt'} text-sm`} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap mb-0.5">
                                        <p className="text-[10px] font-mono text-gray-400 uppercase tracking-wider">{sol.codigo}</p>
                                        <span className={`text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-semibold ${STATUS_CORES[sol.status]}`}>
                                            {statusList[sol.status]}
                                        </span>
                                    </div>
                                    <p className="text-sm font-semibold text-gray-800 group-hover:text-blue-700 truncate">{sol.servico?.titulo}</p>
                                    {sol.resposta ? (
                                        <p className="text-xs text-gray-600 mt-1 line-clamp-2 italic">
                                            <i className="fas fa-reply text-blue-500 mr-1" />
                                            {sol.resposta}
                                        </p>
                                    ) : (
                                        <p className="text-xs text-gray-500 mt-0.5 line-clamp-1">{sol.descricao}</p>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            <div className="mb-10">
                <div className="flex items-center justify-between mb-1">
                    <h2 className="text-xl font-bold text-gray-800">Categorias</h2>
                    <span className="text-xs text-gray-500">{totalServicos} servicos disponiveis</span>
                </div>
                <p className="text-sm text-gray-500">Navegue por area de interesse</p>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-12">
                {(categorias || []).map(cat => (
                    <Link
                        key={cat.id}
                        href={`/categoria/${cat.slug}`}
                        className="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-lg hover:border-transparent hover:ring-2 hover:ring-blue-200 transition-all group"
                    >
                        <div className={`w-14 h-14 bg-gradient-to-br ${COR_BG[cat.cor] || 'from-gray-500 to-gray-600'} rounded-xl flex items-center justify-center mb-3 shadow-md group-hover:scale-110 transition-transform`}>
                            <i className={`${cat.icone || 'fas fa-folder'} text-white text-xl`} />
                        </div>
                        <h3 className="text-sm font-bold text-gray-800 mb-1">{cat.nome}</h3>
                        <p className="text-[11px] text-gray-500 leading-relaxed line-clamp-2">{cat.descricao}</p>
                        <p className="text-[10px] text-blue-600 font-semibold mt-2 uppercase tracking-wide">
                            {cat.servicos_publicados_count || 0} servicos
                        </p>
                    </Link>
                ))}
            </div>

            {(maisAcessados || []).length > 0 && (
                <div className="mb-10">
                    <h2 className="text-xl font-bold text-gray-800 mb-1">Mais procurados</h2>
                    <p className="text-sm text-gray-500 mb-4">Servicos com maior numero de visualizacoes</p>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {maisAcessados.map(servico => (
                            <Link
                                key={servico.id}
                                href={`/servico/${servico.slug}`}
                                className="bg-white rounded-xl border border-gray-200 p-4 hover:border-blue-200 hover:ring-2 hover:ring-blue-100 hover:shadow-md transition-all flex items-start gap-3 group"
                            >
                                <div className="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0 group-hover:bg-blue-100">
                                    <i className={servico.icone || 'fas fa-file-alt'} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-gray-800 group-hover:text-blue-700">{servico.titulo}</p>
                                    <p className="text-xs text-gray-500 line-clamp-2 mt-0.5">{servico.descricao_curta}</p>
                                </div>
                                <i className="fas fa-arrow-right text-gray-300 group-hover:text-blue-600 mt-2" />
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </PortalLayout>
    );
}
