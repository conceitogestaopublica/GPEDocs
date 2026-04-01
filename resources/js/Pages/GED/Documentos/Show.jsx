/**
 * Detalhe do Documento — GED
 *
 * Visualizacao completa com abas: Visualizar, Metadados, Versoes, Auditoria, Fluxos.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import StatusPill from '../../../Components/StatusPill';

const TABS = [
    { key: 'visualizar', label: 'Visualizar', icon: 'fas fa-eye' },
    { key: 'metadados', label: 'Metadados', icon: 'fas fa-tags' },
    { key: 'versoes', label: 'Versoes', icon: 'fas fa-history' },
    { key: 'auditoria', label: 'Auditoria', icon: 'fas fa-shield-alt' },
    { key: 'fluxos', label: 'Fluxos', icon: 'fas fa-project-diagram' },
];

export default function Show({ documento, versoes, metadados, audit_logs, fluxo_instancias, compartilhamentos, tags }) {
    const [activeTab, setActiveTab] = useState('visualizar');
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
                    {activeTab === 'versoes' && <TabVersoes versoes={versoes} documentoId={doc.id} />}
                    {activeTab === 'auditoria' && <TabAuditoria logs={audit_logs} />}
                    {activeTab === 'fluxos' && <TabFluxos instancias={fluxo_instancias} />}
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
