/**
 * Busca Avancada — GED
 *
 * Busca fulltext com filtros, resultados com acoes e buscas salvas.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function Busca({ resultados, filtros_aplicados, tipos_documentais, pastas, buscas_salvas }) {
    const docs = resultados?.data || resultados || [];
    const tipos = tipos_documentais || [];
    const pastaList = pastas || [];
    const salvas = buscas_salvas || [];
    const fa = filtros_aplicados || {};

    const { data, setData, get, processing } = useForm({
        q: fa.q || '',
        tipo_documental_id: fa.tipo_documental_id || '',
        status: fa.status || '',
        classificacao: fa.classificacao || '',
        pasta_id: fa.pasta_id || '',
        data_inicio: fa.data_inicio || '',
        data_fim: fa.data_fim || '',
    });

    const [expandedFilters, setExpandedFilters] = useState({
        tipo: !!fa.tipo_documental_id,
        pasta: !!fa.pasta_id,
        data: !!(fa.data_inicio || fa.data_fim),
        status: !!fa.status,
        classificacao: !!fa.classificacao,
    });

    const toggleFilter = (key) => setExpandedFilters(prev => ({ ...prev, [key]: !prev[key] }));

    const submit = (e) => {
        e.preventDefault();
        get('/busca', { preserveState: true });
    };

    const limpar = () => router.get('/busca');

    const salvarBusca = () => {
        const nome = prompt('Nome para esta busca:');
        if (nome) router.post('/busca/salvar', { nome, filtros: data });
    };

    const hasFilters = Object.values(fa).some(v => v);

    return (
        <AdminLayout>
            <Head title="Busca Avancada" />
            <PageHeader title="Busca Avancada" subtitle="Encontrar documentos por criterios complexos" />

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                {/* Filtros */}
                <div className="lg:col-span-1">
                    <form onSubmit={submit}>
                        <Card title="Filtros">
                            {/* Busca fulltext */}
                            <div className="mb-4">
                                <label className="block text-xs font-medium text-gray-600 mb-1">Buscar texto</label>
                                <div className="relative">
                                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                                    <input type="text" value={data.q} onChange={(e) => setData('q', e.target.value)}
                                        className="ds-input !pl-9" placeholder="Nome, descricao, metadados..." />
                                </div>
                                <p className="text-[10px] text-gray-400 mt-1">Busca no conteudo e metadados</p>
                            </div>

                            <FilterAccordion title="Tipo Documental" expanded={expandedFilters.tipo} onToggle={() => toggleFilter('tipo')}>
                                <select value={data.tipo_documental_id} onChange={(e) => setData('tipo_documental_id', e.target.value)} className="ds-input">
                                    <option value="">Todos</option>
                                    {tipos.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}
                                </select>
                            </FilterAccordion>

                            <FilterAccordion title="Pasta" expanded={expandedFilters.pasta} onToggle={() => toggleFilter('pasta')}>
                                <select value={data.pasta_id} onChange={(e) => setData('pasta_id', e.target.value)} className="ds-input">
                                    <option value="">Todas</option>
                                    {pastaList.map(p => <option key={p.id} value={p.id}>{p.nome}</option>)}
                                </select>
                            </FilterAccordion>

                            <FilterAccordion title="Status" expanded={expandedFilters.status} onToggle={() => toggleFilter('status')}>
                                <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="ds-input">
                                    <option value="">Todos</option>
                                    <option value="rascunho">Rascunho</option>
                                    <option value="revisao">Em Revisao</option>
                                    <option value="publicado">Publicado</option>
                                    <option value="arquivado">Arquivado</option>
                                </select>
                            </FilterAccordion>

                            <FilterAccordion title="Classificacao" expanded={expandedFilters.classificacao} onToggle={() => toggleFilter('classificacao')}>
                                <select value={data.classificacao} onChange={(e) => setData('classificacao', e.target.value)} className="ds-input">
                                    <option value="">Todas</option>
                                    <option value="publico">Publico</option>
                                    <option value="interno">Interno</option>
                                    <option value="confidencial">Confidencial</option>
                                    <option value="restrito">Restrito</option>
                                </select>
                            </FilterAccordion>

                            <FilterAccordion title="Periodo" expanded={expandedFilters.data} onToggle={() => toggleFilter('data')}>
                                <div className="space-y-2">
                                    <div>
                                        <label className="text-[10px] text-gray-500">De</label>
                                        <input type="date" value={data.data_inicio} onChange={(e) => setData('data_inicio', e.target.value)} className="ds-input" />
                                    </div>
                                    <div>
                                        <label className="text-[10px] text-gray-500">Ate</label>
                                        <input type="date" value={data.data_fim} onChange={(e) => setData('data_fim', e.target.value)} className="ds-input" />
                                    </div>
                                </div>
                            </FilterAccordion>

                            <div className="flex gap-2 mt-4">
                                <Button type="submit" loading={processing} className="flex-1 justify-center" icon="fas fa-search">
                                    Buscar
                                </Button>
                                <Button variant="ghost" type="button" onClick={limpar}>Limpar</Button>
                            </div>

                            {hasFilters && (
                                <button type="button" onClick={salvarBusca}
                                    className="w-full mt-2 text-xs text-blue-600 hover:text-blue-800 font-medium py-2">
                                    <i className="fas fa-bookmark mr-1" />Salvar esta busca
                                </button>
                            )}
                        </Card>
                    </form>

                    {/* Buscas salvas */}
                    {salvas.length > 0 && (
                        <Card title="Buscas Salvas" className="mt-4">
                            <div className="space-y-1">
                                {salvas.map(s => (
                                    <div key={s.id} className="flex items-center justify-between group hover:bg-blue-50 rounded-lg transition-colors">
                                        <button onClick={() => router.get('/busca', s.filtros)}
                                            className="flex-1 text-left text-sm text-gray-600 hover:text-blue-600 px-3 py-2">
                                            <i className="fas fa-bookmark text-xs text-gray-300 mr-2" />
                                            {s.nome}
                                        </button>
                                        <button onClick={() => router.delete(`/busca/salvar/${s.id}`)}
                                            className="text-gray-300 hover:text-red-500 px-2 opacity-0 group-hover:opacity-100 transition-all">
                                            <i className="fas fa-times text-xs" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}
                </div>

                {/* Resultados */}
                <div className="lg:col-span-3">
                    {resultados && (
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-gray-500">
                                {resultados.total !== undefined
                                    ? `${resultados.total} resultado(s) encontrado(s)`
                                    : `${docs.length} resultado(s)`}
                            </p>
                        </div>
                    )}

                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold">Documento</th>
                                        <th className="px-4 py-3 text-left font-semibold">Tipo</th>
                                        <th className="px-4 py-3 text-left font-semibold">Status</th>
                                        <th className="px-4 py-3 text-left font-semibold">Pasta</th>
                                        <th className="px-4 py-3 text-left font-semibold">Tamanho</th>
                                        <th className="px-4 py-3 text-left font-semibold">Autor</th>
                                        <th className="px-4 py-3 text-left font-semibold">Data</th>
                                        <th className="px-4 py-3 text-center font-semibold w-24">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {docs.map(doc => (
                                        <tr key={doc.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <Link href={`/documentos/${doc.id}`} className="flex items-center gap-2.5 text-gray-700 hover:text-blue-600">
                                                    <div className="w-8 h-10 bg-gray-100 rounded flex items-center justify-center shrink-0">
                                                        <i className={`${getFileIcon(doc.mime_type)} text-sm`} />
                                                    </div>
                                                    <span className="font-medium truncate max-w-[200px]">{doc.nome}</span>
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{doc.tipo_documental_nome || '-'}</td>
                                            <td className="px-4 py-3">
                                                <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${statusBg(doc.status)}`}>
                                                    {doc.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-gray-500">{doc.pasta_nome || 'Raiz'}</td>
                                            <td className="px-4 py-3 text-xs text-gray-500">{formatBytes(doc.tamanho)}</td>
                                            <td className="px-4 py-3 text-xs text-gray-500">{doc.autor_nome || '-'}</td>
                                            <td className="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                                                {doc.created_at ? new Date(doc.created_at).toLocaleDateString('pt-BR') : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <div className="flex items-center justify-center gap-1">
                                                    <a href={`/documentos/${doc.id}/download`}
                                                        className="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50"
                                                        title="Download">
                                                        <i className="fas fa-download text-xs" />
                                                    </a>
                                                    <Link href={`/documentos/${doc.id}`}
                                                        className="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50"
                                                        title="Abrir">
                                                        <i className="fas fa-eye text-xs" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {(!resultados || docs.length === 0) && (
                            <div className="py-16 text-center text-gray-400">
                                <i className="fas fa-search text-4xl mb-3 block" />
                                <p className="text-lg font-medium">
                                    {resultados ? 'Nenhum documento encontrado' : 'Use os filtros para buscar'}
                                </p>
                                <p className="text-sm mt-1">
                                    {resultados ? 'Tente ajustar os filtros' : 'Preencha os criterios e clique em Buscar'}
                                </p>
                            </div>
                        )}
                    </div>

                    {resultados?.links && resultados.last_page > 1 && (
                        <div className="mt-4 flex justify-center gap-1">
                            {resultados.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'} preserveScroll
                                    className={`px-3 py-1.5 text-xs rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white border text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

function FilterAccordion({ title, expanded: initialExpanded, onToggle, children }) {
    const [expanded, setExpanded] = useState(initialExpanded);
    return (
        <div className="border-t border-gray-100 py-3">
            <button type="button" onClick={() => { setExpanded(!expanded); onToggle?.(); }}
                className="flex items-center justify-between w-full text-sm font-medium text-gray-700">
                {title}
                <i className={`fas fa-chevron-down text-[8px] text-gray-400 transition-transform ${expanded ? 'rotate-180' : ''}`} />
            </button>
            {expanded && <div className="mt-2">{children}</div>}
        </div>
    );
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-400';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-400';
    if (mime.includes('word')) return 'fas fa-file-word text-blue-400';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-400';
    return 'fas fa-file text-gray-400';
}

function statusBg(s) {
    const map = {
        publicado: 'bg-green-100 text-green-700',
        rascunho: 'bg-yellow-100 text-yellow-700',
        revisao: 'bg-blue-100 text-blue-700',
        arquivado: 'bg-gray-100 text-gray-600',
    };
    return map[s] || 'bg-gray-100 text-gray-500';
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
