/**
 * Visualizacao de Processo — GED
 *
 * Detalhes completos, timeline de tramitacao, despacho e comentarios.
 */
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';
import Tabs from '../../../Components/Tabs';
import AssinarModal from '../../../Components/AssinarModal';

const statusColors = {
    aberto: 'bg-blue-100 text-blue-700',
    em_tramitacao: 'bg-yellow-100 text-yellow-700',
    aguardando_assinatura: 'bg-purple-100 text-purple-700',
    concluido: 'bg-green-100 text-green-700',
    cancelado: 'bg-red-100 text-red-700',
    arquivado: 'bg-gray-100 text-gray-700',
};

const statusLabels = {
    aberto: 'Aberto',
    em_tramitacao: 'Em Tramitacao',
    aguardando_assinatura: 'Aguardando Assinatura',
    concluido: 'Concluido',
    cancelado: 'Cancelado',
    arquivado: 'Arquivado',
};

const decisaoLabels = {
    deferido:   { texto: 'Deferido',          cor: 'green',  icone: 'fa-check-circle' },
    indeferido: { texto: 'Indeferido',        cor: 'red',    icone: 'fa-times-circle' },
    parcial:    { texto: 'Deferido Parcial',  cor: 'amber',  icone: 'fa-balance-scale' },
    arquivado:  { texto: 'Arquivado',         cor: 'gray',   icone: 'fa-archive' },
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
    { key: 'comentarios', label: 'Comentarios', icon: 'fas fa-comments' },
];

export default function Show({ processo, usuarios, unidades = [], pode_receber, pode_despachar, pode_concluir, assinatura_pendente, decisao_assinada, pastas = [], solicitacao_portal = null }) {
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

    // Hierarquia de unidades para o dropdown de despacho (filhos sob o pai)
    const unidadesTree = useMemo(() => {
        const filhosPor = new Map();
        (unidades || []).forEach(u => {
            const pid = u.parent_id ?? null;
            if (! filhosPor.has(pid)) filhosPor.set(pid, []);
            filhosPor.get(pid).push(u);
        });
        for (const [, l] of filhosPor) l.sort((a, b) => a.nome.localeCompare(b.nome));
        const out = [];
        const visit = (pid) => {
            for (const u of (filhosPor.get(pid) || [])) {
                out.push(u);
                visit(u.id);
            }
        };
        visit(null);
        return out;
    }, [unidades]);

    const [activeTab, setActiveTab] = useState('detalhes');
    const [showConcluirModal, setShowConcluirModal] = useState(false);
    const [showCancelarModal, setShowCancelarModal] = useState(false);
    const [assinarOpen, setAssinarOpen] = useState(false);
    const [arquivarGedOpen, setArquivarGedOpen] = useState(false);

    // Form: arquivar no GPE Docs
    const arquivarGedForm = useForm({ pasta_id: '' });

    // Hierarquia visual de pastas (filhos abaixo do pai)
    const pastasTree = useMemo(() => {
        const filhosPor = new Map();
        (pastas || []).forEach(p => {
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
    }, [pastas]);

    const handleArquivarGed = (e) => {
        e.preventDefault();
        if (! arquivarGedForm.data.pasta_id) return;
        arquivarGedForm.post(`/processos/${proc.id}/arquivar-no-ged`, {
            preserveScroll: true,
            onSuccess: () => { arquivarGedForm.reset(); setArquivarGedOpen(false); },
        });
    };

    // Modo da acao (Encaminhar | Decidir | Arquivar)
    const [acaoMode, setAcaoMode] = useState('encaminhar');

    // Form: Concluir / Decidir
    const concluirForm = useForm({ observacao_conclusao: '', decisao: '', anexo: null, pular_assinatura: false });

    // Form: Despachar
    const despacharForm = useForm({
        destinatario_id: '',
        setor_destino: '',
        despacho: '',
        files: [],
    });
    const [despacharFiles, setDespacharFiles] = useState([]);

    // Combobox de setor (busca + dropdown)
    const [setorSearch, setSetorSearch] = useState('');
    const [setorOpen, setSetorOpen] = useState(false);
    const unidadesFiltradas = useMemo(() => {
        if (! setorSearch.trim()) return unidadesTree;
        const t = setorSearch.toLowerCase();
        return unidadesTree.filter(u => u.nome.toLowerCase().includes(t) || (u.codigo || '').toLowerCase().includes(t));
    }, [unidadesTree, setorSearch]);
    const setorSelecionado = unidadesTree.find(u => String(u.id) === String(despacharForm.data.setor_destino));

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

    // Decidir e Encerrar — usa /concluir passando decisao
    const handleDecidir = (decisao, pularAssinatura = false) => {
        if (! concluirForm.data.observacao_conclusao.trim()) {
            alert('Informe a observacao/parecer da decisao.');
            return;
        }
        concluirForm.setData(d => ({ ...d, decisao, pular_assinatura: pularAssinatura }));
        concluirForm.transform((data) => ({ ...data, decisao, pular_assinatura: pularAssinatura }));
        concluirForm.post(`/processos/${proc.id}/concluir`, {
            preserveScroll: true,
            onSuccess: () => concluirForm.reset(),
        });
    };

    const handleArquivar = () => {
        if (! confirm('Arquivar este processo? Ele sera encerrado sem decisao formal.')) return;
        concluirForm.transform((data) => ({ ...data, decisao: 'arquivado', observacao_conclusao: data.observacao_conclusao || 'Arquivado sem decisao formal' }));
        concluirForm.post(`/processos/${proc.id}/concluir`, {
            preserveScroll: true,
            onSuccess: () => concluirForm.reset(),
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

            {/* Banner: aguardando assinatura digital */}
            {proc.status === 'aguardando_assinatura' && (
                <div className="bg-purple-50 border border-purple-200 rounded-xl p-4 mb-4 flex items-start gap-3">
                    <i className="fas fa-file-signature text-purple-600 text-xl mt-0.5" />
                    <div className="flex-1">
                        <p className="text-sm font-semibold text-purple-900">
                            Decisao registrada — pendente de assinatura digital
                        </p>
                        <p className="text-xs text-purple-700 mt-0.5">
                            {proc.decisao && decisaoLabels[proc.decisao] && (
                                <><i className={`fas ${decisaoLabels[proc.decisao].icone} mr-1`} /><strong>{decisaoLabels[proc.decisao].texto}</strong> · </>
                            )}
                            Para tornar oficial conforme Lei 14.063/2020 (art. 4 III), assine digitalmente o documento de decisao.
                        </p>
                    </div>
                    {assinatura_pendente ? (
                        <button onClick={() => setAssinarOpen(true)} className="ds-btn ds-btn-primary text-sm whitespace-nowrap">
                            <i className="fas fa-pen-nib mr-1" /> Assinar Agora
                        </button>
                    ) : (
                        <span className="text-xs text-purple-600 italic">Outro signatario esta assinando.</span>
                    )}
                </div>
            )}

            {/* Modal de assinar inline */}
            {assinarOpen && assinatura_pendente && (
                <AssinarModal
                    assinatura={{
                        id: assinatura_pendente.id,
                        documento: { nome: assinatura_pendente.documento_nome },
                        solicitacao: { mensagem: assinatura_pendente.mensagem },
                    }}
                    onClose={() => setAssinarOpen(false)}
                />
            )}

            {/* Banner: decisao concluida */}
            {proc.status === 'concluido' && proc.decisao && decisaoLabels[proc.decisao] && (
                <div className={`rounded-xl border p-3 mb-4 flex items-start gap-3 ${
                    proc.decisao === 'deferido' ? 'bg-green-50 border-green-200' :
                    proc.decisao === 'indeferido' ? 'bg-red-50 border-red-200' :
                    proc.decisao === 'parcial' ? 'bg-amber-50 border-amber-200' :
                    'bg-gray-50 border-gray-200'
                }`}>
                    <i className={`fas ${decisaoLabels[proc.decisao].icone} text-${decisaoLabels[proc.decisao].cor}-600 text-lg mt-0.5`} />
                    <div className="flex-1">
                        <p className="text-sm font-semibold text-gray-800">
                            Decisao final: <strong>{decisaoLabels[proc.decisao].texto}</strong>
                        </p>
                        {proc.observacao_conclusao && (
                            <p className="text-xs text-gray-600 mt-0.5">{proc.observacao_conclusao}</p>
                        )}
                        {decisao_assinada && (
                            <p className="text-[10px] text-gray-500 mt-1">
                                <i className="fas fa-shield-alt mr-1" />
                                Assinado digitalmente em {decisao_assinada.assinado_em}
                                {decisao_assinada.tipo_assinatura && ` · ${decisao_assinada.tipo_assinatura}`}
                            </p>
                        )}
                    </div>
                    {decisao_assinada && (
                        <div className="flex gap-2 shrink-0 flex-wrap">
                            {decisao_assinada.tem_pdf_assinado && (
                                <a href={`/assinaturas/${decisao_assinada.assinatura_id}/download-assinado`}
                                    target="_blank" rel="noopener noreferrer"
                                    className="ds-btn ds-btn-primary text-xs whitespace-nowrap"
                                    title="PDF com assinatura ICP-Brasil embutida (PAdES-BES)">
                                    <i className="fas fa-file-pdf mr-1" />Baixar PDF Assinado
                                </a>
                            )}
                            <a href={`/documentos/${decisao_assinada.documento_id}/download`}
                                target="_blank" rel="noopener noreferrer"
                                className="ds-btn ds-btn-outline text-xs whitespace-nowrap"
                                title="Documento original da decisao">
                                <i className="fas fa-print mr-1" />Imprimir
                            </a>
                            {decisao_assinada.arquivado_no_ged ? (
                                <span className="inline-flex items-center px-2 py-1 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg whitespace-nowrap">
                                    <i className="fas fa-folder-open mr-1" />
                                    Arquivado em <strong className="ml-1">{decisao_assinada.pasta_nome}</strong>
                                </span>
                            ) : (
                                <button onClick={() => setArquivarGedOpen(true)}
                                    className="ds-btn ds-btn-outline text-xs whitespace-nowrap"
                                    title="Mover documento da decisao para uma pasta do GPE Docs">
                                    <i className="fas fa-folder-plus mr-1" />Arquivar no GPE Docs
                                </button>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* Modal: Arquivar no GPE Docs */}
            {arquivarGedOpen && (
                <Modal show={arquivarGedOpen} onClose={() => setArquivarGedOpen(false)} title="Arquivar no GPE Docs">
                    <form onSubmit={handleArquivarGed} className="space-y-4">
                        <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                            <i className="fas fa-info-circle mr-1" />
                            O PDF assinado da decisao sera movido para a pasta escolhida e ficara disponivel no GPE Docs.
                        </div>

                        {pastasTree.length === 0 ? (
                            <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center">
                                <i className="fas fa-folder-open text-amber-600 text-2xl mb-2" />
                                <p className="text-sm text-amber-800 font-semibold mb-1">Nenhuma pasta cadastrada</p>
                                <p className="text-xs text-amber-700">Cadastre pastas em GPE Docs antes de arquivar.</p>
                            </div>
                        ) : (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Pasta de destino <span className="text-red-500">*</span>
                                </label>
                                <select value={arquivarGedForm.data.pasta_id}
                                    onChange={(e) => arquivarGedForm.setData('pasta_id', e.target.value)}
                                    className="ds-input">
                                    <option value="">— Selecione a pasta —</option>
                                    {pastasTree.map(p => (
                                        <option key={p.id} value={p.id}>
                                            {'— '.repeat(p.nivel)}{p.nome}{p.descricao ? ` — ${p.descricao}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {(() => {
                                    const sel = pastasTree.find(p => String(p.id) === String(arquivarGedForm.data.pasta_id));
                                    return sel?.descricao ? (
                                        <p className="mt-1 text-[11px] text-gray-500 italic">
                                            <i className="fas fa-info-circle mr-1" />{sel.descricao}
                                        </p>
                                    ) : null;
                                })()}
                                {arquivarGedForm.errors.pasta_id && (
                                    <p className="mt-1 text-xs text-red-600">{arquivarGedForm.errors.pasta_id}</p>
                                )}
                            </div>
                        )}

                        <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                            <Button variant="secondary" type="button" onClick={() => setArquivarGedOpen(false)}>Cancelar</Button>
                            <Button type="submit" loading={arquivarGedForm.processing} icon="fas fa-folder-plus"
                                disabled={! arquivarGedForm.data.pasta_id || pastasTree.length === 0}>
                                Arquivar
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}

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
                                {pode_concluir && (
                                    <Button variant="primary" icon="fas fa-bolt"
                                        onClick={() => setActiveTab('tramitacao')}>
                                        Tramitar
                                    </Button>
                                )}
                                {! pode_concluir && (
                                    <span className="text-xs text-gray-500 italic">
                                        <i className="fas fa-clock mr-1" />
                                        Aguardando acao do destino atual
                                    </span>
                                )}
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

                {/* ── Tab: Tramitacao (Timeline + Acoes) ── */}
                {activeTab === 'tramitacao' && (
                    <div className="space-y-4">
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

                    {/* Painel de acao unificado */}
                    {pode_concluir && ! ['concluido', 'cancelado', 'aguardando_assinatura'].includes(proc.status) && (
                    <Card title="O que voce deseja fazer?" className="overflow-visible">
                        {/* Mode picker */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
                            {[
                                { v: 'encaminhar', icone: 'fa-paper-plane', cor: 'blue',    titulo: 'Encaminhar',         desc: 'Mandar para outro setor/pessoa' },
                                { v: 'decidir',    icone: 'fa-gavel',       cor: 'emerald', titulo: 'Decidir e Encerrar', desc: 'Deferir/Indeferir/Parcial' },
                                { v: 'arquivar',   icone: 'fa-archive',     cor: 'gray',    titulo: 'Arquivar',           desc: 'Encerrar sem decisao formal' },
                            ].map(op => {
                                const ativo = acaoMode === op.v;
                                const corMap = { blue: 'border-blue-500 bg-blue-50', emerald: 'border-emerald-500 bg-emerald-50', gray: 'border-gray-500 bg-gray-50' };
                                const iconeMap = { blue: 'text-blue-600', emerald: 'text-emerald-600', gray: 'text-gray-600' };
                                return (
                                    <button key={op.v} type="button" onClick={() => setAcaoMode(op.v)}
                                        className={`text-left p-3 rounded-xl border-2 transition-colors ${ativo ? corMap[op.cor] : 'border-gray-200 hover:bg-gray-50'}`}>
                                        <div className="flex items-center gap-2">
                                            <i className={`fas ${op.icone} ${ativo ? iconeMap[op.cor] : 'text-gray-400'}`} />
                                            <div>
                                                <p className="text-sm font-semibold text-gray-800">{op.titulo}</p>
                                                <p className="text-[10px] text-gray-500 leading-tight">{op.desc}</p>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Modo: Encaminhar (despachar atual) */}
                        {acaoMode === 'encaminhar' && (
                          !etapaAtual ? (
                            <div className="py-8 text-center text-gray-400">
                                <i className="fas fa-check-circle text-2xl mb-2 block" />
                                <p className="text-sm">Nenhuma etapa ativa para despachar</p>
                            </div>
                        ) : (
                            <form onSubmit={handleDespachar}>
                                <div className="space-y-4">
                                    <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800 flex items-start gap-2">
                                        <i className="fas fa-info-circle mt-0.5" />
                                        <p>Despache para um <strong>setor</strong> (qualquer pessoa do setor pode receber) e, opcionalmente, indique uma <strong>pessoa especifica</strong> daquele setor.</p>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="relative">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Setor de Destino <span className="text-red-500">*</span>
                                            </label>
                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    value={setorOpen ? setorSearch : (setorSelecionado?.nome || '')}
                                                    onChange={(e) => { setSetorSearch(e.target.value); setSetorOpen(true); }}
                                                    onFocus={() => { setSetorOpen(true); setSetorSearch(''); }}
                                                    placeholder="Digite para buscar setor..."
                                                    className="ds-input pr-9"
                                                />
                                                {despacharForm.data.setor_destino && (
                                                    <button type="button"
                                                        onClick={() => { despacharForm.setData('setor_destino', ''); setSetorSearch(''); }}
                                                        className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                                                        <i className="fas fa-times text-xs" />
                                                    </button>
                                                )}
                                            </div>
                                            {setorOpen && (
                                                <>
                                                    <div className="fixed inset-0 z-30" onClick={() => setSetorOpen(false)} />
                                                    <div className="absolute z-40 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-auto py-1">
                                                        {unidadesFiltradas.length === 0 ? (
                                                            <p className="p-3 text-xs text-gray-400 text-center">Nenhum setor encontrado</p>
                                                        ) : unidadesFiltradas.map(u => {
                                                            const isTopo = u.nivel === 1;
                                                            const indent = (u.nivel - 1) * 16;
                                                            return (
                                                                <button key={u.id} type="button"
                                                                    onClick={() => { despacharForm.setData('setor_destino', u.id); setSetorOpen(false); setSetorSearch(''); }}
                                                                    style={{ paddingLeft: `${12 + indent}px` }}
                                                                    className={`w-full text-left pr-3 py-1.5 text-sm transition-colors ${
                                                                        isTopo
                                                                            ? 'bg-blue-50 text-blue-800 font-bold border-l-4 border-blue-500 hover:bg-blue-100'
                                                                            : 'text-gray-700 hover:bg-gray-100 border-l-4 border-transparent'
                                                                    }`}>
                                                                    {! isTopo && <i className="fas fa-angle-right text-gray-300 text-[10px] mr-1.5" />}
                                                                    {u.nome}
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                </>
                                            )}
                                            {despacharForm.errors.setor_destino && (
                                                <p className="mt-1 text-xs text-red-600">{despacharForm.errors.setor_destino}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Pessoa especifica <span className="text-gray-400 text-xs">(opcional)</span>
                                            </label>
                                            <select
                                                value={despacharForm.data.destinatario_id}
                                                onChange={(e) => despacharForm.setData('destinatario_id', e.target.value)}
                                                className="ds-input"
                                            >
                                                <option value="">— Qualquer pessoa do setor —</option>
                                                {userList
                                                    .filter(u => ! despacharForm.data.setor_destino
                                                        || Number(u.unidade_id) === Number(despacharForm.data.setor_destino))
                                                    .map(u => (
                                                        <option key={u.id} value={u.id}>{u.name}</option>
                                                    ))}
                                            </select>
                                            <p className="mt-1 text-[10px] text-gray-400">
                                                Se vazio, qualquer um do setor podera receber via Caixa Setor.
                                            </p>
                                            {despacharForm.errors.destinatario_id && (
                                                <p className="mt-1 text-xs text-red-600">{despacharForm.errors.destinatario_id}</p>
                                            )}
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
                                            Encaminhar
                                        </Button>
                                        <Button type="button" variant="secondary" icon="fas fa-undo" onClick={handleDevolver}>
                                            Devolver
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        ))}

                        {/* Modo: Decidir e Encerrar */}
                        {acaoMode === 'decidir' && (
                            <div className="space-y-4">
                                <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-800 flex items-start gap-2">
                                    <i className="fas fa-info-circle mt-0.5" />
                                    <p>Registre a <strong>decisao final</strong> sobre o pedido. O processo sera <strong>encerrado</strong> apos a decisao.</p>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Parecer / Justificativa <span className="text-red-500">*</span>
                                    </label>
                                    <textarea value={concluirForm.data.observacao_conclusao}
                                        onChange={(e) => concluirForm.setData('observacao_conclusao', e.target.value)}
                                        rows={5} className="ds-input !h-auto"
                                        placeholder="Justificativa da decisao..." />
                                    {concluirForm.errors.observacao_conclusao && (
                                        <p className="mt-1 text-xs text-red-600">{concluirForm.errors.observacao_conclusao}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Anexar documento <span className="text-gray-400 font-normal">(opcional, ate 50MB)</span>
                                    </label>
                                    {concluirForm.data.anexo ? (
                                        <div className="flex items-center gap-2 p-2 bg-gray-50 border border-gray-200 rounded-lg">
                                            <i className="fas fa-paperclip text-gray-400" />
                                            <span className="text-sm text-gray-700 flex-1 truncate">{concluirForm.data.anexo.name}</span>
                                            <span className="text-xs text-gray-400">{(concluirForm.data.anexo.size / 1024).toFixed(0)} KB</span>
                                            <button type="button" onClick={() => concluirForm.setData('anexo', null)}
                                                className="text-gray-400 hover:text-red-600">
                                                <i className="fas fa-times" />
                                            </button>
                                        </div>
                                    ) : (
                                        <label className="flex items-center justify-center gap-2 p-3 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 hover:bg-blue-50 text-sm text-gray-500">
                                            <i className="fas fa-paperclip" />
                                            <span>Selecionar arquivo (PDF, imagem, etc)</span>
                                            <input type="file" className="hidden"
                                                onChange={(e) => concluirForm.setData('anexo', e.target.files?.[0] || null)} />
                                        </label>
                                    )}
                                    {concluirForm.errors.anexo && (
                                        <p className="mt-1 text-xs text-red-600">{concluirForm.errors.anexo}</p>
                                    )}
                                </div>
                                {solicitacao_portal && (
                                    <div className="bg-blue-50 border border-blue-200 rounded-xl p-3">
                                        <p className="text-xs text-blue-900 font-bold mb-1 flex items-center gap-2">
                                            <i className="fas fa-user-circle" />
                                            Origem: Portal do Cidadao ({solicitacao_portal.codigo})
                                        </p>
                                        <p className="text-[11px] text-blue-700">
                                            Voce pode <strong>responder direto ao cidadao</strong> (sem assinatura ICP-Brasil) ou seguir o fluxo formal com assinatura digital.
                                        </p>
                                    </div>
                                )}

                                <div className="flex flex-wrap items-center gap-2 pt-2">
                                    {solicitacao_portal ? (
                                        <>
                                            <Button type="button" variant="success" icon="fas fa-paper-plane"
                                                loading={concluirForm.processing}
                                                onClick={() => handleDecidir('deferido', true)}>
                                                Deferir e responder direto
                                            </Button>
                                            <Button type="button" variant="danger" icon="fas fa-times-circle"
                                                loading={concluirForm.processing}
                                                onClick={() => handleDecidir('indeferido', true)}>
                                                Indeferir e responder direto
                                            </Button>
                                            <div className="w-full border-t border-gray-200 my-2" />
                                            <p className="text-[11px] text-gray-500 w-full">
                                                Ou siga o fluxo formal com assinatura digital ICP-Brasil:
                                            </p>
                                        </>
                                    ) : null}
                                    <Button type="button" variant={solicitacao_portal ? "secondary" : "success"} icon="fas fa-check-circle"
                                        loading={concluirForm.processing}
                                        onClick={() => handleDecidir('deferido')}>
                                        {solicitacao_portal ? 'Deferir e assinar' : 'Deferir'}
                                    </Button>
                                    <Button type="button" variant="warning" icon="fas fa-balance-scale"
                                        loading={concluirForm.processing}
                                        onClick={() => handleDecidir('parcial')}>
                                        {solicitacao_portal ? 'Deferir Parcial e assinar' : 'Deferir Parcial'}
                                    </Button>
                                    {!solicitacao_portal && (
                                        <Button type="button" variant="danger" icon="fas fa-times-circle"
                                            loading={concluirForm.processing}
                                            onClick={() => handleDecidir('indeferido')}>
                                            Indeferir
                                        </Button>
                                    )}
                                    {solicitacao_portal && (
                                        <Button type="button" variant="danger" icon="fas fa-times-circle"
                                            loading={concluirForm.processing}
                                            onClick={() => handleDecidir('indeferido')}>
                                            Indeferir e assinar
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Modo: Arquivar */}
                        {acaoMode === 'arquivar' && (
                            <div className="space-y-4">
                                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-800 flex items-start gap-2">
                                    <i className="fas fa-exclamation-triangle mt-0.5" />
                                    <p>Arquivar o processo <strong>encerra-o sem decisao formal</strong> (deferido/indeferido). Use quando o pedido perdeu objeto, foi desistido, ou nao se aplica mais.</p>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Motivo do arquivamento (opcional)</label>
                                    <textarea value={concluirForm.data.observacao_conclusao}
                                        onChange={(e) => concluirForm.setData('observacao_conclusao', e.target.value)}
                                        rows={3} className="ds-input !h-auto"
                                        placeholder="Ex.: pedido desistido pelo requerente, objeto perdeu sentido..." />
                                </div>
                                <div className="pt-2">
                                    <Button type="button" variant="secondary" icon="fas fa-archive"
                                        loading={concluirForm.processing} onClick={handleArquivar}>
                                        Arquivar Processo
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Card>
                    )}
                    </div>
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
                                value={concluirForm.data.observacao_conclusao}
                                onChange={(e) => concluirForm.setData('observacao_conclusao', e.target.value)}
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
