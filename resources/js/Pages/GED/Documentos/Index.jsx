/**
 * Meus Documentos — GED
 *
 * Toolbar horizontal, status inline, metadados nas colunas, selecao em lote.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';

export default function DocumentosIndex({ documentos, filters, favorito_ids, usuarios }) {
    const data = documentos?.data || documentos || [];
    const favIds = favorito_ids || [];
    const filtro = filters?.filtro || '';
    const userList = usuarios || [];

    const [selected, setSelected] = useState([]);
    const [search, setSearch] = useState(filters?.search || '');
    const [perPage, setPerPage] = useState(10);
    const [showAssinaturaModal, setShowAssinaturaModal] = useState(false);

    const titleMap = {
        favoritos: 'Favoritos',
        recentes: 'Ultimos Acessados',
        populares: 'Mais Acessados',
        arquivados: 'Arquivados',
    };
    const subtitleMap = {
        favoritos: 'Documentos marcados como favoritos',
        recentes: 'Documentos acessados recentemente',
        populares: 'Documentos mais acessados por voce',
        arquivados: 'Documentos com status arquivado',
    };

    // Coletar todas as chaves de metadados unicas
    const metaKeys = [];
    const metaKeysSet = new Set();
    data.forEach(doc => {
        (doc.metadados || []).forEach(m => {
            if (!metaKeysSet.has(m.chave)) {
                metaKeysSet.add(m.chave);
                metaKeys.push(m.chave);
            }
        });
    });

    const toggleSelect = (id) => {
        setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    };
    const toggleAll = () => {
        selected.length === data.length ? setSelected([]) : setSelected(data.map(d => d.id));
    };
    const selectPage = () => setSelected(data.map(d => d.id));
    const deselectPage = () => setSelected([]);

    const doSearch = (e) => {
        e?.preventDefault();
        const params = {};
        if (search) params.search = search;
        if (filtro) params.filtro = filtro;
        router.get('/documentos', params, { preserveState: true });
    };

    const bulkAction = (action) => {
        if (selected.length === 0) return;
        if (action === 'excluir') {
            if (!confirm(`Excluir ${selected.length} documento(s)?`)) return;
            selected.forEach(id => router.delete(`/documentos/${id}`, { preserveState: true, preserveScroll: true }));
            setSelected([]);
        }
    };

    const bulkStatus = (status) => {
        selected.forEach(id => {
            router.post(`/documentos/${id}/status`, { status }, { preserveState: true, preserveScroll: true });
        });
        setSelected([]);
    };

    // Export CSV
    const exportCSV = () => {
        const headers = ['Nome', 'Status', 'Tipo Documental', 'Pasta', 'Tamanho', 'Modificado', ...metaKeys];
        const rows = data.map(doc => {
            const metas = {};
            (doc.metadados || []).forEach(m => { metas[m.chave] = m.valor; });
            return [
                doc.nome, doc.status, doc.tipo_documental?.nome || '', doc.pasta?.nome || '',
                formatBytes(doc.tamanho), doc.updated_at ? new Date(doc.updated_at).toLocaleDateString('pt-BR') : '',
                ...metaKeys.map(k => metas[k] || ''),
            ];
        });
        const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'documentos.csv'; a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AdminLayout>
            <Head title={titleMap[filtro] || 'Meus Documentos'} />
            <PageHeader
                title={titleMap[filtro] || 'Meus Documentos'}
                subtitle={subtitleMap[filtro] || 'Documentos criados ou compartilhados com voce'}
            >
                <Button icon="fas fa-upload" href="/capturar">Novo Documento</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {/* Barra de pesquisa */}
                <div className="px-4 py-3 border-b border-gray-100">
                    <form onSubmit={doSearch} className="flex items-center gap-3">
                        <span className="text-sm text-gray-500 font-medium shrink-0">Filtrar Resultados:</span>
                        <div className="relative flex-1 max-w-sm">
                            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por nome, tipo..."
                                className="w-full pl-3 pr-8 py-2 rounded-lg border border-gray-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                            <button type="submit" className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600">
                                <i className="fas fa-search text-xs" />
                            </button>
                        </div>
                    </form>
                </div>

                {/* Toolbar de acoes horizontal */}
                <div className="px-4 py-2.5 border-b border-gray-100 flex flex-wrap items-center gap-1.5">
                    <ToolBtn label="Selecionar Pagina" onClick={selectPage} />
                    <ToolBtn label="Desmarcar Pagina" onClick={deselectPage} />
                    <ToolBtn label="Selecionar Tudo" onClick={toggleAll} />
                    <ToolBtn label="Desmarcar Tudo" onClick={() => setSelected([])} />
                    <ToolSep />
                    <ToolBtn label="Download" icon="fas fa-download" onClick={() => {
                        selected.forEach(id => { window.open(`/documentos/${id}/download`, '_blank'); });
                    }} disabled={selected.length === 0} />
                    <ToolBtn label="Excluir" icon="fas fa-trash" onClick={() => bulkAction('excluir')} disabled={selected.length === 0} danger />
                    <ToolSep />
                    <BulkStatusDropdown onSelect={bulkStatus} disabled={selected.length === 0} />
                    <ToolBtn label="Solicitar Assinatura" icon="fas fa-file-signature" onClick={() => setShowAssinaturaModal(true)} disabled={selected.length === 0} />
                    <ToolSep />
                    <ToolBtn label="CSV" icon="fas fa-file-csv" onClick={exportCSV} />
                    <ToolSep />

                    {/* Info selecao + mostrar */}
                    <div className="ml-auto flex items-center gap-3">
                        {selected.length > 0 && (
                            <span className="text-xs text-blue-600 font-medium bg-blue-50 px-2 py-1 rounded">
                                {selected.length} selecionado(s)
                            </span>
                        )}
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs text-gray-500">Mostrar:</span>
                            <select value={perPage} onChange={(e) => setPerPage(e.target.value)}
                                className="text-xs border border-gray-200 rounded px-1.5 py-1 outline-none">
                                <option value={10}>10</option>
                                <option value={25}>25</option>
                                <option value={50}>50</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Tabela */}
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-3 py-3 w-10">
                                    <input type="checkbox" checked={data.length > 0 && selected.length === data.length}
                                        onChange={toggleAll}
                                        className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                                </th>
                                <th className="px-3 py-3 text-left font-semibold">Preview</th>
                                <th className="px-3 py-3 text-left font-semibold">Acoes</th>
                                <th className="px-3 py-3 text-left font-semibold">Status</th>
                                <th className="px-3 py-3 text-left font-semibold">Tipo Documental</th>
                                <th className="px-3 py-3 text-left font-semibold">Pasta</th>
                                <th className="px-3 py-3 text-left font-semibold">Tamanho</th>
                                <th className="px-3 py-3 text-left font-semibold">Modificado</th>
                                {metaKeys.map(key => (
                                    <th key={key} className="px-3 py-3 text-left font-semibold whitespace-nowrap">
                                        {formatMetaLabel(key)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {data.length === 0 ? (
                                <tr>
                                    <td colSpan={8 + metaKeys.length} className="px-4 py-12 text-center text-gray-400">
                                        <i className="fas fa-inbox text-3xl mb-2 block" />
                                        Nenhum documento encontrado.
                                    </td>
                                </tr>
                            ) : data.map(row => {
                                const metas = {};
                                (row.metadados || []).forEach(m => { metas[m.chave] = m.valor; });
                                return (
                                    <tr key={row.id} className={`hover:bg-gray-50 transition-colors ${selected.includes(row.id) ? 'bg-blue-50/50' : ''}`}>
                                        <td className="px-3 py-3">
                                            <input type="checkbox" checked={selected.includes(row.id)}
                                                onChange={() => toggleSelect(row.id)}
                                                className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                                        </td>
                                        {/* Preview */}
                                        <td className="px-3 py-3">
                                            <Link href={`/documentos/${row.id}`} className="flex items-center gap-2.5 text-gray-700 hover:text-blue-600 min-w-0">
                                                <div className="w-10 h-12 bg-gray-100 rounded flex items-center justify-center shrink-0">
                                                    <i className={`${getFileIcon(row.mime_type)} text-lg`} />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-medium truncate max-w-[200px]">{row.nome}</p>
                                                    <p className="text-[10px] text-gray-400">v{row.versao_atual}</p>
                                                </div>
                                            </Link>
                                        </td>
                                        {/* Acoes */}
                                        <td className="px-3 py-3">
                                            <div className="flex items-center gap-0.5">
                                                <ActionBtn icon={`${favIds.includes(row.id) ? 'fas' : 'far'} fa-star`}
                                                    className={favIds.includes(row.id) ? 'text-yellow-500' : ''}
                                                    title="Favoritar"
                                                    onClick={() => router.post(`/documentos/${row.id}/favorito`, {}, { preserveState: true, preserveScroll: true })} />
                                                <ActionBtn icon="fas fa-download" title="Download"
                                                    onClick={() => window.open(`/documentos/${row.id}/download`, '_blank')} />
                                                <ActionBtn icon="fas fa-pen" title="Editar"
                                                    onClick={() => router.visit(`/documentos/${row.id}`)} />
                                                <ActionBtn icon="fas fa-trash" title="Excluir" danger
                                                    onClick={() => { if (confirm('Excluir?')) router.delete(`/documentos/${row.id}`); }} />
                                            </div>
                                        </td>
                                        {/* Status */}
                                        <td className="px-3 py-3">
                                            <InlineStatus documentoId={row.id} status={row.status} />
                                        </td>
                                        {/* Tipo */}
                                        <td className="px-3 py-3 text-xs text-gray-600 whitespace-nowrap">{row.tipo_documental?.nome || '-'}</td>
                                        {/* Pasta */}
                                        <td className="px-3 py-3 text-xs text-gray-500 whitespace-nowrap">{row.pasta?.nome || 'Raiz'}</td>
                                        {/* Tamanho */}
                                        <td className="px-3 py-3 text-xs text-gray-500">{formatBytes(row.tamanho)}</td>
                                        {/* Modificado */}
                                        <td className="px-3 py-3 text-xs text-gray-400 whitespace-nowrap">
                                            {row.updated_at ? new Date(row.updated_at).toLocaleDateString('pt-BR') : '-'}
                                        </td>
                                        {/* Metadados dinamicos */}
                                        {metaKeys.map(key => (
                                            <td key={key} className="px-3 py-3 text-xs text-gray-600 max-w-[180px]">
                                                <div className="truncate" title={metas[key] || ''}>
                                                    {metas[key] ? (
                                                        <span><strong className="text-gray-700">{formatMetaLabel(key)}:</strong> {metas[key]}</span>
                                                    ) : '-'}
                                                </div>
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Paginacao */}
                {documentos?.links && documentos.last_page > 1 && (
                    <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                        <span className="text-xs text-gray-500">
                            Mostrando {documentos.from}-{documentos.to} de {documentos.total}
                        </span>
                        <div className="flex gap-1">
                            {documentos.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'} preserveScroll
                                    className={`px-3 py-1.5 text-xs rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white border text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Modal Assinatura em Lote */}
            <AssinaturaLoteModal
                show={showAssinaturaModal}
                onClose={() => setShowAssinaturaModal(false)}
                documentoIds={selected}
                usuarios={userList}
                onSuccess={() => setSelected([])}
            />
        </AdminLayout>
    );
}

/* ── Toolbar Button ── */
function ToolBtn({ label, icon, onClick, disabled, danger }) {
    return (
        <button onClick={onClick} disabled={disabled}
            className={`px-2.5 py-1.5 text-[11px] font-medium rounded-md border transition-colors whitespace-nowrap
                ${disabled ? 'border-gray-100 text-gray-300 cursor-not-allowed' :
                    danger ? 'border-red-200 text-red-600 hover:bg-red-50' :
                    'border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800'}`}>
            {icon && <i className={`${icon} mr-1`} />}
            {label}
        </button>
    );
}

function ToolSep() {
    return <div className="w-px h-6 bg-gray-200 mx-0.5" />;
}

/* ── Action Button (icone na linha) ── */
function ActionBtn({ icon, title, onClick, className = '', danger }) {
    return (
        <button onClick={onClick} title={title}
            className={`w-6 h-6 rounded flex items-center justify-center transition-colors
                ${danger ? 'text-gray-400 hover:text-red-600 hover:bg-red-50' :
                    `text-gray-400 hover:text-blue-600 hover:bg-blue-50 ${className}`}`}>
            <i className={`${icon} text-[10px]`} />
        </button>
    );
}

/* ── Status inline ── */
function InlineStatus({ documentoId, status }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const cfg = {
        rascunho:  { bg: 'bg-yellow-100 text-yellow-700', label: 'Rascunho' },
        revisao:   { bg: 'bg-blue-100 text-blue-700', label: 'Revisao' },
        publicado: { bg: 'bg-green-100 text-green-700', label: 'Publicado' },
        arquivado: { bg: 'bg-gray-100 text-gray-600', label: 'Arquivado' },
    };
    const c = cfg[status] || { bg: 'bg-gray-100 text-gray-500', label: status };

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)}
                className={`text-[10px] px-2 py-0.5 rounded-full font-medium cursor-pointer hover:ring-2 hover:ring-offset-1 hover:ring-blue-200 ${c.bg}`}>
                {c.label} <i className="fas fa-chevron-down text-[7px] ml-0.5" />
            </button>
            {open && (
                <div className="absolute left-0 top-7 w-36 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 animate-fadeIn">
                    {Object.entries(cfg).map(([val, s]) => (
                        <button key={val} onClick={() => {
                            setOpen(false);
                            if (val !== status) router.post(`/documentos/${documentoId}/status`, { status: val }, { preserveState: true, preserveScroll: true });
                        }}
                            className={`w-full flex items-center gap-2 px-3 py-1.5 text-xs hover:bg-gray-50 ${status === val ? 'bg-gray-50 font-semibold' : ''}`}>
                            <span className={`w-2 h-2 rounded-full ${s.bg.split(' ')[0]}`} />
                            {s.label}
                            {status === val && <i className="fas fa-check text-[8px] text-green-500 ml-auto" />}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ── Bulk Status Dropdown ── */
function BulkStatusDropdown({ onSelect, disabled }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    return (
        <div className="relative" ref={ref}>
            <ToolBtn label="Alterar Status" icon="fas fa-exchange-alt" onClick={() => !disabled && setOpen(!open)} disabled={disabled} />
            {open && (
                <div className="absolute left-0 top-8 w-40 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 animate-fadeIn">
                    {[
                        { v: 'rascunho', l: 'Rascunho' }, { v: 'revisao', l: 'Em Revisao' },
                        { v: 'publicado', l: 'Publicado' }, { v: 'arquivado', l: 'Arquivado' },
                    ].map(s => (
                        <button key={s.v} onClick={() => { setOpen(false); onSelect(s.v); }}
                            className="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                            {s.l}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ── Modal Assinatura em Lote ── */
function AssinaturaLoteModal({ show, onClose, documentoIds, usuarios, onSuccess }) {
    const { data, setData, post, processing, reset } = useForm({
        documento_ids: [],
        signatarios: [],
        mensagem: '',
        prazo: '',
    });

    useEffect(() => {
        if (show) setData('documento_ids', documentoIds);
    }, [show, documentoIds]);

    const toggleUser = (id) => {
        const list = data.signatarios.includes(id) ? data.signatarios.filter(x => x !== id) : [...data.signatarios, id];
        setData('signatarios', list);
    };

    const submit = (e) => {
        e.preventDefault();
        post('/assinaturas/solicitar-lote', {
            onSuccess: () => { reset(); onClose(); onSuccess(); },
        });
    };

    return (
        <Modal show={show} onClose={onClose} title={`Solicitar Assinatura (${documentoIds.length} documento(s))`} maxWidth="lg">
            <form onSubmit={submit} className="space-y-4">
                <div className="bg-blue-50 rounded-xl p-3">
                    <p className="text-xs text-blue-700">
                        <i className="fas fa-info-circle mr-1" />
                        A assinatura sera solicitada para <strong>{documentoIds.length}</strong> documento(s) selecionado(s).
                    </p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Selecione os signatarios</label>
                    <div className="max-h-48 overflow-y-auto border border-gray-200 rounded-xl p-2 space-y-1">
                        {usuarios.map(u => (
                            <label key={u.id} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 rounded px-2 py-1.5">
                                <input type="checkbox" checked={data.signatarios.includes(u.id)}
                                    onChange={() => toggleUser(u.id)}
                                    className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                                <span className="text-gray-700">{u.name}</span>
                                <span className="text-xs text-gray-400 ml-auto">{u.email}</span>
                            </label>
                        ))}
                        {usuarios.length === 0 && (
                            <p className="text-xs text-gray-400 text-center py-3">Nenhum usuario disponivel</p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Mensagem (opcional)</label>
                    <textarea value={data.mensagem} onChange={(e) => setData('mensagem', e.target.value)}
                        className="ds-input !h-auto" rows={2} placeholder="Mensagem para os signatarios..." />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Prazo (opcional)</label>
                    <input type="date" value={data.prazo} onChange={(e) => setData('prazo', e.target.value)}
                        className="ds-input w-auto" />
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-paper-plane"
                        disabled={data.signatarios.length === 0}>
                        Enviar para {data.signatarios.length} signatario(s)
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Helpers ── */
function formatMetaLabel(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-400';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-400';
    if (mime.includes('word')) return 'fas fa-file-word text-blue-400';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-400';
    return 'fas fa-file text-gray-400';
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
