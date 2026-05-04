/**
 * Admin — Carta de Servicos (gestao de categorias e servicos da UG ativa).
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Button from '../../../../Components/Button';
import Modal from '../../../../Components/Modal';
import Card from '../../../../Components/Card';

const CORES = ['red', 'blue', 'amber', 'indigo', 'orange', 'green', 'pink', 'cyan'];

const COR_BG = {
    red: 'bg-red-100 text-red-600', blue: 'bg-blue-100 text-blue-600',
    amber: 'bg-amber-100 text-amber-600', indigo: 'bg-indigo-100 text-indigo-600',
    orange: 'bg-orange-100 text-orange-600', green: 'bg-green-100 text-green-600',
    pink: 'bg-pink-100 text-pink-600', cyan: 'bg-cyan-100 text-cyan-600',
};

export default function CartaServicosAdmin({ servicos, categorias, setores, tiposProcesso, filtros, publicos }) {
    const [showCatForm, setShowCatForm] = useState(false);
    const [showServForm, setShowServForm] = useState(false);
    const [editCat, setEditCat] = useState(null);
    const [editServ, setEditServ] = useState(null);
    const [busca, setBusca] = useState(filtros?.q || '');
    const [filtroCat, setFiltroCat] = useState(filtros?.categoria_id || '');
    const [filtroPub, setFiltroPub] = useState(filtros?.publico_alvo || '');

    const aplicarFiltros = (e) => {
        e?.preventDefault();
        const params = {};
        if (busca) params.q = busca;
        if (filtroCat) params.categoria_id = filtroCat;
        if (filtroPub) params.publico_alvo = filtroPub;
        router.get('/configuracoes/carta-servicos', params, { preserveState: true });
    };

    const togglePublicado = (servico) => {
        router.post(`/configuracoes/carta-servicos/servicos/${servico.id}/toggle-publicado`, {}, { preserveScroll: true });
    };

    const excluirServico = (servico) => {
        if (! confirm(`Excluir o servico "${servico.titulo}"?`)) return;
        router.delete(`/configuracoes/carta-servicos/servicos/${servico.id}`, { preserveScroll: true });
    };

    const excluirCategoria = (cat) => {
        if (! confirm(`Excluir a categoria "${cat.nome}"?`)) return;
        router.delete(`/configuracoes/carta-servicos/categorias/${cat.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="Carta de Servicos" />

            <PageHeader
                title="Carta de Servicos"
                subtitle="Catalogo publicado no Portal do Cidadao da sua UG"
            >
                <Button icon="fas fa-tag" onClick={() => { setEditCat(null); setShowCatForm(true); }} variant="secondary">
                    Nova Categoria
                </Button>
                <Button icon="fas fa-plus" onClick={() => { setEditServ(null); setShowServForm(true); }}>
                    Novo Servico
                </Button>
            </PageHeader>

            {/* Categorias - linha de chips */}
            <Card className="mb-4">
                <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-bold text-gray-700">Categorias</h3>
                    <span className="text-xs text-gray-400">{categorias.length} categorias</span>
                </div>
                <div className="flex flex-wrap gap-2">
                    {categorias.map(cat => (
                        <div key={cat.id}
                            className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium ${COR_BG[cat.cor] || 'bg-gray-100 text-gray-700'}`}>
                            <i className={cat.icone || 'fas fa-folder'} />
                            <span>{cat.nome}</span>
                            <span className="opacity-70">({cat.servicos_count})</span>
                            <button onClick={() => { setEditCat(cat); setShowCatForm(true); }}
                                className="ml-1 opacity-60 hover:opacity-100" title="Editar">
                                <i className="fas fa-pen text-[10px]" />
                            </button>
                            <button onClick={() => excluirCategoria(cat)}
                                className="opacity-60 hover:opacity-100 hover:text-red-600" title="Excluir">
                                <i className="fas fa-trash text-[10px]" />
                            </button>
                        </div>
                    ))}
                    {categorias.length === 0 && (
                        <p className="text-xs text-gray-400">Nenhuma categoria cadastrada ainda.</p>
                    )}
                </div>
            </Card>

            {/* Filtros */}
            <form onSubmit={aplicarFiltros} className="bg-white rounded-2xl border border-gray-200 p-4 mb-4 grid grid-cols-1 md:grid-cols-12 gap-3">
                <div className="md:col-span-5 relative">
                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                    <input
                        type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                        placeholder="Buscar por titulo ou descricao..."
                        className="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                    />
                </div>
                <select value={filtroCat} onChange={(e) => setFiltroCat(e.target.value)}
                    className="md:col-span-3 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">Todas categorias</option>
                    {categorias.map(c => <option key={c.id} value={c.id}>{c.nome}</option>)}
                </select>
                <select value={filtroPub} onChange={(e) => setFiltroPub(e.target.value)}
                    className="md:col-span-2 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    <option value="">Todos publicos</option>
                    {Object.entries(publicos).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <button type="submit" className="md:col-span-2 px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    Filtrar
                </button>
            </form>

            {/* Lista de servicos */}
            <Card padding={false}>
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th className="px-4 py-3 text-left font-semibold">Servico</th>
                            <th className="px-4 py-3 text-left font-semibold">Categoria</th>
                            <th className="px-4 py-3 text-center font-semibold">Publico</th>
                            <th className="px-4 py-3 text-center font-semibold">Visualizacoes</th>
                            <th className="px-4 py-3 text-center font-semibold">Status</th>
                            <th className="px-4 py-3 text-center font-semibold w-32">Acoes</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {(servicos.data || []).map(s => (
                            <tr key={s.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <div className="w-9 h-9 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center shrink-0">
                                            <i className={`${s.icone || 'fas fa-file-alt'} text-sm`} />
                                        </div>
                                        <div>
                                            <p className="font-semibold text-gray-800">{s.titulo}</p>
                                            <p className="text-xs text-gray-500 max-w-md truncate">{s.descricao_curta}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    {s.categoria ? (
                                        <span className={`text-[11px] px-2 py-0.5 rounded-full font-medium ${COR_BG[s.categoria.cor] || 'bg-gray-100 text-gray-700'}`}>
                                            {s.categoria.nome}
                                        </span>
                                    ) : <span className="text-xs text-gray-400">—</span>}
                                </td>
                                <td className="px-4 py-3 text-center text-xs text-gray-600">
                                    {publicos[s.publico_alvo] || s.publico_alvo}
                                </td>
                                <td className="px-4 py-3 text-center text-xs text-gray-500">{s.visualizacoes}</td>
                                <td className="px-4 py-3 text-center">
                                    <button
                                        onClick={() => togglePublicado(s)}
                                        className={`text-[10px] px-2.5 py-1 rounded-full font-medium transition-colors
                                            ${s.publicado
                                                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}
                                    >
                                        {s.publicado ? 'Publicado' : 'Rascunho'}
                                    </button>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-center gap-1">
                                        <button onClick={() => { setEditServ(s); setShowServForm(true); }}
                                            className="w-8 h-8 rounded-lg hover:bg-blue-50 text-gray-400 hover:text-blue-600" title="Editar">
                                            <i className="fas fa-pen text-xs" />
                                        </button>
                                        <button onClick={() => excluirServico(s)}
                                            className="w-8 h-8 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600" title="Excluir">
                                            <i className="fas fa-trash text-xs" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {(servicos.data || []).length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400">
                                <i className="fas fa-folder-open text-3xl mb-2 block" />
                                Nenhum servico cadastrado.
                            </td></tr>
                        )}
                    </tbody>
                </table>
            </Card>

            {showCatForm && (
                <CategoriaForm
                    categoria={editCat}
                    onClose={() => setShowCatForm(false)}
                />
            )}

            {showServForm && (
                <ServicoForm
                    servico={editServ}
                    categorias={categorias}
                    setores={setores}
                    tiposProcesso={tiposProcesso}
                    publicos={publicos}
                    onClose={() => setShowServForm(false)}
                />
            )}
        </AdminLayout>
    );
}

// ============ Form Categoria ============
function CategoriaForm({ categoria, onClose }) {
    const isEdit = !!categoria;
    const { data, setData, post, put, processing, errors } = useForm({
        nome: categoria?.nome || '',
        icone: categoria?.icone || 'fas fa-folder',
        cor: categoria?.cor || 'blue',
        descricao: categoria?.descricao || '',
        ordem: categoria?.ordem || 0,
        ativo: categoria?.ativo ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isEdit) put(`/configuracoes/carta-servicos/categorias/${categoria.id}`, opts);
        else post('/configuracoes/carta-servicos/categorias', opts);
    };

    return (
        <Modal show onClose={onClose} title={isEdit ? 'Editar categoria' : 'Nova categoria'} maxWidth="lg">
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Nome *</label>
                    <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                        className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" required />
                    {errors.nome && <p className="text-xs text-red-500 mt-1">{errors.nome}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Icone (FontAwesome)</label>
                        <input type="text" value={data.icone} onChange={(e) => setData('icone', e.target.value)}
                            placeholder="fas fa-folder"
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono" />
                        <p className="text-[10px] text-gray-400 mt-1">Ex: <code>fas fa-heartbeat</code> — veja em fontawesome.com/icons</p>
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Cor</label>
                        <div className="flex flex-wrap gap-1.5 mt-1">
                            {CORES.map(c => (
                                <button key={c} type="button" onClick={() => setData('cor', c)}
                                    className={`w-8 h-8 rounded-lg ${COR_BG[c]} ${data.cor === c ? 'ring-2 ring-offset-2 ring-gray-700' : ''}`}>
                                    {data.cor === c && <i className="fas fa-check text-xs" />}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Descricao</label>
                    <textarea value={data.descricao} onChange={(e) => setData('descricao', e.target.value)}
                        rows={2}
                        className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Ordem</label>
                        <input type="number" value={data.ordem} onChange={(e) => setData('ordem', parseInt(e.target.value) || 0)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </div>
                    {isEdit && (
                        <div className="flex items-end">
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.ativo} onChange={(e) => setData('ativo', e.target.checked)} />
                                <span>Ativa</span>
                            </label>
                        </div>
                    )}
                </div>
                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" disabled={processing}
                        className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                        {isEdit ? 'Salvar' : 'Criar'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

// ============ Form Servico ============
function ServicoForm({ servico, categorias, setores, tiposProcesso, publicos, onClose }) {
    const isEdit = !!servico;
    const { data, setData, post, put, processing, errors } = useForm({
        categoria_id: servico?.categoria_id || '',
        titulo: servico?.titulo || '',
        publico_alvo: servico?.publico_alvo || 'cidadao',
        descricao_curta: servico?.descricao_curta || '',
        descricao_completa: servico?.descricao_completa || '',
        requisitos: servico?.requisitos || '',
        documentos_necessarios: servico?.documentos_necessarios || [],
        prazo_entrega: servico?.prazo_entrega || '',
        custo: servico?.custo || '',
        canais: servico?.canais || { online: false, presencial: false, telefone: '', app: '', observacoes: '' },
        orgao_responsavel: servico?.orgao_responsavel || '',
        setor_responsavel_id: servico?.setor_responsavel_id || '',
        tipo_processo_id: servico?.tipo_processo_id || '',
        permite_anonimo: servico?.permite_anonimo ?? false,
        legislacao: servico?.legislacao || '',
        palavras_chave: servico?.palavras_chave || [],
        icone: servico?.icone || 'fas fa-file-alt',
        publicado: servico?.publicado ?? false,
        ordem: servico?.ordem || 0,
    });

    const [novoDoc, setNovoDoc] = useState('');
    const [novaTag, setNovaTag] = useState('');

    const addDoc = () => {
        if (! novoDoc.trim()) return;
        setData('documentos_necessarios', [...data.documentos_necessarios, novoDoc.trim()]);
        setNovoDoc('');
    };
    const rmDoc = (i) => setData('documentos_necessarios', data.documentos_necessarios.filter((_, j) => j !== i));

    const addTag = () => {
        if (! novaTag.trim()) return;
        setData('palavras_chave', [...data.palavras_chave, novaTag.trim()]);
        setNovaTag('');
    };
    const rmTag = (i) => setData('palavras_chave', data.palavras_chave.filter((_, j) => j !== i));

    const submit = (e) => {
        e.preventDefault();
        const payload = {
            ...data,
            categoria_id: data.categoria_id || null,
            setor_responsavel_id: data.setor_responsavel_id || null,
            tipo_processo_id: data.tipo_processo_id || null,
        };
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isEdit) router.put(`/configuracoes/carta-servicos/servicos/${servico.id}`, payload, opts);
        else router.post('/configuracoes/carta-servicos/servicos', payload, opts);
    };

    return (
        <Modal show onClose={onClose} title={isEdit ? 'Editar servico' : 'Novo servico'} maxWidth="4xl">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="md:col-span-2">
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Titulo *</label>
                        <input type="text" value={data.titulo} onChange={(e) => setData('titulo', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" required />
                        {errors.titulo && <p className="text-xs text-red-500 mt-1">{errors.titulo}</p>}
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Icone</label>
                        <input type="text" value={data.icone} onChange={(e) => setData('icone', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm font-mono" />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Categoria</label>
                        <select value={data.categoria_id} onChange={(e) => setData('categoria_id', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            <option value="">— Sem categoria —</option>
                            {categorias.map(c => <option key={c.id} value={c.id}>{c.nome}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Publico-alvo *</label>
                        <select value={data.publico_alvo} onChange={(e) => setData('publico_alvo', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            {Object.entries(publicos).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </div>
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Descricao curta</label>
                    <textarea value={data.descricao_curta} onChange={(e) => setData('descricao_curta', e.target.value)}
                        rows={2} maxLength={500}
                        className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
                        placeholder="Resumo de 1-2 linhas que aparece nas listagens" />
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Descricao completa</label>
                    <textarea value={data.descricao_completa} onChange={(e) => setData('descricao_completa', e.target.value)}
                        rows={4}
                        className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
                        placeholder="Texto detalhado sobre o servico" />
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Quem pode solicitar / Requisitos</label>
                    <textarea value={data.requisitos} onChange={(e) => setData('requisitos', e.target.value)}
                        rows={3}
                        className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Documentos necessarios</label>
                    <div className="space-y-1.5">
                        {data.documentos_necessarios.map((d, i) => (
                            <div key={i} className="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-1.5">
                                <i className="fas fa-check-circle text-blue-600 text-xs" />
                                <span className="flex-1 text-sm">{d}</span>
                                <button type="button" onClick={() => rmDoc(i)} className="text-gray-400 hover:text-red-500">
                                    <i className="fas fa-times text-xs" />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="flex gap-2 mt-2">
                        <input type="text" value={novoDoc} onChange={(e) => setNovoDoc(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addDoc(); } }}
                            placeholder="Ex: CPF do solicitante"
                            className="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                        <button type="button" onClick={addDoc} className="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
                            <i className="fas fa-plus" /> Adicionar
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Prazo de entrega</label>
                        <input type="text" value={data.prazo_entrega} onChange={(e) => setData('prazo_entrega', e.target.value)}
                            placeholder="Ex: Ate 5 dias uteis"
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Custo</label>
                        <input type="text" value={data.custo} onChange={(e) => setData('custo', e.target.value)}
                            placeholder="Ex: Gratuito | R$ 50,00"
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </div>
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-2 block">Canais de atendimento</label>
                    <div className="bg-gray-50 rounded-lg p-3 space-y-2">
                        <div className="flex gap-4">
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={!!data.canais.online}
                                    onChange={(e) => setData('canais', { ...data.canais, online: e.target.checked })} />
                                <i className="fas fa-globe text-blue-600" /> Online
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={!!data.canais.presencial}
                                    onChange={(e) => setData('canais', { ...data.canais, presencial: e.target.checked })} />
                                <i className="fas fa-map-marker-alt text-blue-600" /> Presencial
                            </label>
                        </div>
                        <input type="text" value={data.canais.telefone || ''}
                            onChange={(e) => setData('canais', { ...data.canais, telefone: e.target.value })}
                            placeholder="Telefone (opcional, ex: 0800-000-0000)"
                            className="w-full px-3 py-1.5 rounded-lg border border-gray-200 text-sm" />
                        <input type="text" value={data.canais.observacoes || ''}
                            onChange={(e) => setData('canais', { ...data.canais, observacoes: e.target.value })}
                            placeholder="Observacoes (opcional)"
                            className="w-full px-3 py-1.5 rounded-lg border border-gray-200 text-sm" />
                    </div>
                </div>

                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-3">
                    <h4 className="text-xs font-bold text-blue-900 uppercase tracking-wider flex items-center gap-2">
                        <i className="fas fa-project-diagram" /> Integracao com GPE Flow
                    </h4>
                    <p className="text-[11px] text-blue-700">Quando configurado, cada solicitacao deste servico abre automaticamente um processo no setor responsavel.</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-semibold text-gray-600 mb-1 block">Setor responsavel (organograma)</label>
                            <select value={data.setor_responsavel_id} onChange={(e) => setData('setor_responsavel_id', e.target.value)}
                                className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm bg-white">
                                <option value="">— Nenhum (sem processo no Flow) —</option>
                                {setores.map(s => <option key={s.id} value={s.id}>{s.nome}{s.nivel ? ` — N${s.nivel}` : ''}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-600 mb-1 block">Tipo de processo</label>
                            <select value={data.tipo_processo_id} onChange={(e) => setData('tipo_processo_id', e.target.value)}
                                className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm bg-white">
                                <option value="">— Nenhum —</option>
                                {tiposProcesso.map(t => <option key={t.id} value={t.id}>{t.sigla} - {t.nome}</option>)}
                            </select>
                        </div>
                    </div>
                </div>

                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <label className="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" checked={data.permite_anonimo}
                            onChange={(e) => setData('permite_anonimo', e.target.checked)}
                            className="mt-0.5" />
                        <div>
                            <p className="text-sm font-semibold text-amber-900">Permitir solicitacao anonima</p>
                            <p className="text-[11px] text-amber-700">Util para denuncias e ouvidoria. Quando marcado, o cidadao pode solicitar sem fazer login.</p>
                        </div>
                    </label>
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Orgao responsavel (texto livre)</label>
                    <input type="text" value={data.orgao_responsavel} onChange={(e) => setData('orgao_responsavel', e.target.value)}
                        placeholder="Ex: Secretaria de Saude"
                        className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    <p className="text-[10px] text-gray-400 mt-1">Aparece na pagina publica do servico. Pode complementar o setor do organograma.</p>
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Legislacao de referencia</label>
                    <textarea value={data.legislacao} onChange={(e) => setData('legislacao', e.target.value)}
                        rows={2}
                        className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
                        placeholder="Ex: Lei 8.080/90, Decreto Municipal 123/2024" />
                </div>

                <div>
                    <label className="text-xs font-semibold text-gray-600 mb-1 block">Palavras-chave / Tags</label>
                    <div className="flex flex-wrap gap-1.5 mb-2">
                        {data.palavras_chave.map((t, i) => (
                            <span key={i} className="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">
                                #{t}
                                <button type="button" onClick={() => rmTag(i)} className="hover:text-red-500"><i className="fas fa-times text-[10px]" /></button>
                            </span>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <input type="text" value={novaTag} onChange={(e) => setNovaTag(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addTag(); } }}
                            placeholder="Ex: iptu, segunda via"
                            className="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                        <button type="button" onClick={addTag} className="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
                            <i className="fas fa-plus" />
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 pt-3 border-t border-gray-100">
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.publicado} onChange={(e) => setData('publicado', e.target.checked)} />
                        <span className="font-medium">Publicar no portal</span>
                    </label>
                    <div>
                        <label className="text-xs font-semibold text-gray-600 mb-1 block">Ordem</label>
                        <input type="number" value={data.ordem} onChange={(e) => setData('ordem', parseInt(e.target.value) || 0)}
                            className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </div>
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" disabled={processing}
                        className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                        {isEdit ? 'Salvar' : 'Criar servico'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
