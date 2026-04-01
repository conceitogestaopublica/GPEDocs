/**
 * Dashboard — GED
 *
 * Visao geral: estatisticas, atividade recente, fluxos pendentes, acesso rapido.
 */
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import StatCard from '../../../Components/StatCard';
import Card from '../../../Components/Card';

export default function Dashboard({ stats, atividade_recente, fluxos_pendentes }) {
    const s = stats || {};

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            {/* Header */}
            <div className="ds-page-header">
                <div>
                    <h1>Dashboard</h1>
                    <p>Visao geral do sistema de gestao documental</p>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                <StatCard title="Total de Documentos" value={s.total_documentos || 0} icon="fas fa-file-alt" color="blue" />
                <StatCard title="Pendentes de Revisao" value={s.pendentes_revisao || 0} icon="fas fa-clock" color="yellow" />
                <StatCard title="Fluxos Ativos" value={s.fluxos_ativos || 0} icon="fas fa-project-diagram" color="purple" />
                <StatCard title="Armazenamento" value={formatBytes(s.armazenamento || 0)} icon="fas fa-hdd" color="green" />
            </div>

            {/* Acesso Rapido */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <Link href="/capturar" className="flex items-center gap-4 bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-blue-200 transition-all group">
                    <div className="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                        <i className="fas fa-upload text-blue-600 group-hover:text-white transition-colors" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-800">Capturar Documento</p>
                        <p className="text-xs text-gray-400">Upload ou digitalizar</p>
                    </div>
                </Link>
                <Link href="/repositorio" className="flex items-center gap-4 bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-emerald-200 transition-all group">
                    <div className="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center group-hover:bg-emerald-600 transition-colors">
                        <i className="fas fa-folder-open text-emerald-600 group-hover:text-white transition-colors" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-800">Ver Repositorio</p>
                        <p className="text-xs text-gray-400">Navegar pastas e arquivos</p>
                    </div>
                </Link>
                <Link href="/fluxos/create" className="flex items-center gap-4 bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-purple-200 transition-all group">
                    <div className="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center group-hover:bg-purple-600 transition-colors">
                        <i className="fas fa-project-diagram text-purple-600 group-hover:text-white transition-colors" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-800">Criar Fluxo</p>
                        <p className="text-xs text-gray-400">Novo fluxo de trabalho</p>
                    </div>
                </Link>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Atividade Recente */}
                <Card title="Atividade Recente" subtitle="Ultimas acoes no sistema">
                    <div className="divide-y divide-gray-100">
                        {(!atividade_recente || atividade_recente.length === 0) ? (
                            <div className="py-8 text-center text-gray-400">
                                <i className="fas fa-history text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma atividade recente</p>
                            </div>
                        ) : (
                            atividade_recente.map((item, idx) => (
                                <div key={idx} className="flex items-start gap-3 py-3">
                                    <div className="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                                        <i className={`text-xs ${getAcaoIcon(item.acao)}`} />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm text-gray-700">
                                            <span className="font-medium">{item.usuario_nome || 'Sistema'}</span>
                                            {' '}{item.acao}
                                            {item.detalhes?.nome && <span className="font-medium"> "{item.detalhes.nome}"</span>}
                                        </p>
                                        <p className="text-xs text-gray-400 mt-0.5">{formatDate(item.created_at)}</p>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </Card>

                {/* Fluxos Pendentes */}
                <Card title="Fluxos Pendentes" subtitle="Aguardando sua acao">
                    <div className="divide-y divide-gray-100">
                        {(!fluxos_pendentes || fluxos_pendentes.length === 0) ? (
                            <div className="py-8 text-center text-gray-400">
                                <i className="fas fa-check-circle text-2xl mb-2 block" />
                                <p className="text-sm">Nenhum fluxo pendente</p>
                            </div>
                        ) : (
                            fluxos_pendentes.map((item, idx) => (
                                <div key={idx} className="flex items-center justify-between py-3">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-700 truncate">{item.documento_nome}</p>
                                        <p className="text-xs text-gray-400">{item.fluxo_nome} — {item.etapa_nome}</p>
                                    </div>
                                    <div className="flex items-center gap-2 ml-3">
                                        {item.prazo && (
                                            <span className={`text-xs px-2 py-0.5 rounded-full ${
                                                isPastDue(item.prazo)
                                                    ? 'bg-red-100 text-red-600'
                                                    : 'bg-yellow-100 text-yellow-600'
                                            }`}>
                                                {formatDate(item.prazo)}
                                            </span>
                                        )}
                                        <Link href={`/documentos/${item.documento_id}`}
                                            className="ds-btn ds-btn-primary ds-btn-sm">
                                            Analisar
                                        </Link>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </Card>
            </div>
        </AdminLayout>
    );
}

function getAcaoIcon(acao) {
    const map = {
        'upload': 'fas fa-upload text-blue-500',
        'download': 'fas fa-download text-green-500',
        'visualizou': 'fas fa-eye text-gray-400',
        'editou': 'fas fa-edit text-amber-500',
        'excluiu': 'fas fa-trash text-red-500',
        'aprovou': 'fas fa-check text-emerald-500',
        'rejeitou': 'fas fa-times text-red-500',
        'compartilhou': 'fas fa-share text-purple-500',
    };
    return map[acao] || 'fas fa-circle text-gray-400';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function isPastDue(dateStr) {
    return new Date(dateStr) < new Date();
}
