/**
 * Notificações do usuário — lista completa do sino do cabeçalho.
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Button from '../../Components/Button';
import ItemNotificacao from '../../Components/ItemNotificacao';

export default function NotificacoesIndex({ notificacoes, filtros }) {
    const itens = notificacoes?.data || [];
    const naoLidas = filtros?.nao_lidas;

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
                    <Link href="/notificacoes" preserveScroll
                        className={`text-xs px-3 py-1.5 rounded-lg ${!naoLidas ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                        Todas
                    </Link>
                    <Link href="/notificacoes?nao_lidas=1" preserveScroll
                        className={`text-xs px-3 py-1.5 rounded-lg ${naoLidas ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                        Não lidas
                    </Link>
                </div>

                {itens.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-bell-slash text-3xl mb-3 block" />
                        <p className="text-sm">{naoLidas ? 'Nenhuma notificação não lida' : 'Nenhuma notificação'}</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {itens.map((n) => (
                            <li key={n.id} className={`px-4 py-3 flex items-start gap-3 transition-colors ${n.lida ? '' : 'bg-primary-50/40'} ${n.href ? 'hover:bg-gray-50' : ''}`}>
                                {/* Com assunto, o conteúdo inteiro é o link: marca como lida e leva até ele. */}
                                <ItemNotificacao n={n} quando={formatarData(n.created_at)} className="flex-1 min-w-0" />
                                <div className="flex items-center gap-3 shrink-0">
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
