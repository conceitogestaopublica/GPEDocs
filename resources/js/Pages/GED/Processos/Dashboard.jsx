/**
 * Dashboard de Processos — GED
 *
 * Visao geral: estatisticas, caixa de entrada e processos recentes.
 */
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import StatCard from '../../../Components/StatCard';
import Card from '../../../Components/Card';

const statusColors = {
    aberto: 'bg-blue-100 text-blue-700',
    em_tramitacao: 'bg-yellow-100 text-yellow-700',
    concluido: 'bg-green-100 text-green-700',
    cancelado: 'bg-red-100 text-red-700',
    arquivado: 'bg-gray-100 text-gray-700',
};

const statusLabels = {
    aberto: 'Aberto',
    em_tramitacao: 'Em Tramitacao',
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

export default function Dashboard({ stats, processos_recentes, inbox_count }) {
    const recentes = processos_recentes || [];
    const s = stats || {};
    const lista = recentes || [];

    return (
        <AdminLayout>
            <Head title="Processos - Dashboard" />

            <PageHeader title="Processos" subtitle="Visao geral do modulo de processos" />

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                <StatCard title="Abertos" value={s.total_abertos || s.abertos || 0} icon="fas fa-folder-open" color="blue" />
                <StatCard title="Em Tramitacao" value={s.em_tramitacao || 0} icon="fas fa-exchange-alt" color="yellow" />
                <StatCard title="Concluidos no Mes" value={s.concluidos_mes || 0} icon="fas fa-check-circle" color="green" />
                <StatCard title="Atrasados" value={s.atrasados || 0} icon="fas fa-exclamation-triangle" color="red" />
            </div>

            {/* Caixa de Entrada */}
            <div className="mb-6">
                <Link
                    href="/processos/inbox"
                    className="flex items-center gap-4 bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-blue-200 transition-all group"
                >
                    <div className="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                        <i className="fas fa-inbox text-blue-600 group-hover:text-white transition-colors" />
                    </div>
                    <div className="flex-1">
                        <p className="text-sm font-semibold text-gray-800">Minha Caixa de Entrada</p>
                        <p className="text-xs text-gray-400">Processos aguardando sua acao</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {(inbox_count || s.inbox_count || 0) > 0 && (
                            <span className="bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                {inbox_count}
                            </span>
                        )}
                        <i className="fas fa-chevron-right text-gray-300 group-hover:text-blue-400 transition-colors" />
                    </div>
                </Link>
            </div>

            {/* Processos Recentes */}
            <Card title="Processos Recentes" subtitle="Ultimos 10 processos">
                {lista.length === 0 ? (
                    <div className="py-8 text-center text-gray-400">
                        <i className="fas fa-folder text-2xl mb-2 block" />
                        <p className="text-sm">Nenhum processo encontrado</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Protocolo</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Assunto</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Prioridade</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Data</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {lista.map((proc) => (
                                    <tr key={proc.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="py-3 px-4">
                                            <Link href={`/processos/${proc.id}`} className="text-blue-600 hover:underline font-medium">
                                                {proc.numero_protocolo}
                                            </Link>
                                        </td>
                                        <td className="py-3 px-4 text-gray-700 max-w-xs truncate">{proc.assunto}</td>
                                        <td className="py-3 px-4 text-gray-500">{proc.tipo?.nome || proc.tipo}</td>
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
                                        <td className="py-3 px-4 text-gray-400 text-xs">{formatDate(proc.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </AdminLayout>
    );
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
