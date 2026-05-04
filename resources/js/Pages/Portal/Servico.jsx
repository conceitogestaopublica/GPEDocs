/**
 * Portal — Detalhe de um servico.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';

const COR_BG = {
    red: 'bg-red-100 text-red-600', blue: 'bg-blue-100 text-blue-600',
    amber: 'bg-amber-100 text-amber-600', indigo: 'bg-indigo-100 text-indigo-600',
    orange: 'bg-orange-100 text-orange-600', green: 'bg-green-100 text-green-600',
    pink: 'bg-pink-100 text-pink-600', cyan: 'bg-cyan-100 text-cyan-600',
};

function CanaisLista({ canais }) {
    if (!canais || Object.keys(canais).length === 0) {
        return <p className="text-sm text-gray-500">Nao informado.</p>;
    }
    const items = [];
    if (canais.online) items.push({ icon: 'fas fa-globe', label: 'Online' });
    if (canais.presencial) items.push({ icon: 'fas fa-map-marker-alt', label: 'Presencial' });
    if (canais.telefone) items.push({ icon: 'fas fa-phone', label: canais.telefone });
    if (canais.app) items.push({ icon: 'fas fa-mobile-alt', label: canais.app });

    return (
        <ul className="space-y-2">
            {items.map((it, i) => (
                <li key={i} className="flex items-center gap-2 text-sm text-gray-700">
                    <i className={`${it.icon} text-blue-600 w-4 text-center`} />
                    <span>{it.label}</span>
                </li>
            ))}
            {canais.observacoes && (
                <li className="text-xs text-gray-500 mt-2 italic border-t pt-2">{canais.observacoes}</li>
            )}
        </ul>
    );
}

export default function PortalServico({ ug, servico, relacionados, publicos }) {
    const cat = servico.categoria;
    const corCat = cat ? COR_BG[cat.cor] || 'bg-gray-100 text-gray-600' : 'bg-blue-100 text-blue-600';
    const { cidadao } = usePage().props;

    return (
        <PortalLayout ug={ug} hideSearchBar>
            <Head title={`${servico.titulo} — ${ug.nome}`} />

            {/* Breadcrumb */}
            <nav className="text-xs text-gray-500 mb-4">
                <Link href="/" className="hover:text-blue-600">Inicio</Link>
                <i className="fas fa-chevron-right mx-2 text-[10px]" />
                {cat && (
                    <>
                        <Link href={`/categoria/${cat.slug}`} className="hover:text-blue-600">{cat.nome}</Link>
                        <i className="fas fa-chevron-right mx-2 text-[10px]" />
                    </>
                )}
                <span className="text-gray-700 font-medium">{servico.titulo}</span>
            </nav>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Conteudo principal */}
                <article className="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6 lg:p-8">
                    <div className="flex items-start gap-4 mb-6 pb-6 border-b border-gray-100">
                        <div className={`w-16 h-16 rounded-2xl flex items-center justify-center shrink-0 ${corCat}`}>
                            <i className={`${servico.icone || 'fas fa-file-alt'} text-2xl`} />
                        </div>
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                {cat && (
                                    <Link href={`/categoria/${cat.slug}`}
                                        className="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full font-semibold hover:bg-blue-100">
                                        {cat.nome}
                                    </Link>
                                )}
                                <span className="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full font-semibold">
                                    Para {publicos[servico.publico_alvo] || servico.publico_alvo}
                                </span>
                            </div>
                            <h1 className="text-2xl font-bold text-gray-800 leading-tight">{servico.titulo}</h1>
                            {servico.descricao_curta && (
                                <p className="text-sm text-gray-600 mt-2">{servico.descricao_curta}</p>
                            )}
                        </div>
                    </div>

                    {servico.descricao_completa && (
                        <Section titulo="Sobre o servico" icon="fas fa-info-circle">
                            <p className="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{servico.descricao_completa}</p>
                        </Section>
                    )}

                    {servico.requisitos && (
                        <Section titulo="Quem pode solicitar" icon="fas fa-user-check">
                            <p className="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{servico.requisitos}</p>
                        </Section>
                    )}

                    {Array.isArray(servico.documentos_necessarios) && servico.documentos_necessarios.length > 0 && (
                        <Section titulo="Documentos necessarios" icon="fas fa-folder">
                            <ul className="space-y-2">
                                {servico.documentos_necessarios.map((doc, i) => (
                                    <li key={i} className="flex items-start gap-2 text-sm text-gray-700">
                                        <i className="fas fa-check-circle text-blue-600 mt-0.5 shrink-0" />
                                        <span>{doc}</span>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}

                    {servico.legislacao && (
                        <Section titulo="Legislacao de referencia" icon="fas fa-balance-scale">
                            <p className="text-sm text-gray-700 whitespace-pre-line italic">{servico.legislacao}</p>
                        </Section>
                    )}
                </article>

                {/* Sidebar */}
                <aside className="space-y-4">
                    {/* CTA — solicitar este servico */}
                    <div className="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-5 text-white shadow-lg">
                        {servico.permite_anonimo && (
                            <div className="inline-flex items-center gap-1.5 px-2 py-0.5 bg-white/20 rounded-full text-[10px] font-bold uppercase tracking-wider mb-2">
                                <i className="fas fa-user-secret" />
                                Aceita anonimo
                            </div>
                        )}
                        <h3 className="text-sm font-bold mb-1">Solicite este servico online</h3>
                        <p className="text-xs text-blue-100 mb-3">
                            {servico.permite_anonimo
                                ? 'Voce pode se identificar para receber atualizacoes ou solicitar anonimamente.'
                                : 'Acompanhe o andamento e receba atualizacoes por email.'}
                        </p>
                        {cidadao ? (
                            <Link href={`/servico/${servico.slug}/solicitar`}
                                className="block text-center px-4 py-2.5 rounded-xl bg-white text-blue-700 font-bold text-sm hover:bg-blue-50 transition-colors">
                                <i className="fas fa-paper-plane mr-2" />
                                Solicitar agora
                            </Link>
                        ) : servico.permite_anonimo ? (
                            <div className="space-y-2">
                                <Link href={`/servico/${servico.slug}/solicitar`}
                                    className="block text-center px-4 py-2.5 rounded-xl bg-white text-blue-700 font-bold text-sm hover:bg-blue-50">
                                    <i className="fas fa-user-secret mr-2" />
                                    Solicitar anonimamente
                                </Link>
                                <Link href="/entrar"
                                    className="block text-center px-4 py-2 rounded-xl border-2 border-white/40 text-white text-xs font-semibold hover:bg-white/10">
                                    Entrar para ser notificado
                                </Link>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <Link href="/entrar"
                                    className="block text-center px-4 py-2.5 rounded-xl bg-white text-blue-700 font-bold text-sm hover:bg-blue-50">
                                    Entrar
                                </Link>
                                <Link href="/cadastrar"
                                    className="block text-center px-4 py-2 rounded-xl border-2 border-white/40 text-white text-xs font-semibold hover:bg-white/10">
                                    Nao tenho cadastro
                                </Link>
                            </div>
                        )}
                    </div>

                    <div className="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Resumo</h3>
                        <div className="space-y-3">
                            <Resumo icon="fas fa-clock" titulo="Prazo" valor={servico.prazo_entrega || 'Nao informado'} />
                            <Resumo icon="fas fa-dollar-sign" titulo="Custo" valor={servico.custo || 'Gratuito'} highlight={!servico.custo || /gratuito|sem custo/i.test(servico.custo)} />
                            {servico.orgao_responsavel && (
                                <Resumo icon="fas fa-building" titulo="Orgao responsavel" valor={servico.orgao_responsavel} />
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Como solicitar</h3>
                        <CanaisLista canais={servico.canais} />
                    </div>

                    {Array.isArray(servico.palavras_chave) && servico.palavras_chave.length > 0 && (
                        <div className="bg-white rounded-2xl border border-gray-200 p-5">
                            <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Tags</h3>
                            <div className="flex flex-wrap gap-1.5">
                                {servico.palavras_chave.map((tag, i) => (
                                    <span key={i} className="text-[11px] px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full">
                                        #{tag}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}
                </aside>
            </div>

            {(relacionados || []).length > 0 && (
                <div className="mt-10">
                    <h2 className="text-lg font-bold text-gray-800 mb-3">Servicos relacionados</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {relacionados.map(rel => (
                            <Link
                                key={rel.id}
                                href={`/servico/${rel.slug}`}
                                className="bg-white rounded-xl border border-gray-200 p-4 hover:border-blue-200 hover:ring-2 hover:ring-blue-100 transition-all flex items-start gap-3 group"
                            >
                                <div className="w-9 h-9 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0">
                                    <i className={`${rel.icone || 'fas fa-file-alt'} text-sm`} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-gray-800 group-hover:text-blue-700">{rel.titulo}</p>
                                    <p className="text-xs text-gray-500 line-clamp-1 mt-0.5">{rel.descricao_curta}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </PortalLayout>
    );
}

function Section({ titulo, icon, children }) {
    return (
        <section className="mb-6 last:mb-0">
            <h2 className="text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                <i className={`${icon} text-blue-600`} />
                {titulo}
            </h2>
            <div className="pl-6">{children}</div>
        </section>
    );
}

function Resumo({ icon, titulo, valor, highlight }) {
    return (
        <div className="flex items-start gap-3">
            <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${highlight ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500'}`}>
                <i className={`${icon} text-xs`} />
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">{titulo}</p>
                <p className={`text-sm font-medium ${highlight ? 'text-blue-700' : 'text-gray-800'}`}>{valor}</p>
            </div>
        </div>
    );
}
