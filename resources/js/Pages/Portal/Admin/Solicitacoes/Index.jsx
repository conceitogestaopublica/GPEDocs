/**
 * Admin — Lista de solicitacoes do Portal Cidadao.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Card from '../../../../Components/Card';

const STATUS_CORES = {
    aberta:         'bg-blue-100 text-blue-700',
    em_atendimento: 'bg-amber-100 text-amber-700',
    atendida:       'bg-green-100 text-green-700',
    recusada:       'bg-red-100 text-red-700',
    cancelada:      'bg-gray-100 text-gray-600',
};

export default function SolicitacoesIndex({ solicitacoes, servicos, statusList, contagens, filtros }) {
    const [busca, setBusca] = useState(filtros?.q || '');
    const [filtroStatus, setFiltroStatus] = useState(filtros?.status || '');
    const [filtroServico, setFiltroServico] = useState(filtros?.servico_id || '');

    const aplicar = (e) => {
        e?.preventDefault();
        const params = {};
        if (busca) params.q = busca;
        if (filtroStatus) params.status = filtroStatus;
        if (filtroServico) params.servico_id = filtroServico;
        router.get('/configuracoes/solicitacoes-portal', params, { preserveState: true });
    };

    const limpar = () => {
        setBusca(''); setFiltroStatus(''); setFiltroServico('');
        router.get('/configuracoes/solicitacoes-portal');
    };

    const irParaStatus = (status) => {
        setFiltroStatus(status);
        router.get('/configuracoes/solicitacoes-portal', { status }, { preserveState: true });
    };

    return (
        <AdminLayout>
            <Head title="Solicitacoes do Portal" />

            <PageHeader
                title="Solicitacoes do Portal Cidadao"
                subtitle="Atendimento das solicitacoes feitas pelos cidadaos via Carta de Servicos"
            />

            {/* Stats por status */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
                {Object.entries(statusList).map(([key, label]) => (
                    <button
                        key={key}
                        onClick={() => irParaStatus(filtroStatus === key ? '' : key)}
                        className={`bg-white rounded-xl border p-4 text-left hover:shadow-md transition-all
                            ${filtroStatus === key ? 'border-blue-400 ring-2 ring-blue-200' : 'border-gray-200'}`}
                    >
                        <p className="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">{label}</p>
                        <p className="text-2xl font-bold text-gray-800 mt-1">{contagens[key] || 0}</p>
                    </button>
                ))}
            </div>

            {/* Filtros */}
            <form onSubmit={aplicar} className="bg-white rounded-2xl border border-gray-200 p-4 mb-4 grid grid-cols-1 md:grid-cols-12 gap-3">
                <div className="md:col-span-5 relative">
                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                    <input
                        type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                        placeholder="Buscar por codigo ou descricao..."
                        className="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                    />
                </div>
                <select value={filtroStatus} onChange={(e) => setFiltroStatus(e.target.value)}
                    className="md:col-span-2 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">Todos status</option>
                    {Object.entries(statusList).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <select value={filtroServico} onChange={(e) => setFiltroServico(e.target.value)}
                    className="md:col-span-3 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">Todos servicos</option>
                    {servicos.map(s => <option key={s.id} value={s.id}>{s.titulo}</option>)}
                </select>
                <div className="md:col-span-2 flex gap-2">
                    <button type="submit" className="flex-1 px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Filtrar</button>
                    <button type="button" onClick={limpar} className="px-3 py-2 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm" title="Limpar">
                        <i className="fas fa-times" />
                    </button>
                </div>
            </form>

            <Card padding={false}>
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th className="px-4 py-3 text-left font-semibold">Codigo / Servico</th>
                            <th className="px-4 py-3 text-left font-semibold">Cidadao</th>
                            <th className="px-4 py-3 text-center font-semibold">Status</th>
                            <th className="px-4 py-3 text-center font-semibold">Aberta em</th>
                            <th className="px-4 py-3 text-center font-semibold w-24">Acao</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {(solicitacoes.data || []).map(sol => (
                            <tr key={sol.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3">
                                    <p className="text-[10px] font-mono text-gray-400 uppercase tracking-wider">{sol.codigo}</p>
                                    <p className="font-semibold text-gray-800">{sol.servico?.titulo}</p>
                                    <p className="text-xs text-gray-500 line-clamp-1 max-w-md">{sol.descricao}</p>
                                </td>
                                <td className="px-4 py-3">
                                    <p className="text-sm font-medium text-gray-700">{sol.cidadao?.nome}</p>
                                    <p className="text-xs text-gray-500">{sol.cidadao?.email}</p>
                                    {sol.cidadao?.telefone && (
                                        <p className="text-xs text-gray-500"><i className="fas fa-phone text-[9px] mr-1" />{sol.cidadao.telefone}</p>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span className={`text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-semibold ${STATUS_CORES[sol.status]}`}>
                                        {statusList[sol.status]}
                                    </span>
                                    {sol.atendente && (
                                        <p className="text-[10px] text-gray-400 mt-1">por {sol.atendente.name}</p>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-center text-xs text-gray-500">
                                    {new Date(sol.created_at).toLocaleDateString('pt-BR')}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <Link href={`/configuracoes/solicitacoes-portal/${sol.id}`}
                                        className="inline-block px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-100">
                                        Atender
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {(solicitacoes.data || []).length === 0 && (
                            <tr><td colSpan={5} className="px-4 py-12 text-center text-gray-400">
                                <i className="fas fa-inbox text-3xl mb-2 block" />
                                Nenhuma solicitacao com os filtros aplicados.
                            </td></tr>
                        )}
                    </tbody>
                </table>
            </Card>

            {solicitacoes.last_page > 1 && (
                <div className="flex justify-center gap-1 mt-6">
                    {solicitacoes.links.map((link, i) => (
                        <button key={i} disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                                ${link.active ? 'bg-blue-600 text-white' :
                                  link.url ? 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' :
                                  'text-gray-300 cursor-default'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
