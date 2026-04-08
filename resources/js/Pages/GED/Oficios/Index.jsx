/**
 * Oficios Eletronicos — GED
 *
 * Listagem de oficios enviados para destinatarios externos.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

const STATUS_MAP = {
    rascunho:   { label: 'Rascunho',   color: 'bg-gray-100 text-gray-600',   icon: 'fas fa-edit',        desc: 'Em elaboracao' },
    enviado:    { label: 'Enviado',     color: 'bg-blue-100 text-blue-700',   icon: 'fas fa-paper-plane', desc: 'Aguardando entrega' },
    entregue:   { label: 'Entregue',    color: 'bg-yellow-100 text-yellow-700', icon: 'fas fa-envelope',  desc: 'E-mail enviado' },
    lido:       { label: 'Lido',        color: 'bg-green-100 text-green-700', icon: 'fas fa-eye',         desc: 'Destinatario abriu' },
    respondido: { label: 'Respondido',  color: 'bg-purple-100 text-purple-700', icon: 'fas fa-reply',     desc: 'Tem resposta' },
    arquivado:  { label: 'Arquivado',   color: 'bg-gray-100 text-gray-500',  icon: 'fas fa-archive',     desc: 'Arquivado' },
};

export default function OficiosIndex({ oficios, filters }) {
    const data = oficios?.data || oficios || [];
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');

    const doSearch = (e) => {
        e?.preventDefault();
        const params = {};
        if (search) params.search = search;
        if (statusFilter) params.status = statusFilter;
        router.get('/oficios', params, { preserveState: true });
    };

    const filterByStatus = (status) => {
        setStatusFilter(status);
        const params = {};
        if (search) params.search = search;
        if (status) params.status = status;
        router.get('/oficios', params, { preserveState: false });
    };

    return (
        <AdminLayout>
            <Head title="Oficios Eletronicos" />
            <PageHeader
                title="Oficios Eletronicos"
                subtitle="Envio de documentos oficiais para destinatarios externos"
            >
                <Button icon="fas fa-plus" href="/oficios/create">Novo Oficio</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {/* Filtros de status */}
                <div className="flex border-b border-gray-200 overflow-x-auto">
                    <button
                        onClick={() => filterByStatus('')}
                        className={`flex items-center gap-2 px-5 py-4 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                            ${!statusFilter
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                    >
                        <i className="fas fa-list text-xs" />
                        Todos
                    </button>
                    {Object.entries(STATUS_MAP).filter(([k]) => k !== 'rascunho').map(([key, cfg]) => (
                        <button
                            key={key}
                            onClick={() => filterByStatus(key)}
                            className={`flex items-center gap-2 px-5 py-4 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                                ${statusFilter === key
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                        >
                            <i className={`${cfg.icon} text-xs`} />
                            {cfg.label}
                        </button>
                    ))}
                </div>

                {/* Barra de pesquisa */}
                <div className="px-4 py-3 border-b border-gray-100">
                    <form onSubmit={doSearch} className="flex items-center gap-3">
                        <span className="text-sm text-gray-500 font-medium shrink-0">Filtrar Resultados:</span>
                        <div className="relative flex-1 max-w-sm">
                            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por assunto, numero, destinatario..."
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
                                <th className="px-4 py-3 text-left font-semibold">Destinatario</th>
                                <th className="px-4 py-3 text-left font-semibold">Status</th>
                                <th className="px-4 py-3 text-left font-semibold">Data Envio</th>
                                <th className="px-4 py-3 text-center font-semibold w-32">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-gray-400">
                                        <i className="fas fa-file-alt text-3xl mb-2 block" />
                                        Nenhum oficio encontrado.
                                    </td>
                                </tr>
                            ) : data.map(oficio => {
                                const st = STATUS_MAP[oficio.status] || STATUS_MAP.enviado;
                                return (
                                    <tr key={oficio.id} className="hover:bg-gray-50 transition-colors">
                                        {/* Numero */}
                                        <td className="px-4 py-3">
                                            <Link href={`/oficios/${oficio.id}`}
                                                className="text-blue-600 hover:text-blue-800 font-medium">
                                                {oficio.numero}
                                            </Link>
                                        </td>
                                        {/* Assunto */}
                                        <td className="px-4 py-3 text-gray-700">
                                            {oficio.assunto}
                                        </td>
                                        {/* Destinatario */}
                                        <td className="px-4 py-3">
                                            <div className="text-sm text-gray-700">{oficio.destinatario_nome}</div>
                                            {oficio.destinatario_orgao && (
                                                <div className="text-[10px] text-gray-400">{oficio.destinatario_orgao}</div>
                                            )}
                                        </td>
                                        {/* Status */}
                                        <td className="px-4 py-3">
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${st.color}`}
                                                title={st.desc}>
                                                <i className={`${st.icon} mr-0.5`} />
                                                {st.label}
                                            </span>
                                        </td>
                                        {/* Data Envio */}
                                        <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                                            {oficio.enviado_em
                                                ? new Date(oficio.enviado_em).toLocaleDateString('pt-BR', {
                                                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                                                })
                                                : '-'}
                                        </td>
                                        {/* Acoes */}
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-center gap-0.5">
                                                <ActionBtn icon="fas fa-eye" title="Ver"
                                                    onClick={() => router.visit(`/oficios/${oficio.id}`)} />
                                                {oficio.status !== 'arquivado' && (
                                                    <ActionBtn icon="fas fa-archive" title="Arquivar"
                                                        onClick={() => {
                                                            if (confirm('Arquivar este oficio?'))
                                                                router.post(`/oficios/${oficio.id}/arquivar`, {}, { preserveState: true, preserveScroll: true });
                                                        }} />
                                                )}
                                                <ActionBtn icon="fas fa-file-pdf" title="PDF"
                                                    onClick={() => window.open(`/oficios/${oficio.id}/pdf`, '_blank')} />
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Paginacao */}
                {oficios?.links && oficios.last_page > 1 && (
                    <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                        <span className="text-xs text-gray-500">
                            Mostrando {oficios.from}-{oficios.to} de {oficios.total}
                        </span>
                        <div className="flex gap-1">
                            {oficios.links.map((link, i) => (
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

/* ── Action Button ── */
function ActionBtn({ icon, title, onClick }) {
    return (
        <button onClick={onClick} title={title}
            className="w-7 h-7 rounded flex items-center justify-center transition-colors text-gray-400 hover:text-blue-600 hover:bg-blue-50">
            <i className={`${icon} text-[11px]`} />
        </button>
    );
}
