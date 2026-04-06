/**
 * Caixa de Entrada — Processos GED
 *
 * Lista de tramitacoes pendentes do usuario logado.
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';

function getSlaInfo(prazo) {
    if (!prazo) return { label: 'Sem prazo', color: 'bg-gray-100 text-gray-500', rowTint: '' };

    const now = new Date();
    const deadline = new Date(prazo);
    const diffMs = deadline - now;
    const diffHours = diffMs / (1000 * 60 * 60);

    if (diffMs < 0) {
        return { label: 'Atrasado', color: 'bg-red-100 text-red-700', rowTint: 'bg-red-50' };
    }
    if (diffHours <= 24) {
        return { label: 'Expirando', color: 'bg-yellow-100 text-yellow-700', rowTint: 'bg-yellow-50' };
    }
    return { label: 'No prazo', color: 'bg-green-100 text-green-700', rowTint: '' };
}

export default function Inbox({ tramitacoes }) {
    const lista = tramitacoes?.data || tramitacoes || [];

    const handleReceber = (id) => {
        router.post(`/tramitacoes/${id}/receber`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Caixa de Entrada" />

            <PageHeader title="Caixa de Entrada" subtitle="Processos aguardando sua acao">
                <Button variant="secondary" href="/processos" icon="fas fa-arrow-left">
                    Voltar
                </Button>
            </PageHeader>

            <Card padding={false}>
                {lista.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-inbox text-4xl mb-3 block" />
                        <p className="text-sm font-medium">Caixa de entrada vazia</p>
                        <p className="text-xs mt-1">Nenhum processo aguardando sua acao</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 bg-gray-50">
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Protocolo</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Assunto</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Remetente</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Recebido em</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Prazo</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">SLA</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Acoes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {lista.map((tram) => {
                                    const sla = getSlaInfo(tram.prazo);
                                    const processo = tram.processo || {};
                                    return (
                                        <tr key={tram.id} className={`hover:bg-gray-50 transition-colors ${sla.rowTint}`}>
                                            <td className="py-3 px-4">
                                                <Link
                                                    href={`/processos/${tram.processo_id || processo.id}`}
                                                    className="text-blue-600 hover:underline font-medium"
                                                >
                                                    {processo.numero_protocolo || '-'}
                                                </Link>
                                            </td>
                                            <td className="py-3 px-4 text-gray-700 max-w-xs truncate">
                                                {processo.assunto || '-'}
                                            </td>
                                            <td className="py-3 px-4 text-gray-500">
                                                {processo.tipo_processo?.nome || processo.tipo || '-'}
                                            </td>
                                            <td className="py-3 px-4 text-gray-600">
                                                {tram.remetente?.name || tram.remetente_nome || '-'}
                                            </td>
                                            <td className="py-3 px-4 text-gray-400 text-xs">
                                                {formatDate(tram.recebido_em || tram.created_at)}
                                            </td>
                                            <td className="py-3 px-4 text-gray-400 text-xs">
                                                {tram.prazo ? formatDate(tram.prazo) : '-'}
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${sla.color}`}>
                                                    {sla.label}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center justify-end gap-2">
                                                    {tram.status === 'pendente' && (
                                                        <button
                                                            onClick={() => handleReceber(tram.id)}
                                                            className="ds-btn ds-btn-accent ds-btn-sm"
                                                            title="Receber"
                                                        >
                                                            <i className="fas fa-check mr-1" />
                                                            Receber
                                                        </button>
                                                    )}
                                                    <Link
                                                        href={`/processos/${tram.processo_id || processo.id}`}
                                                        className="ds-btn ds-btn-outline ds-btn-sm"
                                                        title="Despachar"
                                                    >
                                                        <i className="fas fa-paper-plane mr-1" />
                                                        Despachar
                                                    </Link>
                                                    <Link
                                                        href={`/processos/${tram.processo_id || processo.id}`}
                                                        className="ds-btn ds-btn-ghost ds-btn-sm"
                                                        title="Ver"
                                                    >
                                                        <i className="fas fa-eye" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {/* Pagination */}
            {tramitacoes?.links && (
                <div className="flex items-center justify-center gap-1 mt-6">
                    {tramitacoes.links.map((link, idx) => (
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
