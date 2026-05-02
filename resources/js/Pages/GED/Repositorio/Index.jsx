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

export default function Repositorio({ pastas, documentos, pasta_atual, breadcrumb, tipos_documentais = [], filtros = {} }) {
    const [viewMode, setViewMode] = useState('list');
    const [selectedDoc, setSelectedDoc] = useState(null);
    const [showNewFolder, setShowNewFolder] = useState(false);

    // Filtros server-side
    const [busca, setBusca]         = useState(filtros.busca || '');
    const [tipoDocId, setTipoDocId] = useState(filtros.tipo_documental_id || '');
    const [status, setStatus]       = useState(filtros.status || '');
    const [dataDe, setDataDe]       = useState(filtros.data_de || '');
    const [dataAte, setDataAte]     = useState(filtros.data_ate || '');

    // Modais de acao em pasta
    const [renamePasta, setRenamePasta] = useState(null);
    const [deletePasta, setDeletePasta] = useState(null);
    const [inativarPasta, setInativarPasta] = useState(null);

    const docs = documentos?.data || documentos || [];
    const folders = pastas || [];
    const crumbs = breadcrumb || [];

    const aplicarFiltros = (e) => {
        e?.preventDefault();
        router.get('/repositorio', {
            pasta_id: pasta_atual?.id || undefined,
            busca: busca || undefined,
            tipo_documental_id: tipoDocId || undefined,
            status: status || undefined,
            data_de: dataDe || undefined,
            data_ate: dataAte || undefined,
        }, { preserveState: true, replace: true });
    };

    const limparFiltros = () => {
        setBusca(''); setTipoDocId(''); setStatus(''); setDataDe(''); setDataAte('');
        router.get('/repositorio', { pasta_id: pasta_atual?.id || undefined }, { preserveState: true, replace: true });
    };

    const temFiltro = busca || tipoDocId || status || dataDe || dataAte;

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
                    {/* Breadcrumb + view mode */}
                    <div className="bg-white rounded-xl border border-gray-200 px-4 py-2.5 mb-3 flex items-center justify-between gap-4">
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
                            <span className="text-xs text-gray-400 ml-3">
                                {documentos?.total ?? docs.length} documento(s)
                            </span>
                        </div>

                        <div className="flex border border-gray-200 rounded-lg overflow-hidden">
                            <button onClick={() => setViewMode('grid')}
                                className={`px-3 py-1.5 text-xs ${viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'}`} title="Visao em grade">
                                <i className="fas fa-th" />
                            </button>
                            <button onClick={() => setViewMode('list')}
                                className={`px-3 py-1.5 text-xs ${viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'}`} title="Visao em lista">
                                <i className="fas fa-list" />
                            </button>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white rounded-xl border border-gray-200 p-3 mb-3">
                        <form onSubmit={aplicarFiltros} className="flex flex-wrap items-end gap-2">
                            <div className="relative flex-1 min-w-[260px]">
                                <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Buscar</label>
                                <i className="fas fa-search absolute left-3 top-[60%] -translate-y-1/2 text-gray-400 text-xs" />
                                <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                                    placeholder="Nome ou descricao..." className="ds-input pl-9" />
                            </div>
                            <div>
                                <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Tipo</label>
                                <select value={tipoDocId} onChange={(e) => setTipoDocId(e.target.value)} className="ds-input w-44">
                                    <option value="">Todos</option>
                                    {tipos_documentais.map(t => (
                                        <option key={t.id} value={t.id}>{t.nome}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Status</label>
                                <select value={status} onChange={(e) => setStatus(e.target.value)} className="ds-input w-36">
                                    <option value="">Todos</option>
                                    <option value="rascunho">Rascunho</option>
                                    <option value="publicado">Publicado</option>
                                    <option value="arquivado">Arquivado</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">De</label>
                                <input type="date" value={dataDe} onChange={(e) => setDataDe(e.target.value)} className="ds-input w-40" />
                            </div>
                            <div>
                                <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Ate</label>
                                <input type="date" value={dataAte} onChange={(e) => setDataAte(e.target.value)} className="ds-input w-40" />
                            </div>
                            <Button type="submit" icon="fas fa-filter">Filtrar</Button>
                            {temFiltro && (
                                <button type="button" onClick={limparFiltros} className="text-xs text-gray-500 hover:text-gray-800 px-2 pb-2">
                                    <i className="fas fa-times mr-1" />Limpar
                                </button>
                            )}
                        </form>
                    </div>

                    {/* Subpastas (apenas quando estiver dentro de alguma pasta — na raiz, usa-se a arvore lateral) */}
                    {pasta_atual && folders.filter(f => f.parent_id === pasta_atual.id).length > 0 && (
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                            {folders.filter(f => f.parent_id === pasta_atual.id).map(folder => (
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
                            {docs.map(doc => (
                                <Link key={doc.id} href={`/documentos/${doc.id}`}
                                    className="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-blue-200 transition-all group">
                                    <div className="w-full h-32 bg-gray-50 rounded-lg flex items-center justify-center mb-3">
                                        <i className={`${getFileIcon(doc.mime_type)} text-3xl text-gray-300 group-hover:text-blue-400 transition-colors`} />
                                    </div>
                                    <p className="text-sm font-medium text-gray-700 truncate" title={doc.nome}>{doc.nome}</p>
                                    {doc.tipo_nome && (
                                        <p className="text-[10px] text-gray-400 mt-0.5">{doc.tipo_nome}</p>
                                    )}
                                    <div className="flex items-center justify-between mt-2">
                                        <span className="text-xs text-gray-400">{formatBytes(doc.tamanho)}</span>
                                        <StatusBadge status={doc.status} />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50 text-gray-500 uppercase text-[10px]">
                                        <tr>
                                            <th className="px-3 py-2.5 text-left font-semibold">Nome</th>
                                            <th className="px-3 py-2.5 text-left font-semibold">Tipo</th>
                                            {! pasta_atual && (
                                                <th className="px-3 py-2.5 text-left font-semibold">Pasta</th>
                                            )}
                                            <th className="px-3 py-2.5 text-left font-semibold">Autor</th>
                                            <th className="px-3 py-2.5 text-left font-semibold">Tamanho</th>
                                            <th className="px-3 py-2.5 text-left font-semibold">Status</th>
                                            <th className="px-3 py-2.5 text-left font-semibold">Criado em</th>
                                            <th className="px-3 py-2.5 text-left font-semibold">Modificado</th>
                                            <th className="px-3 py-2.5 text-center font-semibold w-32">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {docs.map(doc => (
                                            <tr key={doc.id} className="hover:bg-gray-50">
                                                <td className="px-3 py-2.5">
                                                    <Link href={`/documentos/${doc.id}`} className="flex items-center gap-2 text-gray-700 hover:text-blue-600 group">
                                                        <i className={`${getFileIcon(doc.mime_type)} shrink-0`} />
                                                        <div className="min-w-0">
                                                            <p className="font-medium truncate max-w-[280px] group-hover:text-blue-600">{doc.nome}</p>
                                                            {doc.descricao && (
                                                                <p className="text-[10px] text-gray-400 truncate max-w-[280px]">{doc.descricao}</p>
                                                            )}
                                                        </div>
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2.5 text-gray-500 text-xs">{doc.tipo_nome || '-'}</td>
                                                {! pasta_atual && (
                                                    <td className="px-3 py-2.5 text-xs">
                                                        {doc.pasta_nome ? (
                                                            <Link href={`/repositorio?pasta_id=${doc.pasta_id}`}
                                                                className="inline-flex items-center gap-1 text-blue-600 hover:underline">
                                                                <i className="fas fa-folder text-[10px] text-amber-400" />
                                                                {doc.pasta_nome}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-gray-400 italic">Sem pasta</span>
                                                        )}
                                                    </td>
                                                )}
                                                <td className="px-3 py-2.5 text-gray-500 text-xs">{doc.autor_nome || '-'}</td>
                                                <td className="px-3 py-2.5 text-gray-500 text-xs whitespace-nowrap">{formatBytes(doc.tamanho)}</td>
                                                <td className="px-3 py-2.5"><StatusBadge status={doc.status} /></td>
                                                <td className="px-3 py-2.5 text-gray-400 text-[11px] whitespace-nowrap">{formatDate(doc.created_at)}</td>
                                                <td className="px-3 py-2.5 text-gray-400 text-[11px] whitespace-nowrap">{formatDate(doc.updated_at)}</td>
                                                <td className="px-3 py-2.5">
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        <Link href={`/documentos/${doc.id}`}
                                                            className="text-[11px] px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700"
                                                            title="Abrir">
                                                            <i className="fas fa-eye" />
                                                        </Link>
                                                        <a href={`/documentos/${doc.id}/download`}
                                                            className="text-[11px] px-2 py-1 rounded bg-white border border-gray-200 text-gray-600 hover:bg-gray-50"
                                                            title="Baixar">
                                                            <i className="fas fa-download" />
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {docs.length === 0 && (
                                <div className="py-12 text-center text-gray-400">
                                    <i className="fas fa-folder-open text-3xl mb-2 block" />
                                    <p className="text-sm font-medium">Nenhum documento {temFiltro ? 'encontrado com esses filtros' : 'nesta pasta'}</p>
                                    {temFiltro && (
                                        <button onClick={limparFiltros} className="text-xs text-blue-600 hover:underline mt-2">
                                            Limpar filtros
                                        </button>
                                    )}
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
            <NewFolderModal show={showNewFolder} onClose={() => setShowNewFolder(false)} pastaAtualId={pasta_atual?.id} pastaAtualNome={pasta_atual?.nome} pastas={folders} />
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
function NewFolderModal({ show, onClose, pastaAtualId, pastaAtualNome, pastas = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        nome: '',
        descricao: '',
        parent_id: pastaAtualId || '',
    });

    // Reset parent_id quando pastaAtualId muda (ao abrir o modal)
    useEffect(() => {
        if (show) {
            setData(d => ({ ...d, parent_id: pastaAtualId || '' }));
        }
    }, [show, pastaAtualId]);

    // Lista hierarquica de pastas para o dropdown
    const pastasTree = (() => {
        const filhosPor = new Map();
        pastas.forEach(p => {
            const pid = p.parent_id ?? null;
            if (! filhosPor.has(pid)) filhosPor.set(pid, []);
            filhosPor.get(pid).push(p);
        });
        for (const [, l] of filhosPor) l.sort((a, b) => a.nome.localeCompare(b.nome));
        const out = [];
        const visit = (pid, nivel) => {
            for (const p of (filhosPor.get(pid) || [])) {
                out.push({ ...p, nivel });
                visit(p.id, nivel + 1);
            }
        };
        visit(null, 0);
        return out;
    })();

    const submit = (e) => {
        e.preventDefault();
        post('/pastas', { onSuccess: () => { reset(); onClose(); } });
    };

    const paiNome = data.parent_id
        ? (pastas.find(p => String(p.id) === String(data.parent_id))?.nome || pastaAtualNome)
        : null;

    return (
        <Modal show={show} onClose={onClose} title="Nova Pasta">
            <form onSubmit={submit} className="space-y-4">
                <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                    <i className="fas fa-info-circle mr-1" />
                    {paiNome
                        ? <>A nova pasta sera criada <strong>dentro de "{paiNome}"</strong> (subpasta).</>
                        : <>A nova pasta sera criada na <strong>raiz</strong> do repositorio.</>
                    }
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nome da Pasta <span className="text-red-500">*</span>
                    </label>
                    <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                        className="ds-input" placeholder="Ex: Contabilidade, Atos Oficiais..." autoFocus />
                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Descricao <span className="text-gray-400 text-xs">(opcional)</span>
                    </label>
                    <input type="text" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)}
                        className="ds-input" placeholder="Ex: Documentos contabeis arquivados em definitivo" />
                    <p className="mt-1 text-[10px] text-gray-400">
                        Aparece quando o usuario for arquivar um documento — ajuda a saber qual pasta escolher.
                    </p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Pasta pai <span className="text-gray-400 text-xs">(onde criar)</span>
                    </label>
                    <select value={data.parent_id || ''}
                        onChange={(e) => setData('parent_id', e.target.value || '')}
                        className="ds-input">
                        <option value="">— Raiz (sem pasta pai) —</option>
                        {pastasTree.map(p => (
                            <option key={p.id} value={p.id}>
                                {'— '.repeat(p.nivel)}{p.nome}
                            </option>
                        ))}
                    </select>
                    {errors.parent_id && <p className="mt-1 text-xs text-red-600">{errors.parent_id}</p>}
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-folder-plus" disabled={! data.nome.trim()}>
                        Criar Pasta
                    </Button>
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
