/**
 * Repositorio / Gerenciador de Arquivos — GED
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';
import Card from '../../../Components/Card';

export default function Repositorio({ pastas, documentos, pasta_atual, breadcrumb }) {
    const [viewMode, setViewMode] = useState('grid');
    const [selectedDoc, setSelectedDoc] = useState(null);
    const [showNewFolder, setShowNewFolder] = useState(false);
    const [search, setSearch] = useState('');

    // Modais de acao em pasta
    const [renamePasta, setRenamePasta] = useState(null);
    const [deletePasta, setDeletePasta] = useState(null);
    const [inativarPasta, setInativarPasta] = useState(null);

    const docs = documentos?.data || documentos || [];
    const folders = pastas || [];
    const crumbs = breadcrumb || [];

    return (
        <AdminLayout>
            <Head title="Repositorio" />

            <PageHeader title="Repositorio" subtitle="Navegar, visualizar e gerenciar documentos e pastas">
                <Button variant="secondary" icon="fas fa-folder-plus" onClick={() => setShowNewFolder(true)}>
                    Nova Pasta
                </Button>
                <Button icon="fas fa-upload" href="/capturar">
                    Upload
                </Button>
            </PageHeader>

            <div className="flex gap-5">
                {/* Arvore de pastas */}
                <div className="hidden lg:block w-64 shrink-0">
                    <Card title="Pastas" padding={false}>
                        <div className="p-3 max-h-[calc(100vh-250px)] overflow-y-auto">
                            <FolderItem
                                folder={{ id: null, nome: 'Raiz', children: buildTree(folders) }}
                                currentPastaId={pasta_atual?.id}
                                level={0}
                                onRename={setRenamePasta}
                                onDelete={setDeletePasta}
                                onInativar={setInativarPasta}
                            />
                        </div>
                    </Card>
                </div>

                {/* Area principal */}
                <div className="flex-1 min-w-0">
                    {/* Toolbar */}
                    <div className="bg-white rounded-xl border border-gray-200 px-4 py-3 mb-4 flex items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                            <div className="flex items-center gap-1 text-sm">
                                <Link href="/repositorio" className="text-blue-600 hover:underline">
                                    <i className="fas fa-home text-xs" />
                                </Link>
                                {crumbs.map((c, i) => (
                                    <span key={c.id} className="flex items-center gap-1">
                                        <i className="fas fa-chevron-right text-[8px] text-gray-300" />
                                        {i === crumbs.length - 1 ? (
                                            <span className="text-gray-700 font-medium">{c.nome}</span>
                                        ) : (
                                            <Link href={`/repositorio?pasta_id=${c.id}`} className="text-blue-600 hover:underline">{c.nome}</Link>
                                        )}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <div className="relative">
                                <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Filtrar..."
                                    className="pl-8 pr-3 py-1.5 rounded-lg border border-gray-200 text-sm w-48 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                />
                            </div>

                            <div className="flex border border-gray-200 rounded-lg overflow-hidden">
                                <button onClick={() => setViewMode('grid')}
                                    className={`px-3 py-1.5 text-xs ${viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'}`}>
                                    <i className="fas fa-th" />
                                </button>
                                <button onClick={() => setViewMode('list')}
                                    className={`px-3 py-1.5 text-xs ${viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'}`}>
                                    <i className="fas fa-list" />
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Subpastas */}
                    {folders.filter(f => f.parent_id === (pasta_atual?.id || null)).length > 0 && (
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                            {folders.filter(f => f.parent_id === (pasta_atual?.id || null)).map(folder => (
                                <FolderCard
                                    key={folder.id}
                                    folder={folder}
                                    onRename={setRenamePasta}
                                    onDelete={setDeletePasta}
                                    onInativar={setInativarPasta}
                                />
                            ))}
                        </div>
                    )}

                    {/* Documentos */}
                    {viewMode === 'grid' ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            {filteredDocs(docs, search).map(doc => (
                                <Link key={doc.id} href={`/documentos/${doc.id}`}
                                    className="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-blue-200 transition-all group">
                                    <div className="w-full h-32 bg-gray-50 rounded-lg flex items-center justify-center mb-3">
                                        <i className={`${getFileIcon(doc.mime_type)} text-3xl text-gray-300 group-hover:text-blue-400 transition-colors`} />
                                    </div>
                                    <p className="text-sm font-medium text-gray-700 truncate">{doc.nome}</p>
                                    <div className="flex items-center justify-between mt-2">
                                        <span className="text-xs text-gray-400">{formatBytes(doc.tamanho)}</span>
                                        <StatusBadge status={doc.status} />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold">Nome</th>
                                        <th className="px-4 py-3 text-left font-semibold">Tipo</th>
                                        <th className="px-4 py-3 text-left font-semibold">Tamanho</th>
                                        <th className="px-4 py-3 text-left font-semibold">Status</th>
                                        <th className="px-4 py-3 text-left font-semibold">Modificado</th>
                                        <th className="px-4 py-3 text-center font-semibold w-24">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filteredDocs(docs, search).map(doc => (
                                        <tr key={doc.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <Link href={`/documentos/${doc.id}`} className="flex items-center gap-3 text-gray-700 hover:text-blue-600">
                                                    <i className={`${getFileIcon(doc.mime_type)} text-gray-400`} />
                                                    <span className="font-medium truncate max-w-xs">{doc.nome}</span>
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-gray-500">{doc.tipo_nome || '-'}</td>
                                            <td className="px-4 py-3 text-gray-500">{formatBytes(doc.tamanho)}</td>
                                            <td className="px-4 py-3"><StatusBadge status={doc.status} /></td>
                                            <td className="px-4 py-3 text-gray-400 text-xs">{formatDate(doc.updated_at)}</td>
                                            <td className="px-4 py-3 text-center">
                                                <a href={`/documentos/${doc.id}/download`}
                                                    className="text-gray-400 hover:text-blue-600 transition-colors">
                                                    <i className="fas fa-download text-xs" />
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {filteredDocs(docs, search).length === 0 && (
                                <div className="py-12 text-center text-gray-400">
                                    <i className="fas fa-folder-open text-3xl mb-2 block" />
                                    <p>Nenhum documento nesta pasta</p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Pagination */}
                    {documentos?.links && documentos.last_page > 1 && (
                        <div className="mt-4 flex justify-center gap-1">
                            {documentos.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'} preserveScroll
                                    className={`px-3 py-1.5 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white border text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Modais */}
            <NewFolderModal show={showNewFolder} onClose={() => setShowNewFolder(false)} pastaAtualId={pasta_atual?.id} />
            <RenameFolderModal pasta={renamePasta} onClose={() => setRenamePasta(null)} />
            <DeleteFolderModal pasta={deletePasta} onClose={() => setDeletePasta(null)} />
            <InativarFolderModal pasta={inativarPasta} onClose={() => setInativarPasta(null)} />
        </AdminLayout>
    );
}

/* ── Folder Card (grid de subpastas) ── */
function FolderCard({ folder, onRename, onDelete, onInativar }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);

    useEffect(() => {
        const handler = (e) => { if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const hasData = (folder.documentos_count || 0) > 0 || (folder.children_count || 0) > 0;

    return (
        <div className="relative flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-amber-200 transition-all group">
            <Link href={`/repositorio?pasta_id=${folder.id}`} className="flex items-center gap-3 flex-1 min-w-0">
                <div className="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center shrink-0">
                    <i className="fas fa-folder text-amber-500" />
                </div>
                <div className="min-w-0">
                    <p className="text-sm font-medium text-gray-700 truncate">{folder.nome}</p>
                    <p className="text-xs text-gray-400">{folder.documentos_count || 0} docs</p>
                </div>
            </Link>

            {/* Menu 3 pontinhos */}
            <div className="relative" ref={menuRef}>
                <button
                    onClick={(e) => { e.preventDefault(); setMenuOpen(!menuOpen); }}
                    className="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 opacity-0 group-hover:opacity-100 transition-all"
                >
                    <i className="fas fa-ellipsis-v text-xs" />
                </button>

                {menuOpen && (
                    <div className="absolute right-0 top-8 w-44 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 animate-fadeIn">
                        <button
                            onClick={() => { setMenuOpen(false); onRename(folder); }}
                            className="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            <i className="fas fa-pen text-xs text-blue-500 w-4" />
                            Renomear
                        </button>
                        {!hasData ? (
                            <button
                                onClick={() => { setMenuOpen(false); onDelete(folder); }}
                                className="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                            >
                                <i className="fas fa-trash text-xs w-4" />
                                Excluir
                            </button>
                        ) : (
                            <button
                                onClick={() => { setMenuOpen(false); onInativar(folder); }}
                                className="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-amber-600 hover:bg-amber-50"
                            >
                                <i className="fas fa-eye-slash text-xs w-4" />
                                Inativar
                            </button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

/* ── Folder Tree Item ── */
function FolderItem({ folder, currentPastaId, level, onRename, onDelete, onInativar }) {
    const [expanded, setExpanded] = useState(level === 0);
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);
    const hasChildren = folder.children && folder.children.length > 0;
    const isActive = folder.id === currentPastaId;
    const isRoot = folder.id === null;

    useEffect(() => {
        const handler = (e) => { if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    return (
        <div>
            <div
                className={`group flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer transition-colors text-sm
                    ${isActive ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50'}`}
                style={{ paddingLeft: level * 16 + 8 }}
                onContextMenu={(e) => {
                    if (!isRoot) {
                        e.preventDefault();
                        setMenuOpen(true);
                    }
                }}
            >
                {hasChildren ? (
                    <button onClick={() => setExpanded(!expanded)} className="w-4 text-gray-400">
                        <i className={`fas fa-chevron-right text-[8px] transition-transform ${expanded ? 'rotate-90' : ''}`} />
                    </button>
                ) : <span className="w-4" />}
                <Link href={folder.id ? `/repositorio?pasta_id=${folder.id}` : '/repositorio'} className="flex items-center gap-2 flex-1 min-w-0">
                    <i className={`fas fa-folder text-xs ${isActive ? 'text-blue-500' : 'text-amber-400'}`} />
                    <span className="truncate">{folder.nome}</span>
                </Link>

                {/* Menu de contexto na arvore */}
                {!isRoot && (
                    <div className="relative" ref={menuRef}>
                        <button
                            onClick={(e) => { e.stopPropagation(); setMenuOpen(!menuOpen); }}
                            className="w-5 h-5 rounded flex items-center justify-center text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 transition-all"
                        >
                            <i className="fas fa-ellipsis-v text-[9px]" />
                        </button>

                        {menuOpen && (
                            <div className="absolute right-0 top-6 w-40 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50 animate-fadeIn">
                                <button
                                    onClick={() => { setMenuOpen(false); onRename(folder); }}
                                    className="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50"
                                >
                                    <i className="fas fa-pen text-blue-500 w-3" />
                                    Renomear
                                </button>
                                <button
                                    onClick={() => { setMenuOpen(false); onDelete(folder); }}
                                    className="w-full flex items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50"
                                >
                                    <i className="fas fa-trash w-3" />
                                    Excluir
                                </button>
                                <button
                                    onClick={() => { setMenuOpen(false); onInativar(folder); }}
                                    className="w-full flex items-center gap-2 px-3 py-2 text-xs text-amber-600 hover:bg-amber-50"
                                >
                                    <i className="fas fa-eye-slash w-3" />
                                    Inativar
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
            {expanded && hasChildren && folder.children.map(child => (
                <FolderItem key={child.id} folder={child} currentPastaId={currentPastaId} level={level + 1}
                    onRename={onRename} onDelete={onDelete} onInativar={onInativar} />
            ))}
        </div>
    );
}

/* ── Modal: Nova Pasta ── */
function NewFolderModal({ show, onClose, pastaAtualId }) {
    const { data, setData, post, processing, errors, reset } = useForm({ nome: '', parent_id: pastaAtualId || '' });

    const submit = (e) => {
        e.preventDefault();
        post('/pastas', { onSuccess: () => { reset(); onClose(); } });
    };

    return (
        <Modal show={show} onClose={onClose} title="Nova Pasta">
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome da Pasta</label>
                    <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                        className="ds-input" placeholder="Nome da pasta" autoFocus />
                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-folder-plus">Criar Pasta</Button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Modal: Renomear Pasta ── */
function RenameFolderModal({ pasta, onClose }) {
    const { data, setData, put, processing, errors } = useForm({ nome: pasta?.nome || '', descricao: pasta?.descricao || '' });

    useEffect(() => {
        if (pasta) {
            setData({ nome: pasta.nome, descricao: pasta.descricao || '' });
        }
    }, [pasta]);

    const submit = (e) => {
        e.preventDefault();
        put(`/pastas/${pasta.id}`, { onSuccess: onClose });
    };

    if (!pasta) return null;

    return (
        <Modal show={!!pasta} onClose={onClose} title="Renomear Pasta">
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                    <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                        className="ds-input" autoFocus />
                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                    <input type="text" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)}
                        className="ds-input" placeholder="Opcional" />
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-pen">Renomear</Button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Modal: Excluir Pasta ── */
function DeleteFolderModal({ pasta, onClose }) {
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.delete(`/pastas/${pasta.id}`, {
            onSuccess: onClose,
            onFinish: () => setProcessing(false),
        });
    };

    if (!pasta) return null;

    return (
        <Modal show={!!pasta} onClose={onClose} title="Excluir Pasta">
            <div className="space-y-4">
                <div className="flex items-start gap-3 p-4 bg-red-50 rounded-xl">
                    <i className="fas fa-exclamation-triangle text-red-500 mt-0.5" />
                    <div>
                        <p className="text-sm font-medium text-red-800">Tem certeza que deseja excluir?</p>
                        <p className="text-sm text-red-600 mt-1">
                            A pasta <strong>"{pasta.nome}"</strong> sera excluida permanentemente.
                            Esta acao nao pode ser desfeita.
                        </p>
                    </div>
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant="danger" onClick={submit} loading={processing} icon="fas fa-trash">Excluir</Button>
                </div>
            </div>
        </Modal>
    );
}

/* ── Modal: Inativar Pasta ── */
function InativarFolderModal({ pasta, onClose }) {
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(`/pastas/${pasta.id}/inativar`, {}, {
            onSuccess: onClose,
            onFinish: () => setProcessing(false),
        });
    };

    if (!pasta) return null;

    return (
        <Modal show={!!pasta} onClose={onClose} title="Inativar Pasta">
            <div className="space-y-4">
                <div className="flex items-start gap-3 p-4 bg-amber-50 rounded-xl">
                    <i className="fas fa-eye-slash text-amber-500 mt-0.5" />
                    <div>
                        <p className="text-sm font-medium text-amber-800">Inativar pasta</p>
                        <p className="text-sm text-amber-600 mt-1">
                            A pasta <strong>"{pasta.nome}"</strong> e suas subpastas serao ocultadas
                            do sistema. Os documentos serao preservados e a pasta podera ser reativada
                            posteriormente.
                        </p>
                    </div>
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant="accent" onClick={submit} loading={processing} icon="fas fa-eye-slash">Inativar</Button>
                </div>
            </div>
        </Modal>
    );
}

/* ── Helpers ── */

function StatusBadge({ status }) {
    const map = {
        publicado: { bg: 'bg-green-100 text-green-700', label: 'Publicado' },
        rascunho: { bg: 'bg-yellow-100 text-yellow-700', label: 'Rascunho' },
        arquivado: { bg: 'bg-gray-100 text-gray-600', label: 'Arquivado' },
    };
    const s = map[status] || { bg: 'bg-gray-100 text-gray-500', label: status };
    return <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${s.bg}`}>{s.label}</span>;
}

function buildTree(folders) {
    const map = {};
    const roots = [];
    folders.forEach(f => { map[f.id] = { ...f, children: [] }; });
    folders.forEach(f => {
        if (f.parent_id && map[f.parent_id]) map[f.parent_id].children.push(map[f.id]);
        else roots.push(map[f.id]);
    });
    return roots;
}

function filteredDocs(docs, search) {
    if (!search) return docs;
    const q = search.toLowerCase();
    return docs.filter(d => d.nome.toLowerCase().includes(q));
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-400';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-400';
    if (mime.includes('word') || mime.includes('document')) return 'fas fa-file-word text-blue-400';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-400';
    if (mime.includes('presentation') || mime.includes('powerpoint')) return 'fas fa-file-powerpoint text-orange-400';
    if (mime.includes('text')) return 'fas fa-file-alt text-gray-400';
    if (mime.includes('zip') || mime.includes('rar') || mime.includes('tar')) return 'fas fa-file-archive text-amber-400';
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
    return new Date(dateStr).toLocaleDateString('pt-BR');
}
