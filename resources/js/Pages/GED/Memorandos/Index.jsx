/**
 * Memorandos — GED
 *
 * Listagem de memorandos internos com abas Recebidos/Enviados.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

export default function MemorandosIndex({ memorandos, filters, usuarios }) {
    const data = memorandos?.data || memorandos || [];
    const tipo = filters?.tipo || 'recebidos';
    const [search, setSearch] = useState(filters?.search || '');

    const unreadCount = tipo === 'recebidos'
        ? data.filter(m => !m.lido).length
        : 0;

    const doSearch = (e) => {
        e?.preventDefault();
        const params = { tipo };
        if (search) params.search = search;
        router.get('/memorandos', params, { preserveState: true });
    };

    const switchTab = (tab) => {
        router.get('/memorandos', { tipo: tab }, { preserveState: false });
    };

    return (
        <AdminLayout>
            <Head title="Memorandos" />
            <PageHeader
                title="Memorandos Internos"
                subtitle="Comunicacao interna via memorandos"
            >
                <Button icon="fas fa-plus" href="/memorandos/create">Novo Memorando</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {/* Abas */}
                <div className="flex border-b border-gray-200">
                    <button
                        onClick={() => switchTab('recebidos')}
                        className={`flex items-center gap-2 px-5 py-4 text-sm font-medium border-b-2 transition-colors
                            ${tipo === 'recebidos'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                    >
                        <i className="fas fa-inbox text-xs" />
                        Recebidos
                        {unreadCount > 0 && (
                            <span className="ml-1 px-1.5 py-0.5 text-[10px] font-bold bg-blue-600 text-white rounded-full">
                                {unreadCount}
                            </span>
                        )}
                    </button>
                    <button
                        onClick={() => switchTab('enviados')}
                        className={`flex items-center gap-2 px-5 py-4 text-sm font-medium border-b-2 transition-colors
                            ${tipo === 'enviados'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                    >
                        <i className="fas fa-paper-plane text-xs" />
                        Enviados
                    </button>
                </div>

                {/* Barra de pesquisa */}
                <div className="px-4 py-3 border-b border-gray-100">
                    <form onSubmit={doSearch} className="flex items-center gap-3">
                        <span className="text-sm text-gray-500 font-medium shrink-0">Filtrar Resultados:</span>
                        <div className="relative flex-1 max-w-sm">
                            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por assunto, numero..."
                                className="w-full pl-3 pr-8 py-2 rounded-lg border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                            <button type="submit" className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600">
                                <i className="fas fa-search text-xs" />
                            </button>
                        </div>
                    </form>
                </div>

                {/* Tabela */}
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold">Numero</th>
                                <th className="px-4 py-3 text-left font-semibold">Assunto</th>
                                <th className="px-4 py-3 text-left font-semibold">
                                    {tipo === 'recebidos' ? 'Remetente' : 'Destinatarios'}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold">Data</th>
                                <th className="px-4 py-3 text-left font-semibold">Status</th>
                                <th className="px-4 py-3 text-center font-semibold w-32">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-gray-400">
                                        <i className="fas fa-envelope-open text-3xl mb-2 block" />
                                        Nenhum memorando encontrado.
                                    </td>
                                </tr>
                            ) : data.map(memo => (
                                <tr key={memo.id}
                                    className={`hover:bg-gray-50 transition-colors ${tipo === 'recebidos' && !memo.lido ? 'bg-blue-50/30' : ''}`}>
                                    {/* Numero */}
                                    <td className="px-4 py-3">
                                        <Link href={`/memorandos/${memo.id}`}
                                            className={`hover:text-blue-600 ${tipo === 'recebidos' && !memo.lido ? 'font-bold text-gray-900' : 'text-blue-600'}`}>
                                            <div className="flex items-center gap-2">
                                                {tipo === 'recebidos' && !memo.lido && (
                                                    <span className="w-2 h-2 rounded-full bg-blue-500 shrink-0" />
                                                )}
                                                {memo.numero}
                                            </div>
                                        </Link>
                                    </td>
                                    {/* Assunto */}
                                    <td className="px-4 py-3">
                                        <span className={`${tipo === 'recebidos' && !memo.lido ? 'font-semibold text-gray-900' : 'text-gray-700'}`}>
                                            {memo.assunto}
                                        </span>
                                        {memo.confidencial && (
                                            <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-600 font-medium">
                                                <i className="fas fa-lock mr-0.5" />Confidencial
                                            </span>
                                        )}
                                    </td>
                                    {/* Remetente / Destinatarios */}
                                    <td className="px-4 py-3 text-gray-600 text-xs">
                                        {tipo === 'recebidos' ? (
                                            <span>{memo.remetente?.name || '-'}</span>
                                        ) : (
                                            <span className="truncate max-w-[200px] block">
                                                {(memo.destinatarios || []).map(d => d.user?.name || d.name).join(', ') || '-'}
                                            </span>
                                        )}
                                    </td>
                                    {/* Data */}
                                    <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                                        {memo.created_at ? new Date(memo.created_at).toLocaleDateString('pt-BR') : '-'}
                                    </td>
                                    {/* Status */}
                                    <td className="px-4 py-3">
                                        {tipo === 'recebidos' ? (
                                            memo.lido ? (
                                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">
                                                    <i className="fas fa-check mr-0.5" />Lido
                                                </span>
                                            ) : (
                                                <span className="text-[10px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
                                                    <i className="fas fa-envelope mr-0.5" />Nao lido
                                                </span>
                                            )
                                        ) : (
                                            <ReadStatus destinatarios={memo.destinatarios || []} />
                                        )}
                                    </td>
                                    {/* Acoes */}
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-center gap-0.5">
                                            <ActionBtn icon="fas fa-eye" title="Ver"
                                                onClick={() => router.visit(`/memorandos/${memo.id}`)} />
                                            <ActionBtn icon="fas fa-archive" title="Arquivar"
                                                onClick={() => {
                                                    if (confirm('Arquivar este memorando?'))
                                                        router.post(`/memorandos/${memo.id}/arquivar`, {}, { preserveState: true, preserveScroll: true });
                                                }} />
                                            <ActionBtn icon="fas fa-file-pdf" title="PDF"
                                                onClick={() => window.open(`/memorandos/${memo.id}/pdf`, '_blank')} />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Paginacao */}
                {memorandos?.links && memorandos.last_page > 1 && (
                    <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                        <span className="text-xs text-gray-500">
                            Mostrando {memorandos.from}-{memorandos.to} de {memorandos.total}
                        </span>
                        <div className="flex gap-1">
                            {memorandos.links.map((link, i) => (
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

/* ── Read Status (enviados) ── */
function ReadStatus({ destinatarios }) {
    const total = destinatarios.length;
    const lidos = destinatarios.filter(d => d.lido || d.pivot?.lido).length;

    if (total === 0) return <span className="text-xs text-gray-400">-</span>;

    const allRead = lidos === total;

    return (
        <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium
            ${allRead ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}>
            <i className={`fas ${allRead ? 'fa-check-double' : 'fa-clock'} mr-0.5`} />
            {lidos} de {total} lido(s)
        </span>
    );
}

/* ── Action Button ── */
function ActionBtn({ icon, title, onClick }) {
    return (
        <button onClick={onClick} title={title}
            className="w-7 h-7 rounded flex items-center justify-center transition-colors text-gray-400 hover:text-blue-600 hover:bg-blue-50">
            <i className={`${icon} text-[11px]`} />
        </button>
    );
}
