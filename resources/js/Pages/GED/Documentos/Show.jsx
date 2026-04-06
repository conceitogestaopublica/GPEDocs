/**
 * Detalhe do Documento — GED
 *
 * Visualizacao completa com abas: Visualizar, Metadados, Versoes, Auditoria, Fluxos.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import AdminLayout from '../../../Layouts/AdminLayout';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import StatusPill from '../../../Components/StatusPill';

const TABS = [
    { key: 'visualizar', label: 'Visualizar', icon: 'fas fa-eye' },
    { key: 'metadados', label: 'Metadados', icon: 'fas fa-tags' },
    { key: 'assinaturas', label: 'Assinaturas', icon: 'fas fa-file-signature' },
    { key: 'versoes', label: 'Versoes', icon: 'fas fa-history' },
    { key: 'auditoria', label: 'Auditoria', icon: 'fas fa-shield-alt' },
];

export default function Show({ documento, versoes, metadados, audit_logs, fluxo_instancias, compartilhamentos, tags, is_favorito, usuarios }) {
    const [activeTab, setActiveTab] = useState('visualizar');
    const [statusOpen, setStatusOpen] = useState(false);
    const doc = documento || {};

    const statusMap = {
        publicado: 'success',
        rascunho: 'warning',
        arquivado: 'info',
        excluido: 'danger',
    };

    const clasMap = {
        publico: { bg: 'bg-green-100 text-green-700', label: 'Publico' },
        interno: { bg: 'bg-blue-100 text-blue-700', label: 'Interno' },
        confidencial: { bg: 'bg-orange-100 text-orange-700', label: 'Confidencial' },
        restrito: { bg: 'bg-red-100 text-red-700', label: 'Restrito' },
    };

    return (
        <AdminLayout>
            <Head title={doc.nome || 'Documento'} />

            {/* Cabecalho do Documento */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
                            <i className={`${getFileIcon(doc.mime_type)} text-2xl`} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-800">{doc.nome}</h1>
                            <div className="flex items-center gap-3 mt-2">
                                <StatusPill status={statusMap[doc.status] || 'info'} label={doc.status} />
                                {doc.classificacao && (
                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${clasMap[doc.classificacao]?.bg || 'bg-gray-100 text-gray-600'}`}>
                                        {clasMap[doc.classificacao]?.label || doc.classificacao}
                                    </span>
                                )}
                                <span className="text-xs text-gray-400">v{doc.versao_atual || 1}</span>
                                <span className="text-xs text-gray-400">{formatBytes(doc.tamanho)}</span>
                            </div>
                            {doc.descricao && <p className="text-sm text-gray-500 mt-2">{doc.descricao}</p>}
                            <div className="flex items-center gap-2 mt-2 flex-wrap">
                                {(tags || []).map(tag => (
                                    <span key={tag.id} className="text-[10px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 font-medium">
                                        {tag.nome}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* QR Code */}
                        {doc.qr_code_token && (
                            <div className="relative group">
                                <button className="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors"
                                    title="QR Code de verificacao">
                                    <i className="fas fa-qrcode" />
                                </button>
                                <div className="absolute right-0 top-12 bg-white rounded-xl shadow-xl border border-gray-100 p-4 z-50 hidden group-hover:block animate-fadeIn">
                                    <QRCodeSVG value={`${window.location.origin}/verificar/${doc.qr_code_token}`} size={160} />
                                    <p className="text-[10px] text-gray-400 text-center mt-2">Escaneie para verificar</p>
                                </div>
                            </div>
                        )}

                        {/* Favorito */}
                        <button
                            onClick={() => router.post(`/documentos/${doc.id}/favorito`)}
                            className={`w-10 h-10 rounded-xl border flex items-center justify-center transition-all
                                ${is_favorito
                                    ? 'bg-yellow-50 border-yellow-300 text-yellow-500 hover:bg-yellow-100'
                                    : 'border-gray-200 text-gray-400 hover:bg-gray-50 hover:text-yellow-500'}`}
                            title={is_favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
                        >
                            <i className={`${is_favorito ? 'fas' : 'far'} fa-star`} />
                        </button>

                        {/* Status */}
                        <div className="relative">
                            <button
                                onClick={() => setStatusOpen(!statusOpen)}
                                className="ds-btn ds-btn-outline flex items-center gap-2"
                            >
                                <i className="fas fa-exchange-alt text-xs" />
                                Status
                                <i className={`fas fa-chevron-down text-[10px] transition-transform ${statusOpen ? 'rotate-180' : ''}`} />
                            </button>
                            {statusOpen && (
                                <div className="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 animate-fadeIn">
                                    {[
                                        { value: 'rascunho', label: 'Rascunho', icon: 'fas fa-pen', color: 'text-yellow-600' },
                                        { value: 'revisao', label: 'Em Revisao', icon: 'fas fa-search', color: 'text-blue-600' },
                                        { value: 'publicado', label: 'Publicado', icon: 'fas fa-check-circle', color: 'text-green-600' },
                                        { value: 'arquivado', label: 'Arquivado', icon: 'fas fa-archive', color: 'text-gray-600' },
                                    ].map(s => (
                                        <button
                                            key={s.value}
                                            onClick={() => {
                                                setStatusOpen(false);
                                                router.post(`/documentos/${doc.id}/status`, { status: s.value });
                                            }}
                                            className={`w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-gray-50 transition-colors
                                                ${doc.status === s.value ? 'bg-gray-50 font-semibold' : ''}`}
                                        >
                                            <i className={`${s.icon} text-xs ${s.color} w-4`} />
                                            <span className={doc.status === s.value ? s.color : 'text-gray-700'}>{s.label}</span>
                                            {doc.status === s.value && <i className="fas fa-check text-[10px] text-green-500 ml-auto" />}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <Button variant="secondary" icon="fas fa-share-alt">Compartilhar</Button>
                        <a href={`/documentos/${doc.id}/download`} className="ds-btn ds-btn-primary">
                            <i className="fas fa-download mr-1" /> Download
                        </a>
                        <Button variant="danger" icon="fas fa-trash" onClick={() => {
                            if (confirm('Tem certeza que deseja excluir este documento?')) {
                                router.delete(`/documentos/${doc.id}`);
                            }
                        }}>Excluir</Button>
                    </div>
                </div>
            </div>

            {/* Abas */}
            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="flex border-b border-gray-200">
                    {TABS.map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={`flex items-center gap-2 px-5 py-4 text-sm font-medium border-b-2 transition-colors
                                ${activeTab === tab.key
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                        >
                            <i className={`${tab.icon} text-xs`} />
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="p-6">
                    {activeTab === 'visualizar' && <TabVisualizar documento={doc} />}
                    {activeTab === 'metadados' && <TabMetadados metadados={metadados} documento={doc} />}
                    {activeTab === 'assinaturas' && <TabAssinaturas documento={doc} usuarios={usuarios || []} />}
                    {activeTab === 'versoes' && <TabVersoes versoes={versoes} documentoId={doc.id} />}
                    {activeTab === 'auditoria' && <TabAuditoria logs={audit_logs} />}
                </div>
            </div>
        </AdminLayout>
    );
}

function TabVisualizar({ documento }) {
    const isPdf = documento.mime_type?.includes('pdf');
    const isImage = documento.mime_type?.includes('image');

    return (
        <div className="flex items-center justify-center min-h-[400px] bg-gray-50 rounded-xl">
            {isPdf ? (
                <iframe
                    src={`/documentos/${documento.id}/preview`}
                    className="w-full h-[600px] rounded-lg"
                    title="Visualizador PDF"
                />
            ) : isImage ? (
                <img
                    src={`/documentos/${documento.id}/preview`}
                    alt={documento.nome}
                    className="max-w-full max-h-[600px] rounded-lg shadow-lg"
                />
            ) : (
                <div className="text-center text-gray-400">
                    <i className="fas fa-file text-5xl mb-4 block" />
                    <p className="text-lg font-medium">Pre-visualizacao nao disponivel</p>
                    <p className="text-sm mt-1">Faca o download para visualizar este arquivo</p>
                    <a href={`/documentos/${documento.id}/download`} className="ds-btn ds-btn-primary mt-4">
                        <i className="fas fa-download mr-2" />Download
                    </a>
                </div>
            )}
        </div>
    );
}

function TabMetadados({ metadados, documento }) {
    const metas = metadados || [];
    return (
        <div className="space-y-6">
            {/* Metadados do sistema */}
            <div>
                <h3 className="text-sm font-semibold text-gray-700 mb-3">Informacoes do Documento</h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <InfoRow label="Tipo Documental" value={documento.tipo_nome || '-'} />
                    <InfoRow label="Autor" value={documento.autor_nome || '-'} />
                    <InfoRow label="Criado em" value={formatDate(documento.created_at)} />
                    <InfoRow label="Atualizado em" value={formatDate(documento.updated_at)} />
                    <InfoRow label="Tamanho" value={formatBytes(documento.tamanho)} />
                    <InfoRow label="MIME Type" value={documento.mime_type || '-'} />
                </div>
            </div>

            {/* Metadados customizados */}
            {metas.length > 0 && (
                <div>
                    <h3 className="text-sm font-semibold text-gray-700 mb-3">Metadados Personalizados</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {metas.map(m => (
                            <InfoRow key={m.id} label={m.chave} value={m.valor} />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function TabVersoes({ versoes, documentoId }) {
    const vers = versoes || [];
    return (
        <div>
            <table className="w-full text-sm">
                <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th className="px-4 py-3 text-left font-semibold">Versao</th>
                        <th className="px-4 py-3 text-left font-semibold">Autor</th>
                        <th className="px-4 py-3 text-left font-semibold">Tamanho</th>
                        <th className="px-4 py-3 text-left font-semibold">Data</th>
                        <th className="px-4 py-3 text-left font-semibold">Comentario</th>
                        <th className="px-4 py-3 text-center font-semibold w-24">Acao</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {vers.map(v => (
                        <tr key={v.id} className="hover:bg-gray-50">
                            <td className="px-4 py-3 font-medium">v{v.versao}</td>
                            <td className="px-4 py-3 text-gray-500">{v.autor_nome || '-'}</td>
                            <td className="px-4 py-3 text-gray-500">{formatBytes(v.tamanho)}</td>
                            <td className="px-4 py-3 text-gray-400 text-xs">{formatDate(v.created_at)}</td>
                            <td className="px-4 py-3 text-gray-500 truncate max-w-xs">{v.comentario || '-'}</td>
                            <td className="px-4 py-3 text-center">
                                <button className="text-blue-600 hover:text-blue-800 text-xs font-medium">Restaurar</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {vers.length === 0 && (
                <div className="py-8 text-center text-gray-400">
                    <p className="text-sm">Nenhuma versao anterior</p>
                </div>
            )}
        </div>
    );
}

function TabAuditoria({ logs }) {
    const items = logs || [];
    return (
        <div>
            <table className="w-full text-sm">
                <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th className="px-4 py-3 text-left font-semibold">Data/Hora</th>
                        <th className="px-4 py-3 text-left font-semibold">Usuario</th>
                        <th className="px-4 py-3 text-left font-semibold">Acao</th>
                        <th className="px-4 py-3 text-left font-semibold">Detalhes</th>
                        <th className="px-4 py-3 text-left font-semibold">IP</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {items.map(log => (
                        <tr key={log.id} className="hover:bg-gray-50">
                            <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{formatDate(log.created_at)}</td>
                            <td className="px-4 py-3 text-gray-700 font-medium">{log.usuario_nome || '-'}</td>
                            <td className="px-4 py-3">
                                <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">{log.acao}</span>
                            </td>
                            <td className="px-4 py-3 text-gray-500 text-xs truncate max-w-xs">{JSON.stringify(log.detalhes)}</td>
                            <td className="px-4 py-3 text-gray-400 text-xs">{log.ip || '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {items.length === 0 && (
                <div className="py-8 text-center text-gray-400">
                    <p className="text-sm">Nenhum registro de auditoria</p>
                </div>
            )}
        </div>
    );
}

function TabFluxos({ instancias }) {
    const items = instancias || [];
    const statusColors = {
        pendente: 'bg-yellow-100 text-yellow-700',
        em_andamento: 'bg-blue-100 text-blue-700',
        concluido: 'bg-green-100 text-green-700',
        cancelado: 'bg-red-100 text-red-700',
    };

    return (
        <div>
            {items.length === 0 ? (
                <div className="py-8 text-center text-gray-400">
                    <i className="fas fa-project-diagram text-2xl mb-2 block" />
                    <p className="text-sm">Nenhum fluxo associado</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {items.map(inst => (
                        <div key={inst.id} className="border border-gray-200 rounded-xl p-4">
                            <div className="flex items-center justify-between mb-2">
                                <h4 className="text-sm font-semibold text-gray-700">{inst.fluxo_nome}</h4>
                                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statusColors[inst.status] || 'bg-gray-100 text-gray-600'}`}>
                                    {inst.status}
                                </span>
                            </div>
                            <p className="text-xs text-gray-400">Etapa atual: {inst.etapa_atual || '-'}</p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function TabAssinaturas({ documento, usuarios }) {
    const [showSolicitar, setShowSolicitar] = useState(false);
    const solicitacoes = documento.solicitacoes_assinatura || [];
    const { data, setData, post, processing, reset } = useForm({
        signatarios: [],
        mensagem: '',
        prazo: '',
    });

    const toggleUser = (id) => {
        const list = data.signatarios.includes(id) ? data.signatarios.filter(x => x !== id) : [...data.signatarios, id];
        setData('signatarios', list);
    };

    const submitSolicitar = (e) => {
        e.preventDefault();
        post(`/documentos/${documento.id}/solicitar-assinatura`, {
            onSuccess: () => { reset(); setShowSolicitar(false); },
        });
    };

    const statusColors = {
        pendente: 'bg-yellow-100 text-yellow-700',
        assinado: 'bg-green-100 text-green-700',
        recusado: 'bg-red-100 text-red-700',
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold text-gray-700">Solicitacoes de Assinatura</h3>
                <Button size="sm" icon="fas fa-plus" onClick={() => setShowSolicitar(!showSolicitar)}>
                    Solicitar Assinatura
                </Button>
            </div>

            {/* Form solicitar */}
            {showSolicitar && (
                <form onSubmit={submitSolicitar} className="bg-blue-50 rounded-xl p-4 space-y-3">
                    <p className="text-sm font-medium text-blue-800">Selecione os signatarios</p>
                    <div className="max-h-40 overflow-y-auto space-y-1">
                        {usuarios.map(u => (
                            <label key={u.id} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-blue-100 rounded px-2 py-1">
                                <input type="checkbox" checked={data.signatarios.includes(u.id)}
                                    onChange={() => toggleUser(u.id)}
                                    className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                                <span className="text-gray-700">{u.name}</span>
                                <span className="text-xs text-gray-400">{u.email}</span>
                            </label>
                        ))}
                    </div>
                    <textarea value={data.mensagem} onChange={(e) => setData('mensagem', e.target.value)}
                        className="ds-input !h-auto" rows={2} placeholder="Mensagem opcional..." />
                    <div className="flex items-center gap-3">
                        <input type="date" value={data.prazo} onChange={(e) => setData('prazo', e.target.value)}
                            className="ds-input w-auto" />
                        <span className="text-xs text-gray-500">Prazo (opcional)</span>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" size="sm" loading={processing} icon="fas fa-paper-plane"
                            disabled={data.signatarios.length === 0}>
                            Enviar
                        </Button>
                        <Button variant="ghost" size="sm" type="button" onClick={() => setShowSolicitar(false)}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            )}

            {/* Lista de solicitacoes */}
            {solicitacoes.length === 0 ? (
                <div className="py-8 text-center text-gray-400">
                    <i className="fas fa-file-signature text-2xl mb-2 block" />
                    <p className="text-sm">Nenhuma solicitacao de assinatura</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {solicitacoes.map(sol => (
                        <div key={sol.id} className="border border-gray-200 rounded-xl overflow-hidden">
                            <div className="bg-gray-50 px-4 py-3 flex items-center justify-between">
                                <div>
                                    <span className="text-xs text-gray-500">
                                        Solicitado por <strong>{sol.solicitante?.name}</strong> em {new Date(sol.created_at).toLocaleDateString('pt-BR')}
                                    </span>
                                    {sol.mensagem && <p className="text-xs text-gray-600 mt-0.5">{sol.mensagem}</p>}
                                </div>
                                <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${
                                    sol.status === 'concluida' ? 'bg-green-100 text-green-700' :
                                    sol.status === 'cancelada' ? 'bg-red-100 text-red-700' :
                                    'bg-yellow-100 text-yellow-700'
                                }`}>{sol.status}</span>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {(sol.assinaturas || []).map(a => (
                                    <div key={a.id} className="flex items-center justify-between px-4 py-2.5">
                                        <div className="flex items-center gap-2">
                                            <i className={`fas ${a.status === 'assinado' ? 'fa-check-circle text-green-500' : a.status === 'recusado' ? 'fa-times-circle text-red-500' : 'fa-clock text-yellow-500'} text-xs`} />
                                            <span className="text-sm text-gray-700">{a.signatario?.name}</span>
                                            <span className="text-xs text-gray-400">{a.email_signatario}</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {a.assinado_em && (
                                                <span className="text-[10px] text-gray-400">
                                                    {new Date(a.assinado_em).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                                </span>
                                            )}
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${statusColors[a.status] || 'bg-gray-100 text-gray-500'}`}>
                                                {a.status}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function InfoRow({ label, value }) {
    return (
        <div className="bg-gray-50 rounded-lg px-4 py-3">
            <p className="text-[11px] text-gray-400 uppercase font-semibold tracking-wide">{label}</p>
            <p className="text-sm text-gray-700 mt-0.5">{value || '-'}</p>
        </div>
    );
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-500';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-500';
    if (mime.includes('word')) return 'fas fa-file-word text-blue-500';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-500';
    return 'fas fa-file text-gray-400';
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
