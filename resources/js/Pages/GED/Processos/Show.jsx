/**
 * Visualizacao de Processo — GED
 *
 * Detalhes completos, timeline de tramitacao, despacho e comentarios.
 */
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';
import Tabs from '../../../Components/Tabs';

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

function getSlaInfo(prazo) {
    if (!prazo) return { label: 'Sem prazo', color: 'text-gray-400', dotColor: 'bg-gray-300' };
    const now = new Date();
    const deadline = new Date(prazo);
    const diffMs = deadline - now;
    const diffHours = diffMs / (1000 * 60 * 60);

    if (diffMs < 0) return { label: 'Atrasado', color: 'text-red-600', dotColor: 'bg-red-500' };
    if (diffHours <= 24) return { label: 'Expirando', color: 'text-yellow-600', dotColor: 'bg-yellow-500' };
    return { label: 'No prazo', color: 'text-green-600', dotColor: 'bg-green-500' };
}

const tabList = [
    { key: 'detalhes', label: 'Detalhes', icon: 'fas fa-info-circle' },
    { key: 'tramitacao', label: 'Tramitacao', icon: 'fas fa-route' },
    { key: 'despachar', label: 'Despachar', icon: 'fas fa-paper-plane' },
    { key: 'comentarios', label: 'Comentarios', icon: 'fas fa-comments' },
];

export default function Show({ processo, usuarios }) {
    const proc = processo || {};
    const tramitacoes = proc.tramitacoes || [];
    const comentarios = proc.comentarios || [];
    const anexos = proc.anexos || [];
    const dadosFormulario = proc.dados_formulario || {};
    const requerente = proc.requerente || {};
    const tipoProcesso = proc.tipo_processo || {};
    const templatesDespacho = tipoProcesso.templates_despacho || [];
    const etapaAtual = proc.etapa_atual || tramitacoes.find(t => t.status === 'pendente' || t.status === 'recebido');
    const userList = usuarios || [];

    const [activeTab, setActiveTab] = useState('detalhes');
    const [showConcluirModal, setShowConcluirModal] = useState(false);
    const [showCancelarModal, setShowCancelarModal] = useState(false);

    // Form: Concluir
    const concluirForm = useForm({ observacao: '' });

    // Form: Despachar
    const despacharForm = useForm({
        destinatario_id: '',
        setor_destino: '',
        despacho: '',
        files: [],
    });
    const [despacharFiles, setDespacharFiles] = useState([]);

    // Form: Comentario
    const comentarioForm = useForm({
        conteudo: '',
        interno: false,
    });

    const handleConcluir = (e) => {
        e.preventDefault();
        concluirForm.post(`/processos/${proc.id}/concluir`, {
            onSuccess: () => setShowConcluirModal(false),
        });
    };

    const handleCancelar = () => {
        router.post(`/processos/${proc.id}/cancelar`, {}, {
            onSuccess: () => setShowCancelarModal(false),
        });
    };

    const handleDespachar = (e) => {
        e.preventDefault();
        if (!etapaAtual) return;

        const formData = new FormData();
        formData.append('destinatario_id', despacharForm.data.destinatario_id);
        formData.append('setor_destino', despacharForm.data.setor_destino);
        formData.append('despacho', despacharForm.data.despacho);
        despacharFiles.forEach((file, i) => formData.append(`files[${i}]`, file));

        despacharForm.post(`/tramitacoes/${etapaAtual.id}/despachar`, {
            data: formData,
            forceFormData: true,
            onSuccess: () => {
                despacharForm.reset();
                setDespacharFiles([]);
            },
        });
    };

    const handleDevolver = () => {
        if (!etapaAtual) return;
        router.post(`/tramitacoes/${etapaAtual.id}/devolver`, {}, {
            preserveScroll: true,
        });
    };

    const handleComentario = (e) => {
        e.preventDefault();
        comentarioForm.post(`/processos/${proc.id}/comentarios`, {
            onSuccess: () => comentarioForm.reset(),
            preserveScroll: true,
        });
    };

    const addDespacharFile = (e) => {
        setDespacharFiles(prev => [...prev, ...Array.from(e.target.files)]);
        e.target.value = '';
    };

    const applyTemplate = (template) => {
        despacharForm.setData('despacho', template.conteudo || template);
    };

    return (
        <AdminLayout>
            <Head title={`Processo ${proc.numero_protocolo || ''}`} />

            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start justify-between flex-wrap gap-4">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <Link href="/processos" className="text-gray-400 hover:text-gray-600 transition-colors">
                                <i className="fas fa-arrow-left" />
                            </Link>
                            <h1 className="text-2xl font-bold text-gray-800">{proc.numero_protocolo}</h1>
                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[proc.status] || 'bg-gray-100 text-gray-600'}`}>
                                {statusLabels[proc.status] || proc.status}
                            </span>
                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${prioridadeColors[proc.prioridade] || 'bg-gray-100 text-gray-600'}`}>
                                {proc.prioridade}
                            </span>
                        </div>
                        <p className="text-gray-600 ml-8">{proc.assunto}</p>
                        <div className="flex items-center gap-4 mt-2 ml-8 text-xs text-gray-400">
                            <span><i className="fas fa-folder mr-1" />{tipoProcesso.nome || '-'}</span>
                            <span><i className="fas fa-building mr-1" />{proc.setor_origem || '-'}</span>
                            <span><i className="fas fa-calendar mr-1" />{formatDate(proc.created_at)}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {proc.status !== 'concluido' && proc.status !== 'cancelado' && (
                            <>
                                <Button variant="success" icon="fas fa-check" onClick={() => setShowConcluirModal(true)}>
                                    Concluir
                                </Button>
                                <Button variant="danger" icon="fas fa-ban" onClick={() => setShowCancelarModal(true)}>
                                    Cancelar
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <Tabs tabs={tabList} activeTab={activeTab} onChange={setActiveTab} />

            <div className="mt-6">
                {/* ── Tab: Detalhes ── */}
                {activeTab === 'detalhes' && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Dados do formulario */}
                        <Card title="Dados do Formulario">
                            {Object.keys(dadosFormulario).length === 0 ? (
                                <p className="text-sm text-gray-400">Nenhum dado preenchido</p>
                            ) : (
                                <div className="space-y-3">
                                    {Object.entries(dadosFormulario).map(([key, value]) => (
                                        <div key={key} className="flex items-start justify-between py-2 border-b border-gray-50 last:border-0">
                                            <span className="text-sm font-medium text-gray-500 capitalize">
                                                {key.replace(/_/g, ' ')}
                                            </span>
                                            <span className="text-sm text-gray-800 text-right max-w-[60%]">{value || '-'}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>

                        {/* Requerente */}
                        <div className="space-y-6">
                            <Card title="Requerente">
                                {!requerente.nome && !proc.requerente_nome ? (
                                    <p className="text-sm text-gray-400">Nenhum requerente informado</p>
                                ) : (
                                    <div className="space-y-2">
                                        {(requerente.nome || proc.requerente_nome) && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <i className="fas fa-user text-gray-400 w-5" />
                                                <span className="text-gray-700">{requerente.nome || proc.requerente_nome}</span>
                                            </div>
                                        )}
                                        {(requerente.cpf || proc.requerente_cpf) && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <i className="fas fa-id-card text-gray-400 w-5" />
                                                <span className="text-gray-700">{requerente.cpf || proc.requerente_cpf}</span>
                                            </div>
                                        )}
                                        {(requerente.email || proc.requerente_email) && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <i className="fas fa-envelope text-gray-400 w-5" />
                                                <span className="text-gray-700">{requerente.email || proc.requerente_email}</span>
                                            </div>
                                        )}
                                        {(requerente.telefone || proc.requerente_telefone) && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <i className="fas fa-phone text-gray-400 w-5" />
                                                <span className="text-gray-700">{requerente.telefone || proc.requerente_telefone}</span>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </Card>

                            {/* Anexos */}
                            <Card title={`Anexos (${anexos.length})`}>
                                {anexos.length === 0 ? (
                                    <p className="text-sm text-gray-400">Nenhum anexo</p>
                                ) : (
                                    <div className="space-y-2">
                                        {anexos.map((anexo, idx) => (
                                            <div key={idx} className="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <i className={`${getFileIcon(anexo.mime_type || anexo.tipo)} text-lg`} />
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-700">{anexo.nome || anexo.nome_original}</p>
                                                        {anexo.tamanho && <p className="text-xs text-gray-400">{formatBytes(anexo.tamanho)}</p>}
                                                    </div>
                                                </div>
                                                <a
                                                    href={anexo.url || `/anexos/${anexo.id}/download`}
                                                    className="text-blue-600 hover:text-blue-800 transition-colors"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <i className="fas fa-download" />
                                                </a>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Card>
                        </div>
                    </div>
                )}

                {/* ── Tab: Tramitacao (Timeline) ── */}
                {activeTab === 'tramitacao' && (
                    <Card title="Historico de Tramitacao">
                        {tramitacoes.length === 0 ? (
                            <div className="py-8 text-center text-gray-400">
                                <i className="fas fa-route text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma tramitacao registrada</p>
                            </div>
                        ) : (
                            <div className="relative">
                                {/* Vertical line */}
                                <div className="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-200" />

                                <div className="space-y-6">
                                    {tramitacoes.map((tram, idx) => {
                                        const sla = getSlaInfo(tram.prazo);
                                        const isLast = idx === tramitacoes.length - 1;
                                        const isPending = tram.status === 'pendente' || tram.status === 'recebido';

                                        return (
                                            <div key={tram.id || idx} className="relative pl-12">
                                                {/* Timeline dot */}
                                                <div className={`absolute left-3.5 w-4 h-4 rounded-full border-2 border-white shadow ${
                                                    tram.status === 'concluido' || tram.status === 'despachado'
                                                        ? 'bg-green-500'
                                                        : isPending
                                                        ? 'bg-blue-500 animate-pulse'
                                                        : 'bg-gray-300'
                                                }`} />

                                                <div className={`bg-white rounded-xl border p-4 ${isPending ? 'border-blue-200 shadow-sm' : 'border-gray-100'}`}>
                                                    <div className="flex items-start justify-between flex-wrap gap-2">
                                                        <div>
                                                            <div className="flex items-center gap-2 mb-1">
                                                                <span className="text-xs font-bold text-gray-400">#{tram.ordem || idx + 1}</span>
                                                                <span className="text-sm font-semibold text-gray-800">{tram.setor_destino || '-'}</span>
                                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium ${
                                                                    tram.status === 'despachado' ? 'bg-green-100 text-green-700'
                                                                    : tram.status === 'recebido' ? 'bg-blue-100 text-blue-700'
                                                                    : tram.status === 'pendente' ? 'bg-yellow-100 text-yellow-700'
                                                                    : tram.status === 'devolvido' ? 'bg-orange-100 text-orange-700'
                                                                    : 'bg-gray-100 text-gray-600'
                                                                }`}>
                                                                    {tram.status}
                                                                </span>
                                                            </div>
                                                            <p className="text-xs text-gray-500">
                                                                {tram.remetente?.name || tram.remetente_nome || '-'}
                                                                <i className="fas fa-arrow-right mx-1.5 text-[8px]" />
                                                                {tram.destinatario?.name || tram.destinatario_nome || '-'}
                                                            </p>
                                                        </div>
                                                        <div className="text-right">
                                                            {tram.prazo && (
                                                                <span className={`text-xs font-medium ${sla.color}`}>
                                                                    <i className={`fas fa-circle text-[6px] mr-1 ${sla.dotColor}`} />
                                                                    {sla.label}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {tram.despacho && (
                                                        <div className="mt-3 p-3 bg-gray-50 rounded-lg">
                                                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{tram.despacho}</p>
                                                        </div>
                                                    )}

                                                    <div className="flex items-center gap-4 mt-3 text-[11px] text-gray-400">
                                                        {tram.recebido_em && (
                                                            <span><i className="fas fa-inbox mr-1" />Recebido: {formatDate(tram.recebido_em)}</span>
                                                        )}
                                                        {tram.despachado_em && (
                                                            <span><i className="fas fa-paper-plane mr-1" />Despachado: {formatDate(tram.despachado_em)}</span>
                                                        )}
                                                        {tram.prazo && (
                                                            <span><i className="fas fa-clock mr-1" />Prazo: {formatDate(tram.prazo)}</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </Card>
                )}

                {/* ── Tab: Despachar ── */}
                {activeTab === 'despachar' && (
                    <Card title="Despachar Processo">
                        {!etapaAtual ? (
                            <div className="py-8 text-center text-gray-400">
                                <i className="fas fa-check-circle text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma etapa ativa para despachar</p>
                            </div>
                        ) : (
                            <form onSubmit={handleDespachar}>
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Destinatario <span className="text-red-500">*</span>
                                            </label>
                                            <select
                                                value={despacharForm.data.destinatario_id}
                                                onChange={(e) => despacharForm.setData('destinatario_id', e.target.value)}
                                                className="ds-input"
                                            >
                                                <option value="">Selecionar usuario...</option>
                                                {userList.map(u => (
                                                    <option key={u.id} value={u.id}>{u.name}</option>
                                                ))}
                                            </select>
                                            {despacharForm.errors.destinatario_id && (
                                                <p className="mt-1 text-xs text-red-600">{despacharForm.errors.destinatario_id}</p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Setor de Destino</label>
                                            <input
                                                type="text"
                                                value={despacharForm.data.setor_destino}
                                                onChange={(e) => despacharForm.setData('setor_destino', e.target.value)}
                                                className="ds-input"
                                                placeholder="Setor de destino"
                                            />
                                        </div>
                                    </div>

                                    {/* Templates de despacho */}
                                    {templatesDespacho.length > 0 && (
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 mb-2">Templates de Despacho</label>
                                            <div className="flex flex-wrap gap-2">
                                                {templatesDespacho.map((tmpl, idx) => (
                                                    <button
                                                        key={idx}
                                                        type="button"
                                                        onClick={() => applyTemplate(tmpl)}
                                                        className="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors"
                                                    >
                                                        <i className="fas fa-file-alt mr-1" />
                                                        {tmpl.nome || `Template ${idx + 1}`}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Despacho <span className="text-red-500">*</span>
                                        </label>
                                        <textarea
                                            value={despacharForm.data.despacho}
                                            onChange={(e) => despacharForm.setData('despacho', e.target.value)}
                                            className="ds-input !h-auto"
                                            rows={6}
                                            placeholder="Texto do despacho..."
                                        />
                                        {despacharForm.errors.despacho && (
                                            <p className="mt-1 text-xs text-red-600">{despacharForm.errors.despacho}</p>
                                        )}
                                    </div>

                                    {/* Anexos do despacho */}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Anexos</label>
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => document.getElementById('despachoFileInput').click()}
                                                className="ds-btn ds-btn-outline ds-btn-sm"
                                            >
                                                <i className="fas fa-paperclip mr-1" />
                                                Anexar arquivo
                                            </button>
                                            <input
                                                id="despachoFileInput"
                                                type="file"
                                                multiple
                                                onChange={addDespacharFile}
                                                className="hidden"
                                            />
                                            {despacharFiles.length > 0 && (
                                                <span className="text-xs text-gray-400">{despacharFiles.length} arquivo(s)</span>
                                            )}
                                        </div>
                                        {despacharFiles.length > 0 && (
                                            <div className="mt-2 space-y-1">
                                                {despacharFiles.map((file, idx) => (
                                                    <div key={idx} className="flex items-center justify-between text-xs bg-gray-50 rounded px-3 py-2">
                                                        <span className="truncate">{file.name}</span>
                                                        <button type="button" onClick={() => setDespacharFiles(prev => prev.filter((_, i) => i !== idx))}
                                                            className="text-red-400 hover:text-red-600 ml-2">
                                                            <i className="fas fa-times" />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-3 pt-2">
                                        <Button type="submit" loading={despacharForm.processing} icon="fas fa-paper-plane">
                                            Despachar
                                        </Button>
                                        <Button type="button" variant="secondary" icon="fas fa-undo" onClick={handleDevolver}>
                                            Devolver
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        )}
                    </Card>
                )}

                {/* ── Tab: Comentarios ── */}
                {activeTab === 'comentarios' && (
                    <div className="space-y-6">
                        {/* Form novo comentario */}
                        <Card title="Novo Comentario">
                            <form onSubmit={handleComentario}>
                                <div className="space-y-3">
                                    <textarea
                                        value={comentarioForm.data.conteudo}
                                        onChange={(e) => comentarioForm.setData('conteudo', e.target.value)}
                                        className="ds-input !h-auto"
                                        rows={3}
                                        placeholder="Escreva seu comentario..."
                                    />
                                    {comentarioForm.errors.conteudo && (
                                        <p className="text-xs text-red-600">{comentarioForm.errors.conteudo}</p>
                                    )}
                                    <div className="flex items-center justify-between">
                                        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={comentarioForm.data.interno}
                                                onChange={(e) => comentarioForm.setData('interno', e.target.checked)}
                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            Comentario interno
                                        </label>
                                        <Button type="submit" loading={comentarioForm.processing} icon="fas fa-comment" size="sm">
                                            Comentar
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </Card>

                        {/* Lista de comentarios */}
                        <Card title={`Comentarios (${comentarios.length})`}>
                            {comentarios.length === 0 ? (
                                <div className="py-8 text-center text-gray-400">
                                    <i className="fas fa-comments text-2xl mb-2 block" />
                                    <p className="text-sm">Nenhum comentario</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {comentarios.map((com, idx) => (
                                        <div key={com.id || idx} className={`p-4 rounded-xl ${com.interno ? 'bg-yellow-50 border border-yellow-100' : 'bg-gray-50'}`}>
                                            <div className="flex items-center justify-between mb-2">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center">
                                                        <i className="fas fa-user text-blue-600 text-xs" />
                                                    </div>
                                                    <span className="text-sm font-medium text-gray-700">
                                                        {com.usuario?.name || com.usuario_nome || 'Usuario'}
                                                    </span>
                                                    {com.interno && (
                                                        <span className="text-[10px] px-1.5 py-0.5 bg-yellow-200 text-yellow-700 rounded font-medium">
                                                            Interno
                                                        </span>
                                                    )}
                                                </div>
                                                <span className="text-xs text-gray-400">{formatDate(com.created_at)}</span>
                                            </div>
                                            <p className="text-sm text-gray-600 whitespace-pre-wrap">{com.conteudo}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>
                    </div>
                )}
            </div>

            {/* Modal: Concluir */}
            <Modal show={showConcluirModal} onClose={() => setShowConcluirModal(false)} title="Concluir Processo">
                <form onSubmit={handleConcluir}>
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600">
                            Deseja concluir o processo <strong>{proc.numero_protocolo}</strong>?
                        </p>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Observacao</label>
                            <textarea
                                value={concluirForm.data.observacao}
                                onChange={(e) => concluirForm.setData('observacao', e.target.value)}
                                className="ds-input !h-auto"
                                rows={3}
                                placeholder="Observacao sobre a conclusao..."
                            />
                        </div>
                        <div className="flex items-center justify-end gap-2 pt-2">
                            <Button variant="secondary" type="button" onClick={() => setShowConcluirModal(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" variant="accent" loading={concluirForm.processing} icon="fas fa-check">
                                Confirmar Conclusao
                            </Button>
                        </div>
                    </div>
                </form>
            </Modal>

            {/* Modal: Cancelar */}
            <Modal show={showCancelarModal} onClose={() => setShowCancelarModal(false)} title="Cancelar Processo">
                <div className="space-y-4">
                    <div className="flex items-start gap-3 p-4 bg-red-50 rounded-xl">
                        <i className="fas fa-exclamation-triangle text-red-500 mt-0.5" />
                        <div>
                            <p className="text-sm font-medium text-red-800">Atencao</p>
                            <p className="text-sm text-red-600 mt-1">
                                Esta acao cancelara o processo <strong>{proc.numero_protocolo}</strong> e nao podera ser desfeita.
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button variant="secondary" onClick={() => setShowCancelarModal(false)}>
                            Voltar
                        </Button>
                        <Button variant="danger" icon="fas fa-ban" onClick={handleCancelar}>
                            Confirmar Cancelamento
                        </Button>
                    </div>
                </div>
            </Modal>
        </AdminLayout>
    );
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-400';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-400';
    if (mime.includes('word') || mime.includes('document')) return 'fas fa-file-word text-blue-400';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-400';
    return 'fas fa-file text-gray-400';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
