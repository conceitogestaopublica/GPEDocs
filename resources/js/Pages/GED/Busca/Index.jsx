/**
 * Busca Avancada — GED
 *
 * Busca full-text com filtros em acordeao e resultados paginados.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function Busca({ resultados, filtros_aplicados, tipos_documentais, buscas_salvas }) {
    const docs = resultados?.data || resultados || [];
    const tipos = tipos_documentais || [];
    const salvas = buscas_salvas || [];
    const fa = filtros_aplicados || {};

    const { data, setData, get, processing } = useForm({
        q: fa.q || '',
        tipo_documental_id: fa.tipo_documental_id || '',
        status: fa.status || '',
        classificacao: fa.classificacao || '',
        data_inicio: fa.data_inicio || '',
        data_fim: fa.data_fim || '',
    });

    const [expandedFilters, setExpandedFilters] = useState({
        tipo: !!fa.tipo_documental_id,
        data: !!(fa.data_inicio || fa.data_fim),
        status: !!fa.status,
    });

    const submit = (e) => {
        e.preventDefault();
        get('/busca', { preserveState: true });
    };

    const limpar = () => {
        router.get('/busca');
    };

    const salvarBusca = () => {
        const nome = prompt('Nome para esta busca:');
        if (nome) {
            router.post('/busca/salvar', { nome, filtros: data });
        }
    };

    return (
        <AdminLayout>
            <Head title="Busca Avancada" />
            <PageHeader title="Busca Avancada" subtitle="Encontrar documentos por criterios complexos" />

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                {/* Filtros */}
                <div className="lg:col-span-1">
                    <form onSubmit={submit}>
                        <Card title="Filtros">
                            {/* Busca full-text */}
                            <div className="mb-4">
                                <label className="block text-xs font-medium text-gray-600 mb-1">Buscar texto</label>
                                <div className="relative">
                                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                                    <input type="text" value={data.q} onChange={(e) => setData('q', e.target.value)}
                                        className="ds-input !pl-9" placeholder="Buscar no conteudo..." />
                                </div>
                                <p className="text-[10px] text-gray-400 mt-1">Use aspas para busca exata</p>
                            </div>

                            {/* Acordeao: Tipo */}
                            <FilterAccordion
                                title="Tipo Documental"
                                expanded={expandedFilters.tipo}
                                onToggle={() => setExpandedFilters({ ...expandedFilters, tipo: !expandedFilters.tipo })}
                            >
                                <select value={data.tipo_documental_id} onChange={(e) => setData('tipo_documental_id', e.target.value)}
                                    className="ds-input">
                                    <option value="">Todos</option>
                                    {tipos.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}
                                </select>
                            </FilterAccordion>

                            {/* Acordeao: Status */}
                            <FilterAccordion
                                title="Status"
                                expanded={expandedFilters.status}
                                onToggle={() => setExpandedFilters({ ...expandedFilters, status: !expandedFilters.status })}
                            >
                                <select value={data.status} onChange={(e) => setData('status', e.target.value)}
                                    className="ds-input">
                                    <option value="">Todos</option>
                                    <option value="rascunho">Rascunho</option>
                                    <option value="publicado">Publicado</option>
                                    <option value="arquivado">Arquivado</option>
                                </select>
                            </FilterAccordion>

                            {/* Acordeao: Classificacao */}
                            <FilterAccordion
                                title="Classificacao"
                                expanded={false}
                                onToggle={() => {}}
                            >
                                <select value={data.classificacao} onChange={(e) => setData('classificacao', e.target.value)}
                                    className="ds-input">
                                    <option value="">Todas</option>
                                    <option value="publico">Publico</option>
                                    <option value="interno">Interno</option>
                                    <option value="confidencial">Confidencial</option>
                                    <option value="restrito">Restrito</option>
                                </select>
                            </FilterAccordion>

                            {/* Acordeao: Periodo */}
                            <FilterAccordion
                                title="Periodo"
                                expanded={expandedFilters.data}
                                onToggle={() => setExpandedFilters({ ...expandedFilters, data: !expandedFilters.data })}
                            >
                                <div className="space-y-2">
                                    <input type="date" value={data.data_inicio} onChange={(e) => setData('data_inicio', e.target.value)}
                                        className="ds-input" />
                                    <input type="date" value={data.data_fim} onChange={(e) => setData('data_fim', e.target.value)}
                                        className="ds-input" />
                                </div>
                            </FilterAccordion>

                            <div className="flex gap-2 mt-4">
                                <Button type="submit" loading={processing} className="flex-1 justify-center" icon="fas fa-search">
                                    Buscar
                                </Button>
                                <Button variant="ghost" type="button" onClick={limpar}>Limpar</Button>
                            </div>

                            <button type="button" onClick={salvarBusca}
                                className="w-full mt-2 text-xs text-blue-600 hover:text-blue-800 font-medium py-2">
                                <i className="fas fa-bookmark mr-1" />Salvar esta busca
                            </button>
                        </Card>
                    </form>

                    {/* Buscas salvas */}
                    {salvas.length > 0 && (
                        <Card title="Buscas Salvas" className="mt-4">
                            <div className="space-y-1">
                                {salvas.map(s => (
                                    <button key={s.id} onClick={() => router.get('/busca', s.filtros)}
                                        className="w-full text-left text-sm text-gray-600 hover:text-blue-600 hover:bg-blue-50 px-3 py-2 rounded-lg transition-colors">
                                        <i className="fas fa-bookmark text-xs text-gray-300 mr-2" />
                                        {s.nome}
                                    </button>
                                ))}
                            </div>
                        </Card>
                    )}
                </div>

                {/* Resultados */}
                <div className="lg:col-span-3">
                    {/* Contador */}
                    {resultados && (
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-gray-500">
                                {resultados.total !== undefined
                                    ? `Exibindo ${resultados.from || 0}-${resultados.to || 0} de ${resultados.total} resultados`
                                    : `${docs.length} resultados encontrados`}
                            </p>
                        </div>
                    )}

                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Documento</th>
                                    <th className="px-4 py-3 text-left font-semibold">Tipo</th>
                                    <th className="px-4 py-3 text-left font-semibold">Status</th>
                                    <th className="px-4 py-3 text-left font-semibold">Autor</th>
                                    <th className="px-4 py-3 text-left font-semibold">Data</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {docs.map(doc => (
                                    <tr key={doc.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <Link href={`/documentos/${doc.id}`} className="flex items-center gap-3 text-gray-700 hover:text-blue-600">
                                                <i className={`${getFileIcon(doc.mime_type)}`} />
                                                <div>
                                                    <p className="font-medium truncate max-w-xs">{doc.nome}</p>
                                                    {doc.pasta_nome && <p className="text-[10px] text-gray-400">{doc.pasta_nome}</p>}
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-gray-500 text-xs">{doc.tipo_nome || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${statusBg(doc.status)}`}>
                                                {doc.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-500 text-xs">{doc.autor_nome || '-'}</td>
                                        <td className="px-4 py-3 text-gray-400 text-xs">
                                            {doc.created_at ? new Date(doc.created_at).toLocaleDateString('pt-BR') : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {docs.length === 0 && (
                            <div className="py-16 text-center text-gray-400">
                                <i className="fas fa-search text-4xl mb-3 block" />
                                <p className="text-lg font-medium">Nenhum documento encontrado</p>
                                <p className="text-sm mt-1">Tente ajustar os filtros de busca</p>
                            </div>
                        )}
                    </div>

                    {/* Paginacao */}
                    {resultados?.links && resultados.last_page > 1 && (
                        <div className="mt-4 flex justify-center gap-1">
                            {resultados.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'} preserveScroll
                                    className={`px-3 py-1.5 text-sm rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white border text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
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
    const map = { publicado: 'bg-green-100 text-green-700', rascunho: 'bg-yellow-100 text-yellow-700', arquivado: 'bg-gray-100 text-gray-600' };
    return map[s] || 'bg-gray-100 text-gray-500';
}
