/**
 * Notificações do usuário — lista completa do sino, com filtro por categoria (TR 4.11.3).
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Button from '../../Components/Button';

export default function NotificacoesIndex({ notificacoes, filtros, categorias = [] }) {
    const itens = notificacoes?.data || [];
    const naoLidas = filtros?.nao_lidas;
    const categoria = filtros?.categoria || '';
    const icone = Object.fromEntries(categorias.map((c) => [c.chave, c.icone]));
    const url = (mudar) => {
        const q = { nao_lidas: naoLidas ? 1 : undefined, categoria: categoria || undefined, ...mudar };
        const s = new URLSearchParams(Object.entries(q).filter(([, v]) => v)).toString();
        return `/notificacoes${s ? `?${s}` : ''}`;
    };

    return (
        <AdminLayout>
            <Head title="Notificações" />
            <PageHeader title="Notificações" subtitle="Avisos de tramitações, assinaturas e comunicações">
                <Button variant="secondary" icon="fas fa-check-double"
                    onClick={() => router.post('/notificacoes/marcar-todas', {}, { preserveScroll: true })}>
                    Marcar todas como lidas
                </Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                    <Link href={url({ nao_lidas: undefined })} preserveScroll
                        className={`text-xs px-3 py-1.5 rounded-lg ${!naoLidas ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                        Todas
                    </Link>
                    <Link href={url({ nao_lidas: 1 })} preserveScroll
                        className={`text-xs px-3 py-1.5 rounded-lg ${naoLidas ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                        Não lidas
                    </Link>
                </div>
                {categorias.length > 0 && (
                    <div className="px-4 py-2 border-b border-gray-100 flex flex-wrap items-center gap-1.5">
                        <Link href={url({ categoria: undefined })} preserveScroll
                            className={`text-xs px-2.5 py-1 rounded-full border ${! categoria ? 'border-cyan-600 bg-cyan-50 text-cyan-800' : 'border-gray-200 text-gray-600 hover:bg-gray-50'}`}>Todas as categorias</Link>
                        {categorias.map((c) => (
                            <Link key={c.chave} href={url({ categoria: c.chave })} preserveScroll
                                className={`text-xs px-2.5 py-1 rounded-full border ${categoria === c.chave ? 'border-cyan-600 bg-cyan-50 text-cyan-800' : 'border-gray-200 text-gray-600 hover:bg-gray-50'}`}>
                                <i className={`fas ${c.icone} mr-1`} />{c.rotulo}
                                {c.nao_lidas > 0 && <span className="ml-1 text-[10px] font-bold bg-blue-600 text-white rounded-full px-1.5">{c.nao_lidas}</span>}
                            </Link>
                        ))}
                    </div>
                )}

                {itens.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-bell-slash text-3xl mb-3 block" />
                        <p className="text-sm">{naoLidas ? 'Nenhuma notificação não lida' : 'Nenhuma notificação'}</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {itens.map((n) => (
                            <li key={n.id} className={`px-4 py-3 flex items-start gap-3 ${n.lida ? '' : 'bg-blue-50/40'}`}>
                                <span className={`mt-0.5 w-7 h-7 rounded-full shrink-0 flex items-center justify-center text-xs ${n.lida ? 'bg-gray-100 text-gray-400' : 'bg-blue-100 text-blue-600'}`}>
                                    <i className={`fas ${icone[n.categoria] || 'fa-bell'}`} />
                                </span>
                                <div className="flex-1 min-w-0">
                                    <p className={`text-sm ${n.lida ? 'text-gray-700' : 'text-gray-900 font-semibold'}`}>
                                        {n.urgente && <span className="mr-1.5 text-[9px] px-1.5 py-0.5 rounded bg-red-600 text-white font-bold align-middle">URGENTE</span>}
                                        {n.titulo}
                                        {n.setor && <span className="ml-1.5 text-[10px] font-normal text-gray-400">· para o seu setor</span>}
                                    </p>
                                    {n.mensagem && <p className="text-xs text-gray-500 mt-0.5">{n.mensagem}</p>}
                                    <p className="text-[11px] text-gray-400 mt-1">{formatarData(n.created_at)}</p>
                                </div>
                                <div className="flex items-center gap-3 shrink-0">
                                    {n.tem_link && (
                                        <Link href={`/notificacoes/${n.id}/abrir`} className="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                            Abrir
                                        </Link>
                                    )}
                                    {!n.lida && (
                                        <button onClick={() => router.post(`/notificacoes/${n.id}/lida`, {}, { preserveScroll: true })}
                                            className="text-xs text-gray-500 hover:text-gray-700">
                                            Marcar como lida
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {notificacoes?.links && notificacoes.last_page > 1 && (
                    <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                        <span className="text-xs text-gray-500">
                            Mostrando {notificacoes.from}-{notificacoes.to} de {notificacoes.total}
                        </span>
                        <div className="flex gap-1">
                            {notificacoes.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'} preserveScroll
                                    className={`px-3 py-1.5 text-xs rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white border text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

function formatarData(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
