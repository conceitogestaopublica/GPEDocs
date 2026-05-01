/**
 * Listagem de Processos — GED
 *
 * Tabela com filtros, badges de status/prioridade e paginacao.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

const statusColors = {
    aberto: 'bg-blue-100 text-blue-700',
    em_tramitacao: 'bg-yellow-100 text-yellow-700',
    aguardando_assinatura: 'bg-purple-100 text-purple-700',
    concluido: 'bg-green-100 text-green-700',
    cancelado: 'bg-red-100 text-red-700',
    arquivado: 'bg-gray-100 text-gray-700',
};

const statusLabels = {
    aberto: 'Aberto',
    em_tramitacao: 'Em Tramitacao',
    aguardando_assinatura: 'Aguardando Assinatura',
    concluido: 'Concluido',
    cancelado: 'Cancelado',
    arquivado: 'Arquivado',
};

const prioridadeColors = {
    baixa: 'bg-gray-100 text-gray-600',
    normal: 'bg-blue-100 text-blue-600',
    alta: 'bg-orange-100 text-orange-600',
    urgente: 'bg-red-100 text-red-600',
};

const statusOptions = [
    { value: '', label: 'Todos os status' },
    { value: 'aberto', label: 'Aberto' },
    { value: 'em_tramitacao', label: 'Em Tramitacao' },
    { value: 'concluido', label: 'Concluido' },
    { value: 'cancelado', label: 'Cancelado' },
    { value: 'arquivado', label: 'Arquivado' },
];

const prioridadeOptions = [
    { value: '', label: 'Todas as prioridades' },
    { value: 'baixa', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'alta', label: 'Alta' },
    { value: 'urgente', label: 'Urgente' },
];

export default function Index({ processos, filters, tipos_processo }) {
    const f = filters || {};
    const [search, setSearch] = useState(f.search || '');
    const [tipoId, setTipoId] = useState(f.tipo_processo_id || '');
    const [status, setStatus] = useState(f.status || '');
    const [prioridade, setPrioridade] = useState(f.prioridade || '');

    const tipos = tipos_processo || [];
    const lista = processos?.data || [];

    const applyFilters = (overrides = {}) => {
        const params = {
            search,
            tipo_processo_id: tipoId,
            status,
            prioridade,
            ...overrides,
        };
        // Remove empty values
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        router.get('/processos', params, { preserveState: true, preserveScroll: true });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        applyFilters();
    };

    return (
        <AdminLayout>
            <Head title="Processos" />

            <PageHeader title="Processos" subtitle="Gerenciamento de processos administrativos">
                <Button href="/processos/create" icon="fas fa-plus">
                    Abrir Processo
                </Button>
            </PageHeader>

            {/* Filter bar */}
            <Card className="mb-6">
                <form onSubmit={handleSearch} className="flex flex-wrap items-end gap-3">
                    <div className="flex-1 min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Protocolo, assunto..."
                            className="ds-input"
                        />
                    </div>
                    <div className="w-48">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
                        <select
                            value={tipoId}
                            onChange={(e) => { setTipoId(e.target.value); applyFilters({ tipo_processo_id: e.target.value }); }}
                            className="ds-input"
                        >
                            <option value="">Todos os tipos</option>
                            {tipos.map((t) => (
                                <option key={t.id} value={t.id}>{t.nome}</option>
                            ))}
                        </select>
                    </div>
                    <div className="w-44">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select
                            value={status}
                            onChange={(e) => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                            className="ds-input"
                        >
                            {statusOptions.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="w-44">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Prioridade</label>
                        <select
                            value={prioridade}
                            onChange={(e) => { setPrioridade(e.target.value); applyFilters({ prioridade: e.target.value }); }}
                            className="ds-input"
                        >
                            {prioridadeOptions.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                    <button type="submit" className="ds-btn ds-btn-primary">
                        <i className="fas fa-search mr-1" />
                        Filtrar
                    </button>
                </form>
            </Card>

            {/* Table */}
            <Card padding={false}>
                {lista.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-folder-open text-3xl mb-3 block" />
                        <p className="text-sm font-medium">Nenhum processo encontrado</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 bg-gray-50">
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Protocolo</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Assunto</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Prioridade</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Aberto por</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Data</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {lista.map((proc) => (
                                    <tr key={proc.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="py-3 px-4">
                                            <Link href={`/processos/${proc.id}`} className="text-blue-600 hover:underline font-medium">
                                                {proc.numero_protocolo}
                                            </Link>
                                        </td>
                                        <td className="py-3 px-4 text-gray-700 max-w-xs truncate">{proc.assunto}</td>
                                        <td className="py-3 px-4 text-gray-500">{proc.tipo_processo?.nome || proc.tipo || '-'}</td>
                                        <td className="py-3 px-4">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[proc.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {statusLabels[proc.status] || proc.status}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${prioridadeColors[proc.prioridade] || 'bg-gray-100 text-gray-600'}`}>
                                                {proc.prioridade}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4 text-gray-500">{proc.aberto_por?.name || '-'}</td>
                                        <td className="py-3 px-4 text-gray-400 text-xs">{formatDate(proc.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {/* Pagination */}
            {processos?.links && (
                <div className="flex items-center justify-center gap-1 mt-6">
                    {processos.links.map((link, idx) => (
                        <Link
                            key={idx}
                            href={link.url || '#'}
                            className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : link.url
                                    ? 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
                                    : 'text-gray-300 cursor-not-allowed'
                            }`}
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
