/**
 * Tipos de Processo — Admin GED
 *
 * CRUD com schema builder para formularios, etapas de fluxo e templates de despacho.
 */
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Button from '../../../../Components/Button';
import Card from '../../../../Components/Card';
import Modal from '../../../../Components/Modal';
import useFillViewportHeight from '../../../../Hooks/useFillViewportHeight';
import SetoresMulti from '../../../../Components/SetoresMulti';

const categoriaOptions = [
    { value: 'administrativo', label: 'Administrativo' },
    { value: 'financeiro', label: 'Financeiro' },
    { value: 'juridico', label: 'Jurídico' },
    { value: 'rh', label: 'Recursos Humanos' },
    { value: 'compras', label: 'Compras/Licitacao' },
    { value: 'outro', label: 'Outro' },
];

const tiposCampo = [
    { value: 'text', label: 'Texto' },
    { value: 'number', label: 'Número' },
    { value: 'date', label: 'Data' },
    { value: 'select', label: 'Seleção' },
    { value: 'textarea', label: 'Texto longo' },
    { value: 'money', label: 'Monetário' },
    { value: 'cpf_cnpj', label: 'CPF/CNPJ (validado)' },
    { value: 'cep', label: 'Endereço com CEP' },
    { value: 'email', label: 'E-mail' },
    { value: 'telefone', label: 'Telefone' },
    { value: 'checkbox', label: 'Caixa de marcar' },
    { value: 'calculo', label: 'Cálculo entre campos' },
];

/** Ajuda do campo para quem preenche (TR 4.38.6): texto e imagem. */
function AjudaDoCampo({ field, onChange }) {
    const [enviando, setEnviando] = useState(false);
    const enviar = async (arquivo) => {
        if (! arquivo) return;
        setEnviando(true);
        try {
            const fd = new FormData(); fd.append('imagem', arquivo);
            const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
            const r = await fetch('/admin/tipos-processo/ajuda-imagem', { method: 'POST', body: fd, headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' } });
            const j = await r.json();
            if (j.url) onChange('ajuda_imagem', j.url);
        } finally {
            setEnviando(false);
        }
    };
    return (
        <div className="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div className="md:col-span-2">
                <label className="block text-[11px] font-medium text-gray-500 mb-1">Ajuda (aparece abaixo do campo)</label>
                <input type="text" value={field.ajuda || ''} onChange={(e) => onChange('ajuda', e.target.value)}
                    className="ds-input text-xs" placeholder="Ex.: informe a matrícula que está no carnê do IPTU" maxLength={500} />
            </div>
            <div>
                <label className="block text-[11px] font-medium text-gray-500 mb-1">Imagem de ajuda</label>
                {field.ajuda_imagem ? (
                    <div className="flex items-center gap-2">
                        <img src={field.ajuda_imagem} alt="" className="h-9 rounded border border-gray-200" />
                        <button type="button" onClick={() => onChange('ajuda_imagem', '')} className="text-xs text-red-600 hover:underline">Remover</button>
                    </div>
                ) : (
                    <label className="ds-btn ds-btn-outline ds-btn-sm cursor-pointer">
                        <i className={`fas ${enviando ? 'fa-spinner fa-spin' : 'fa-image'} mr-1`} />{enviando ? 'Enviando…' : 'Escolher imagem'}
                        <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" className="hidden" onChange={(e) => enviar(e.target.files[0])} />
                    </label>
                )}
            </div>
        </div>
    );
}

const tiposEtapa = [
    { value: 'analise', label: 'Analise' },
    { value: 'parecer', label: 'Parecer' },
    { value: 'aprovacao', label: 'Aprovação' },
    { value: 'assinatura', label: 'Assinatura' },
    { value: 'despacho', label: 'Despacho' },
    { value: 'arquivamento', label: 'Arquivamento' },
];

const emptyField = { campo: '', tipo: 'text', label: '', obrigatorio: false, opcoes: '' };
const emptyEtapa = { nome: '', tipo: 'analise', setor_destino: '', unidades: [], obrigatorio: true, sla_horas: '', template_texto: '' };
const emptyTemplate = { nome: '', conteudo: '' };

export default function Index({ tipos, unidades = [] }) {
    // Listagem rola só por dentro — a página nunca (padrão gpe2).
    const scrollRef = useFillViewportHeight();
    const lista = tipos?.data || tipos || [];
    const [showModal, setShowModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [activeSection, setActiveSection] = useState('basic');

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nome: '',
        sigla: '',
        descricao: '',
        categoria: 'administrativo',
        sla_padrao_horas: '',
        prazo_dias_uteis: false,
        localizacao: 'nao',
        modalidade: '',
        email_entrada: '',
        email_unidade_id: '',
        avaliacao: true,
        ativo: true,
        schema_formulario: [],
        etapas: [],
        templates_despacho: [],
        anexos_exigidos: [],
    });

    const openCreate = () => {
        reset();
        setEditingId(null);
        setActiveSection('basic');
        setShowModal(true);
    };

    const openEdit = (tipo) => {
        setData({
            nome: tipo.nome || '',
            sigla: tipo.sigla || '',
            descricao: tipo.descricao || '',
            categoria: tipo.categoria || 'administrativo',
            sla_padrao_horas: tipo.sla_padrao_horas || '',
            prazo_dias_uteis: !! tipo.prazo_dias_uteis,
            localizacao: tipo.localizacao || 'nao',
            modalidade: tipo.modalidade || '',
            email_entrada: tipo.email_entrada || '',
            email_unidade_id: tipo.email_unidade_id || '',
            avaliacao: tipo.avaliacao !== false,
            ativo: tipo.ativo !== false,
            schema_formulario: tipo.schema_formulario || [],
            etapas: (tipo.etapas || []).map(e => ({ ...e, unidades: e.unidades || [], obrigatorio: e.obrigatorio ?? true })),
            templates_despacho: tipo.templates_despacho || [],
            anexos_exigidos: tipo.anexos_exigidos || [],
        });
        setEditingId(tipo.id);
        setActiveSection('basic');
        setShowModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingId) {
            put(`/admin/tipos-processo/${editingId}`, {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        } else {
            post('/admin/tipos-processo', {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        }
    };

    const confirmDelete = (tipo) => {
        setDeleteTarget(tipo);
        setShowDeleteModal(true);
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(`/admin/tipos-processo/${deleteTarget.id}`, {
            onSuccess: () => { setShowDeleteModal(false); setDeleteTarget(null); },
        });
    };

    const toggleAtivo = (tipo) => {
        router.put(`/admin/tipos-processo/${tipo.id}`, {
            ...tipo,
            ativo: !tipo.ativo,
        }, { preserveScroll: true });
    };

    // ── Schema Formulario helpers ──
    const addField = () => setData('schema_formulario', [...data.schema_formulario, { ...emptyField }]);
    const removeField = (idx) => setData('schema_formulario', data.schema_formulario.filter((_, i) => i !== idx));
    const updateField = (idx, key, value) => {
        const updated = [...data.schema_formulario];
        updated[idx] = { ...updated[idx], [key]: value };
        setData('schema_formulario', updated);
    };
    const moveField = (idx, dir) => {
        const newIdx = idx + dir;
        if (newIdx < 0 || newIdx >= data.schema_formulario.length) return;
        const updated = [...data.schema_formulario];
        [updated[idx], updated[newIdx]] = [updated[newIdx], updated[idx]];
        setData('schema_formulario', updated);
    };

    // ── Etapas helpers ──
    const addEtapa = () => setData('etapas', [...data.etapas, { ...emptyEtapa }]);
    const removeEtapa = (idx) => setData('etapas', data.etapas.filter((_, i) => i !== idx));
    const updateEtapa = (idx, key, value) => {
        const updated = [...data.etapas];
        updated[idx] = { ...updated[idx], [key]: value };
        setData('etapas', updated);
    };
    const moveEtapa = (idx, dir) => {
        const newIdx = idx + dir;
        if (newIdx < 0 || newIdx >= data.etapas.length) return;
        const updated = [...data.etapas];
        [updated[idx], updated[newIdx]] = [updated[newIdx], updated[idx]];
        setData('etapas', updated);
    };

    // ── Templates helpers ──
    const addTemplate = () => setData('templates_despacho', [...data.templates_despacho, { ...emptyTemplate }]);
    const removeTemplate = (idx) => setData('templates_despacho', data.templates_despacho.filter((_, i) => i !== idx));
    const updateTemplate = (idx, key, value) => {
        const updated = [...data.templates_despacho];
        updated[idx] = { ...updated[idx], [key]: value };
        setData('templates_despacho', updated);
    };

    const sections = [
        { key: 'basic', label: 'Dados Básicos', icon: 'fas fa-info-circle' },
        { key: 'schema', label: 'Formulário', icon: 'fas fa-list-alt' },
        { key: 'etapas', label: 'Etapas', icon: 'fas fa-route' },
        { key: 'templates', label: 'Templates', icon: 'fas fa-file-alt' },
        { key: 'anexos', label: 'Documentos exigidos', icon: 'fas fa-paperclip' },
    ];

    return (
        <AdminLayout>
            <Head title="Tipos de Processo" />

            <PageHeader title="Tipos de Processo" subtitle="Gerenciar tipos e fluxos de processos">
                <Button icon="fas fa-plus" onClick={openCreate}>
                    Novo Tipo
                </Button>
            </PageHeader>

            <Card padding={false}>
                {lista.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-cogs text-3xl mb-3 block" />
                        <p className="text-sm font-medium">Nenhum tipo de processo cadastrado</p>
                    </div>
                ) : (
                    <div ref={scrollRef} className="overflow-auto">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 z-20 bg-gray-50">
                                <tr className="border-b border-gray-200 bg-gray-50">
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Nome</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Sigla</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Categoria</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">SLA (h)</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Processos</th>
                                    <th className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th className="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {lista.map((tipo) => (
                                    <tr key={tipo.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="py-3 px-4 font-medium text-gray-800">{tipo.nome}</td>
                                        <td className="py-3 px-4">
                                            {tipo.sigla ? (
                                                <span className="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">{tipo.sigla}</span>
                                            ) : (
                                                <span className="text-gray-300">-</span>
                                            )}
                                        </td>
                                        <td className="py-3 px-4 text-gray-500 capitalize">{tipo.categoria || '-'}</td>
                                        <td className="py-3 px-4 text-gray-500">{tipo.sla_padrao_horas || '-'}</td>
                                        <td className="py-3 px-4">
                                            <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                                                {tipo.processos_count ?? 0}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4">
                                            <button
                                                onClick={() => toggleAtivo(tipo)}
                                                className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${
                                                    tipo.ativo !== false ? 'bg-green-500' : 'bg-gray-300'
                                                }`}
                                            >
                                                <span className={`inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform shadow-sm ${
                                                    tipo.ativo !== false ? 'translate-x-4' : 'translate-x-1'
                                                }`} />
                                            </button>
                                        </td>
                                        <td className="py-3 px-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => openEdit(tipo)}
                                                    className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                    title="Editar"
                                                >
                                                    <i className="fas fa-edit" />
                                                </button>
                                                <button
                                                    onClick={() => confirmDelete(tipo)}
                                                    className={`p-1.5 rounded-lg transition-colors ${
                                                        (tipo.processos_count ?? 0) > 0
                                                            ? 'text-gray-200 cursor-not-allowed'
                                                            : 'text-gray-400 hover:text-red-600 hover:bg-red-50'
                                                    }`}
                                                    disabled={(tipo.processos_count ?? 0) > 0}
                                                    title={(tipo.processos_count ?? 0) > 0 ? 'Não pode excluir: possui processos' : 'Excluir'}
                                                >
                                                    <i className="fas fa-trash" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {/* Pagination */}
            {tipos?.links && (
                <div className="flex items-center justify-center gap-1 mt-6">
                    {tipos.links.map((link, idx) => (
                        <button
                            key={idx}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : link.url
                                    ? 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
                                    : 'text-gray-300 cursor-not-allowed'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            {/* ══════════════════════════════════════════════════════════ */}
            {/* Modal: Create / Edit                                      */}
            {/* ══════════════════════════════════════════════════════════ */}
            <Modal show={showModal} onClose={() => setShowModal(false)} title={editingId ? 'Editar Tipo de Processo' : 'Novo Tipo de Processo'} maxWidth="4xl">
                <form onSubmit={handleSubmit}>
                    {/* Section tabs */}
                    <div className="flex gap-1 mb-6 border-b border-gray-100 pb-3 -mt-1">
                        {sections.map((sec) => (
                            <button
                                key={sec.key}
                                type="button"
                                onClick={() => setActiveSection(sec.key)}
                                className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors ${
                                    activeSection === sec.key
                                        ? 'bg-blue-100 text-blue-700'
                                        : 'text-gray-500 hover:bg-gray-100'
                                }`}
                            >
                                <i className={sec.icon} />
                                {sec.label}
                                {sec.key === 'schema' && data.schema_formulario.length > 0 && (
                                    <span className="bg-blue-600 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center">
                                        {data.schema_formulario.length}
                                    </span>
                                )}
                                {sec.key === 'etapas' && data.etapas.length > 0 && (
                                    <span className="bg-blue-600 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center">
                                        {data.etapas.length}
                                    </span>
                                )}
                                {sec.key === 'templates' && data.templates_despacho.length > 0 && (
                                    <span className="bg-blue-600 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center">
                                        {data.templates_despacho.length}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    {/* ── Section: Dados Basicos ── */}
                    {activeSection === 'basic' && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Nome <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.nome}
                                        onChange={(e) => setData('nome', e.target.value)}
                                        className="ds-input"
                                        placeholder="Ex: Licença Prêmios"
                                    />
                                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Sigla</label>
                                    <input
                                        type="text"
                                        value={data.sigla}
                                        onChange={(e) => setData('sigla', e.target.value)}
                                        className="ds-input"
                                        placeholder="Ex: LP"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                                <textarea
                                    value={data.descricao}
                                    onChange={(e) => setData('descricao', e.target.value)}
                                    className="ds-input !h-auto"
                                    rows={3}
                                    placeholder="Descrição do tipo de processo..."
                                />
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                                    <select
                                        value={data.categoria}
                                        onChange={(e) => setData('categoria', e.target.value)}
                                        className="ds-input"
                                    >
                                        {categoriaOptions.map(o => (
                                            <option key={o.value} value={o.value}>{o.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">SLA Padrão (horas)</label>
                                    <input
                                        type="number"
                                        value={data.sla_padrao_horas}
                                        onChange={(e) => setData('sla_padrao_horas', e.target.value)}
                                        className="ds-input"
                                        placeholder="Ex: 48"
                                        min="1"
                                    />
                                    <label className="mt-2 flex items-start gap-2 text-xs text-gray-600 cursor-pointer">
                                        <input type="checkbox" checked={!! data.prazo_dias_uteis} onChange={(e) => setData('prazo_dias_uteis', e.target.checked)}
                                            className="mt-0.5 rounded border-gray-300 text-blue-600" />
                                        <span>Contar em dias úteis (cada 24h = 1 dia útil; vence no fechamento do expediente, sem fins de semana e feriados — Configurações › Expediente e feriados)</span>
                                    </label>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
                                    <select value={data.modalidade} onChange={(e) => setData('modalidade', e.target.value)} className="ds-input mb-3">
                                        <option value="">Processo comum</option>
                                        <option value="ouvidoria">Ouvidoria (Lei 13.460 — 30 dias úteis, sigilo, decisão final)</option>
                                        <option value="esic">e-SIC / acesso à informação (LAI — 20 dias + 10)</option>
                                        <option value="chamado">Chamado técnico (patrimônio, abrir em nome de servidor)</option>
                                        <option value="servidor">Requerimento do servidor (só Gestão de Pessoas e setores envolvidos; fora das buscas)</option>
                                    </select>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">E-mail de entrada <span className="font-normal text-gray-400">(opcional)</span></label>
                                    <input type="email" value={data.email_entrada} onChange={(e) => setData('email_entrada', e.target.value)} className="ds-input" placeholder="ouvidoria@municipio.gov.br" />
                                    {errors.email_entrada && <p className="text-xs text-red-600 mt-1">{errors.email_entrada}</p>}
                                    <select value={data.email_unidade_id} onChange={(e) => setData('email_unidade_id', e.target.value)} className="ds-input mt-2">
                                        <option value="">Setor que recebe os e-mails…</option>
                                        {unidades.map(u => <option key={u.id} value={u.id}>{u.nome}</option>)}
                                    </select>
                                    {errors.email_unidade_id && <p className="text-xs text-red-600 mt-1">{errors.email_unidade_id}</p>}
                                    <p className="text-[11px] text-gray-500 mt-1 mb-3">O e-mail que chega neste endereço abre um processo deste assunto no setor escolhido, com canal "e-mail"; a decisão final volta ao remetente.</p>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Local da demanda no mapa</label>
                                    <select value={data.localizacao} onChange={(e) => setData('localizacao', e.target.value)} className="ds-input">
                                        <option value="nao">Não pede</option>
                                        <option value="opcional">Pede, opcional</option>
                                        <option value="obrigatoria">Obrigatório marcar no mapa</option>
                                    </select>
                                    <label className="mt-2 flex items-start gap-2 text-xs text-gray-600 cursor-pointer">
                                        <input type="checkbox" checked={!! data.avaliacao} onChange={(e) => setData('avaliacao', e.target.checked)}
                                            className="mt-0.5 rounded border-gray-300 text-blue-600" />
                                        <span>Pedir avaliação por estrelas ao concluir</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ── Section: Schema Formulario ── */}
                    {activeSection === 'schema' && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-500">
                                    Defina os campos que serão exibidos ao abrir um processo deste tipo.
                                </p>
                                <button
                                    type="button"
                                    onClick={addField}
                                    className="ds-btn ds-btn-outline ds-btn-sm"
                                >
                                    <i className="fas fa-plus mr-1" />
                                    Adicionar Campo
                                </button>
                            </div>

                            {data.schema_formulario.length === 0 ? (
                                <div className="py-8 text-center text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">
                                    <i className="fas fa-list-alt text-2xl mb-2 block" />
                                    <p className="text-sm">Nenhum campo configurado</p>
                                    <p className="text-xs mt-1">Clique em "Adicionar Campo" para começar</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {data.schema_formulario.map((field, idx) => (
                                        <div key={idx} className="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                            <div className="flex items-center justify-between mb-3">
                                                <span className="text-xs font-bold text-gray-400">Campo #{idx + 1}</span>
                                                <div className="flex items-center gap-1">
                                                    <button type="button" onClick={() => moveField(idx, -1)}
                                                        className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30" disabled={idx === 0}>
                                                        <i className="fas fa-arrow-up text-xs" />
                                                    </button>
                                                    <button type="button" onClick={() => moveField(idx, 1)}
                                                        className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30" disabled={idx === data.schema_formulario.length - 1}>
                                                        <i className="fas fa-arrow-down text-xs" />
                                                    </button>
                                                    <button type="button" onClick={() => removeField(idx)}
                                                        className="p-1 text-red-400 hover:text-red-600 ml-1">
                                                        <i className="fas fa-trash text-xs" />
                                                    </button>
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Identificador</label>
                                                    <input
                                                        type="text"
                                                        value={field.campo}
                                                        onChange={(e) => updateField(idx, 'campo', e.target.value)}
                                                        className="ds-input text-xs"
                                                        placeholder="nome_campo"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Tipo</label>
                                                    <select
                                                        value={field.tipo}
                                                        onChange={(e) => updateField(idx, 'tipo', e.target.value)}
                                                        className="ds-input text-xs"
                                                    >
                                                        {tiposCampo.map(t => (
                                                            <option key={t.value} value={t.value}>{t.label}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Label</label>
                                                    <input
                                                        type="text"
                                                        value={field.label}
                                                        onChange={(e) => updateField(idx, 'label', e.target.value)}
                                                        className="ds-input text-xs"
                                                        placeholder="Nome de exibição"
                                                    />
                                                </div>
                                                <div className="flex items-end gap-3">
                                                    <label className="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer pb-2">
                                                        <input
                                                            type="checkbox"
                                                            checked={field.obrigatorio || false}
                                                            onChange={(e) => updateField(idx, 'obrigatorio', e.target.checked)}
                                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                        />
                                                        Obrigatório
                                                    </label>
                                                </div>
                                            </div>
                                            {(field.tipo === 'select') && (
                                                <div className="mt-3">
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Opções (separadas por vírgula)</label>
                                                    <input
                                                        type="text"
                                                        value={field.opcoes || ''}
                                                        onChange={(e) => updateField(idx, 'opcoes', e.target.value)}
                                                        className="ds-input text-xs"
                                                        placeholder="opcao1, opcao2, opcao3"
                                                    />
                                                </div>
                                            )}
                                            {field.tipo === 'calculo' && (
                                                <div className="mt-3">
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Fórmula</label>
                                                    <input type="text" value={field.formula || ''}
                                                        onChange={(e) => updateField(idx, 'formula', e.target.value)}
                                                        className="ds-input text-xs font-mono" placeholder="{quantidade} * {valor_unitario}" />
                                                    <p className="mt-1 text-[10px] text-gray-400">
                                                        Use o identificador dos outros campos entre chaves e + − * / ( ). Disponíveis: {data.schema_formulario
                                                            .filter(c => c.campo && ['number', 'money', 'calculo'].includes(c.tipo) && c !== field).map(c => `{${c.campo}}`).join(' ') || 'nenhum campo numérico ainda'}
                                                    </p>
                                                </div>
                                            )}
                                            <AjudaDoCampo field={field} onChange={(k, v) => updateField(idx, k, v)} />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── Section: Etapas ── */}
                    {activeSection === 'etapas' && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-500">
                                    Defina as etapas do fluxo de tramitação deste tipo de processo.
                                </p>
                                <button
                                    type="button"
                                    onClick={addEtapa}
                                    className="ds-btn ds-btn-outline ds-btn-sm"
                                >
                                    <i className="fas fa-plus mr-1" />
                                    Adicionar Etapa
                                </button>
                            </div>

                            {data.etapas.length === 0 ? (
                                <div className="py-8 text-center text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">
                                    <i className="fas fa-route text-2xl mb-2 block" />
                                    <p className="text-sm">Nenhuma etapa configurada</p>
                                    <p className="text-xs mt-1">Clique em "Adicionar Etapa" para definir o fluxo</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {data.etapas.map((etapa, idx) => (
                                        <div key={idx} className="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                            <div className="flex items-center justify-between mb-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold">
                                                        {idx + 1}
                                                    </span>
                                                    <span className="text-xs font-bold text-gray-500">Etapa</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <button type="button" onClick={() => moveEtapa(idx, -1)}
                                                        className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30" disabled={idx === 0}>
                                                        <i className="fas fa-arrow-up text-xs" />
                                                    </button>
                                                    <button type="button" onClick={() => moveEtapa(idx, 1)}
                                                        className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30" disabled={idx === data.etapas.length - 1}>
                                                        <i className="fas fa-arrow-down text-xs" />
                                                    </button>
                                                    <button type="button" onClick={() => removeEtapa(idx)}
                                                        className="p-1 text-red-400 hover:text-red-600 ml-1">
                                                        <i className="fas fa-trash text-xs" />
                                                    </button>
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Nome</label>
                                                    <input
                                                        type="text"
                                                        value={etapa.nome}
                                                        onChange={(e) => updateEtapa(idx, 'nome', e.target.value)}
                                                        className="ds-input text-xs"
                                                        placeholder="Nome da etapa"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Tipo</label>
                                                    <select
                                                        value={etapa.tipo}
                                                        onChange={(e) => updateEtapa(idx, 'tipo', e.target.value)}
                                                        className="ds-input text-xs"
                                                    >
                                                        {tiposEtapa.map(t => (
                                                            <option key={t.value} value={t.value}>{t.label}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">SLA (horas)</label>
                                                    <input
                                                        type="number"
                                                        value={etapa.sla_horas}
                                                        onChange={(e) => updateEtapa(idx, 'sla_horas', e.target.value)}
                                                        className="ds-input text-xs"
                                                        placeholder="48"
                                                        min="1"
                                                    />
                                                </div>
                                                <label className="flex items-center gap-2 text-xs text-gray-700 cursor-pointer pt-5">
                                                    <input type="checkbox" checked={etapa.obrigatorio !== false}
                                                        onChange={(e) => updateEtapa(idx, 'obrigatorio', e.target.checked)}
                                                        className="rounded border-gray-300 text-blue-600" />
                                                    Obrigatória (não pode ser pulada)
                                                </label>
                                            </div>
                                            <div className="mt-3">
                                                <SetoresMulti pequeno titulo="Setores responsáveis pela etapa"
                                                    ajuda={etapa.setor_destino && ! (etapa.unidades || []).length
                                                        ? `Antes era um texto: "${etapa.setor_destino}". Escolha os setores no organograma. Vazio = etapa livre (qualquer setor).`
                                                        : 'Um ou vários setores. Vazio = etapa livre: quem despacha escolhe qualquer setor.'}
                                                    unidades={unidades} valor={etapa.unidades || []}
                                                    onChange={(v) => updateEtapa(idx, 'unidades', v)} />
                                            </div>
                                            <div className="mt-3">
                                                <label className="block text-[11px] font-medium text-gray-500 mb-1">Template de Texto</label>
                                                <textarea
                                                    value={etapa.template_texto}
                                                    onChange={(e) => updateEtapa(idx, 'template_texto', e.target.value)}
                                                    className="ds-input !h-auto text-xs"
                                                    rows={2}
                                                    placeholder="Texto padrão para esta etapa..."
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── Section: Templates de Despacho ── */}
                    {/* ── Section: Documentos exigidos (TR 4.32.14, 4.38.18) ── */}
                    {activeSection === 'anexos' && (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-500">Documentos que o requerente anexa ao abrir. Sem os obrigatórios o processo não abre.</p>
                                <button type="button" className="ds-btn ds-btn-outline ds-btn-sm"
                                    onClick={() => setData('anexos_exigidos', [...data.anexos_exigidos, { nome: '', obrigatorio: true, extensoes: 'pdf' }])}>
                                    <i className="fas fa-plus mr-1" />Adicionar documento
                                </button>
                            </div>
                            {data.anexos_exigidos.length === 0 && (
                                <p className="py-6 text-center text-sm text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">Nenhum documento exigido</p>
                            )}
                            {data.anexos_exigidos.map((ax, idx) => {
                                const mudar = (k, v) => setData('anexos_exigidos', data.anexos_exigidos.map((a, i) => i === idx ? { ...a, [k]: v } : a));
                                return (
                                    <div key={idx} className="bg-gray-50 rounded-xl p-3 border border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                        <div className="md:col-span-6">
                                            <label className="block text-[11px] font-medium text-gray-500 mb-1">Documento</label>
                                            <input value={ax.nome} onChange={(e) => mudar('nome', e.target.value)} className="ds-input text-xs" placeholder="Ex.: Cópia do RG" maxLength={150} />
                                        </div>
                                        <div className="md:col-span-3">
                                            <label className="block text-[11px] font-medium text-gray-500 mb-1">Extensões permitidas</label>
                                            <input value={ax.extensoes || ''} onChange={(e) => mudar('extensoes', e.target.value)} className="ds-input text-xs" placeholder="pdf, jpg, png" />
                                        </div>
                                        <label className="md:col-span-2 flex items-center gap-1.5 text-xs text-gray-600 pb-2 cursor-pointer">
                                            <input type="checkbox" checked={ax.obrigatorio !== false} onChange={(e) => mudar('obrigatorio', e.target.checked)} className="rounded border-gray-300 text-blue-600" />
                                            Obrigatório
                                        </label>
                                        <button type="button" title="Remover" onClick={() => setData('anexos_exigidos', data.anexos_exigidos.filter((_, i) => i !== idx))}
                                            className="md:col-span-1 p-2 text-red-400 hover:text-red-600 justify-self-end">
                                            <i className="fas fa-trash text-xs" />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {activeSection === 'templates' && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-500">
                                    Templates de texto prontos para uso nos despachos.
                                </p>
                                <button
                                    type="button"
                                    onClick={addTemplate}
                                    className="ds-btn ds-btn-outline ds-btn-sm"
                                >
                                    <i className="fas fa-plus mr-1" />
                                    Adicionar Template
                                </button>
                            </div>

                            {data.templates_despacho.length === 0 ? (
                                <div className="py-8 text-center text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">
                                    <i className="fas fa-file-alt text-2xl mb-2 block" />
                                    <p className="text-sm">Nenhum template cadastrado</p>
                                    <p className="text-xs mt-1">Clique em "Adicionar Template" para criar modelos de despacho</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {data.templates_despacho.map((tmpl, idx) => (
                                        <div key={idx} className="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                            <div className="flex items-center justify-between mb-3">
                                                <span className="text-xs font-bold text-gray-400">Template #{idx + 1}</span>
                                                <button type="button" onClick={() => removeTemplate(idx)}
                                                    className="p-1 text-red-400 hover:text-red-600">
                                                    <i className="fas fa-trash text-xs" />
                                                </button>
                                            </div>
                                            <div className="space-y-3">
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Nome do Template</label>
                                                    <input
                                                        type="text"
                                                        value={tmpl.nome}
                                                        onChange={(e) => updateTemplate(idx, 'nome', e.target.value)}
                                                        className="ds-input text-xs"
                                                        placeholder="Ex: Deferimento padrão"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[11px] font-medium text-gray-500 mb-1">Conteúdo</label>
                                                    <textarea
                                                        value={tmpl.conteudo}
                                                        onChange={(e) => updateTemplate(idx, 'conteudo', e.target.value)}
                                                        className="ds-input !h-auto text-xs"
                                                        rows={4}
                                                        placeholder="Texto do template de despacho..."
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Footer */}
                    <div className="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-gray-100">
                        <Button variant="secondary" type="button" onClick={() => setShowModal(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={processing} icon="fas fa-save">
                            {editingId ? 'Salvar Alterações' : 'Criar Tipo'}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal: Delete */}
            <Modal show={showDeleteModal} onClose={() => setShowDeleteModal(false)} title="Excluir Tipo de Processo">
                <div className="space-y-4">
                    {deleteTarget && (deleteTarget.processos_count ?? 0) > 0 ? (
                        <div className="flex items-start gap-3 p-4 bg-yellow-50 rounded-xl">
                            <i className="fas fa-exclamation-triangle text-yellow-500 mt-0.5" />
                            <div>
                                <p className="text-sm font-medium text-yellow-800">Não é possível excluir</p>
                                <p className="text-sm text-yellow-600 mt-1">
                                    Este tipo possui <strong>{deleteTarget.processos_count}</strong> processo(s) vinculado(s).
                                    Remova ou migre os processos antes de excluir.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="flex items-start gap-3 p-4 bg-red-50 rounded-xl">
                                <i className="fas fa-exclamation-triangle text-red-500 mt-0.5" />
                                <div>
                                    <p className="text-sm font-medium text-red-800">Atenção</p>
                                    <p className="text-sm text-red-600 mt-1">
                                        Deseja excluir o tipo <strong>"{deleteTarget?.nome}"</strong>? Esta ação não pode ser desfeita.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center justify-end gap-2 pt-2">
                                <Button variant="secondary" onClick={() => setShowDeleteModal(false)}>
                                    Cancelar
                                </Button>
                                <Button variant="danger" icon="fas fa-trash" onClick={handleDelete}>
                                    Excluir
                                </Button>
                            </div>
                        </>
                    )}
                    {deleteTarget && (deleteTarget.processos_count ?? 0) > 0 && (
                        <div className="flex justify-end">
                            <Button variant="secondary" onClick={() => setShowDeleteModal(false)}>
                                Fechar
                            </Button>
                        </div>
                    )}
                </div>
            </Modal>
        </AdminLayout>
    );
}
