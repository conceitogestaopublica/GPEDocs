/**
 * Assinaturas Pendentes — GED
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';
import AssinarModal from '../../../Components/AssinarModal';

export default function Assinaturas({ pendentes, aguardando_outros, concluidas, assinadas, filtros = {}, sistemas_origem = [], tipos_documentais = [], pastas = [] }) {
    // Retro-compat: se backend antigo só mandar `assinadas`, usa ele como `concluidas`.
    const aguardandoData = aguardando_outros ?? { data: [], total: 0 };
    const concluidasData = concluidas ?? assinadas ?? { data: [], total: 0 };

    const totalPendentes = (pendentes || []).length;
    const totalAguardando = aguardandoData?.total ?? (Array.isArray(aguardandoData) ? aguardandoData.length : 0);
    const totalConcluidas = concluidasData?.total ?? (Array.isArray(concluidasData) ? concluidasData.length : 0);

    const [activeTab, setActiveTab] = useState(
        totalPendentes > 0 ? 'pendentes' :
        totalAguardando > 0 ? 'aguardando' :
        totalConcluidas > 0 ? 'concluidas' : 'pendentes'
    );
    const [assinarModal, setAssinarModal] = useState(null);
    const [recusarModal, setRecusarModal] = useState(null);

    return (
        <AdminLayout>
            <Head title="Assinaturas" />
            <PageHeader title="Assinaturas" subtitle="Pendentes de sua assinatura e historico assinado" />

            {/* Tabs */}
            <div className="flex gap-2 mb-6 items-center">
                {[
                    { key: 'pendentes',  label: `Pendentes (${totalPendentes})`,            icon: 'fas fa-clock' },
                    { key: 'aguardando', label: `Aguardando outros (${totalAguardando})`,   icon: 'fas fa-hourglass-half' },
                    { key: 'concluidas', label: `Concluídas (${totalConcluidas})`,          icon: 'fas fa-check-circle' },
                ].map(tab => (
                    <button key={tab.key} onClick={() => setActiveTab(tab.key)}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors
                            ${activeTab === tab.key ? 'bg-blue-600 text-white shadow-md' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`}>
                        <i className={`${tab.icon} text-xs`} />
                        {tab.label}
                    </button>
                ))}
                <a href="/validar-assinatura" target="_blank" rel="noopener"
                   className="ml-auto flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium text-green-700 bg-green-50 border border-green-200 hover:bg-green-100 transition-colors">
                    <i className="fas fa-shield-alt text-xs" />
                    Validar PDF assinado
                </a>
            </div>

            {/* Filtro de origem (sistema integrado) — visivel em ambas as tabs */}
            {sistemas_origem.length > 0 && (
                <div className="bg-white rounded-xl border border-gray-200 p-2 mb-3 flex items-center gap-2 flex-wrap">
                    <span className="text-[10px] uppercase tracking-wide text-gray-500 font-semibold ml-2">Origem:</span>
                    <button onClick={() => router.get('/assinaturas', {}, { preserveState: true })}
                        className={`text-[11px] px-3 py-1 rounded-full transition-colors ${
                            ! filtros.origem ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}>
                        Todos
                    </button>
                    <button onClick={() => router.get('/assinaturas', { origem: 'interno' }, { preserveState: true })}
                        className={`text-[11px] px-3 py-1 rounded-full transition-colors ${
                            filtros.origem === 'interno' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}>
                        <i className="fas fa-home mr-1" />Internos
                    </button>
                    {sistemas_origem.map(s => (
                        <button key={s.codigo}
                            onClick={() => router.get('/assinaturas', { origem: s.codigo }, { preserveState: true })}
                            className={`text-[11px] px-3 py-1 rounded-full transition-colors ${
                                filtros.origem === s.codigo ? 'bg-violet-600 text-white' : 'bg-violet-50 text-violet-700 hover:bg-violet-100'
                            }`} title={s.nome}>
                            <i className="fas fa-plug mr-1" />{s.codigo}
                        </button>
                    ))}
                </div>
            )}

            {activeTab === 'pendentes' && (
                <Card padding={false}>
                    {(pendentes || []).length === 0 ? (
                        <div className="py-12 text-center text-gray-400">
                            <i className="fas fa-check-circle text-3xl mb-2 block" />
                            <p>Nenhuma assinatura pendente</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {(pendentes || []).map(a => <PendenteRow key={a.id} a={a}
                                onAssinar={() => setAssinarModal(a)}
                                onRecusar={() => setRecusarModal(a)} />)}
                        </div>
                    )}
                </Card>
            )}

            {activeTab === 'aguardando' && (
                <AssinadasView assinadas={aguardandoData} filtrosIniciais={filtros}
                               emptyText="Nenhum documento que você assinou está aguardando outras assinaturas."
                               showAguardandoBadge={true} />
            )}

            {activeTab === 'concluidas' && (
                <AssinadasView assinadas={concluidasData} filtrosIniciais={filtros}
                               emptyText="Nenhum documento totalmente assinado."
                               permitirArquivar={true}
                               tipos_documentais={tipos_documentais}
                               pastas={pastas} />
            )}

            <AssinarModal assinatura={assinarModal} onClose={() => setAssinarModal(null)} />
            <RecusarModal assinatura={recusarModal} onClose={() => setRecusarModal(null)} />
        </AdminLayout>
    );
}

function AssinadasView({ assinadas, filtrosIniciais, emptyText, showAguardandoBadge = false, permitirArquivar = false, tipos_documentais = [], pastas = [] }) {
    const items = assinadas?.data || (Array.isArray(assinadas) ? assinadas : []);
    const links = assinadas?.links || null;

    const [busca, setBusca]   = useState(filtrosIniciais?.busca || '');
    const [tipo, setTipo]     = useState(filtrosIniciais?.tipo_filtro || '');
    const [dataDe, setDataDe] = useState(filtrosIniciais?.data_de || '');
    const [dataAte, setDataAte] = useState(filtrosIniciais?.data_ate || '');

    // Selecao em lote (apenas quando permitirArquivar)
    const [selecionados, setSelecionados] = useState(new Set());
    const [arquivarTarget, setArquivarTarget] = useState(null); // {ids:[], docs:[]}

    const toggleSel = (id) => {
        const novo = new Set(selecionados);
        novo.has(id) ? novo.delete(id) : novo.add(id);
        setSelecionados(novo);
    };
    const toggleSelAll = () => {
        if (selecionados.size === items.length) setSelecionados(new Set());
        else setSelecionados(new Set(items.map(a => a.documento?.id).filter(Boolean)));
    };

    const abrirArquivarLote = () => {
        const docs = items.filter(a => selecionados.has(a.documento?.id))
                          .map(a => a.documento)
                          .filter(Boolean);
        if (docs.length === 0) return;
        setArquivarTarget({ ids: docs.map(d => d.id), docs });
    };
    const abrirArquivarUm = (doc) => {
        setArquivarTarget({ ids: [doc.id], docs: [doc] });
    };

    const aplicar = (e) => {
        e?.preventDefault();
        router.get('/assinaturas',
            { busca: busca || undefined, tipo_filtro: tipo || undefined,
              data_de: dataDe || undefined, data_ate: dataAte || undefined },
            { preserveState: true, replace: true });
    };

    const limpar = () => {
        setBusca(''); setTipo(''); setDataDe(''); setDataAte('');
        router.get('/assinaturas', {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-3">
                <form onSubmit={aplicar} className="flex flex-wrap items-end gap-2">
                    <div className="relative flex-1 min-w-[260px]">
                        <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Buscar</label>
                        <i className="fas fa-search absolute left-3 top-[60%] -translate-y-1/2 text-gray-400 text-xs" />
                        <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                            placeholder="Documento, mensagem, e-mail ou CPF..."
                            className="ds-input pl-9" />
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Tipo</label>
                        <select value={tipo} onChange={(e) => { setTipo(e.target.value); }}
                            className="ds-input w-44">
                            <option value="">Todos</option>
                            <option value="simples">Simples</option>
                            <option value="qualificada">Qualificada (ICP)</option>
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
                    {(busca || tipo || dataDe || dataAte) && (
                        <button type="button" onClick={limpar}
                            className="text-xs text-gray-500 hover:text-gray-800 px-2 pb-2">
                            <i className="fas fa-times mr-1" />Limpar
                        </button>
                    )}
                </form>
            </div>

            {/* Barra de selecao em lote (so quando permitirArquivar) */}
            {permitirArquivar && items.length > 0 && (
                <div className="bg-white rounded-xl border border-gray-200 px-4 py-2 mb-3 flex items-center justify-between gap-3">
                    <label className="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                        <input type="checkbox"
                            checked={selecionados.size === items.length && items.length > 0}
                            ref={el => { if (el) el.indeterminate = selecionados.size > 0 && selecionados.size < items.length; }}
                            onChange={toggleSelAll}
                            className="rounded border-gray-300 text-blue-600" />
                        {selecionados.size > 0
                            ? <span><strong>{selecionados.size}</strong> selecionado(s)</span>
                            : <span>Selecionar todos</span>
                        }
                    </label>
                    {selecionados.size > 0 && (
                        <div className="flex items-center gap-2">
                            <button onClick={abrirArquivarLote}
                                className="text-xs px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                                <i className="fas fa-folder-plus mr-1" />
                                Classificar e Arquivar ({selecionados.size})
                            </button>
                            <button onClick={() => setSelecionados(new Set())}
                                className="text-xs text-gray-500 hover:text-gray-800">
                                <i className="fas fa-times mr-1" />Limpar selecao
                            </button>
                        </div>
                    )}
                </div>
            )}

            <Card padding={false}>
                {items.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <i className="fas fa-file-signature text-3xl mb-2 block" />
                        <p>{emptyText || 'Nenhuma assinatura encontrada'}</p>
                    </div>
                ) : (
                    <div className="divide-y divide-gray-100">
                        {items.map(a => <AssinadaRow key={a.id} a={a}
                            showAguardandoBadge={showAguardandoBadge}
                            permitirArquivar={permitirArquivar}
                            selecionado={selecionados.has(a.documento?.id)}
                            onToggleSel={() => toggleSel(a.documento?.id)}
                            onArquivar={() => abrirArquivarUm(a.documento)} />)}
                    </div>
                )}
            </Card>

            {/* Modal Classificar e Arquivar */}
            {arquivarTarget && (
                <ClassificarArquivarModal
                    target={arquivarTarget}
                    tiposDocumentais={tipos_documentais}
                    pastas={pastas}
                    onClose={() => { setArquivarTarget(null); setSelecionados(new Set()); }}
                />
            )}

            {links && (
                <div className="mt-3 flex justify-center gap-1 flex-wrap">
                    {links.map((l, i) => (
                        <button key={i} disabled={!l.url}
                            onClick={() => l.url && router.get(l.url, {}, { preserveState: true })}
                            className={`text-xs px-3 py-1.5 rounded-lg ${
                                l.active ? 'bg-blue-600 text-white' :
                                l.url ? 'bg-white border border-gray-200 hover:bg-gray-50' :
                                'bg-gray-50 text-gray-300 cursor-not-allowed'
                            }`}
                            dangerouslySetInnerHTML={{ __html: l.label }} />
                    ))}
                </div>
            )}
        </>
    );
}

function AssinadaRow({ a, showAguardandoBadge = false, permitirArquivar = false, selecionado = false, onToggleSel, onArquivar }) {
    const ehQualificada = a.tipo_assinatura === 'qualificada';
    const dataFmt = a.assinado_em
        ? new Date(a.assinado_em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

    return (
        <div className={`px-5 py-4 hover:bg-gray-50 transition-colors ${selecionado ? 'bg-blue-50/40' : ''}`}>
            <div className="flex items-start gap-3">
                {permitirArquivar && (
                    <input type="checkbox" checked={selecionado} onChange={onToggleSel}
                        className="mt-3 rounded border-gray-300 text-blue-600 shrink-0" />
                )}
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${
                    ehQualificada ? 'bg-green-100' : 'bg-blue-100'
                }`}>
                    <i className={`fas fa-${ehQualificada ? 'shield-alt' : 'check'} ${
                        ehQualificada ? 'text-green-600' : 'text-blue-600'
                    } text-sm`} />
                </div>

                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <Link href={`/documentos/${a.documento?.id}`}
                            className="text-sm font-medium text-gray-800 hover:text-blue-600 truncate">
                            {a.documento?.nome}
                        </Link>
                        {a.solicitacao && (
                            <span className="text-[10px] font-mono text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded">
                                SOL-{(a.solicitacao.created_at || '').slice(0,4)}-{String(a.solicitacao.id).padStart(6,'0')}
                            </span>
                        )}
                        {ehQualificada && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-bold">
                                ICP-Brasil
                            </span>
                        )}
                        {showAguardandoBadge && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold flex items-center gap-1">
                                <i className="fas fa-hourglass-half" /> Aguardando outros
                            </span>
                        )}
                    </div>
                    <p className="text-xs text-gray-500 mt-0.5">
                        Solicitado por <strong>{a.solicitacao?.solicitante?.name}</strong>
                        {a.solicitacao?.mensagem && <span className="text-gray-400"> · {a.solicitacao.mensagem}</span>}
                    </p>
                    <p className="text-[11px] text-gray-400 mt-0.5">
                        <i className="fas fa-clock mr-1" />Assinado em {dataFmt}
                        {a.cpf_signatario && <span className="ml-2"><i className="fas fa-id-card mr-1" />CPF {mascararCpf(a.cpf_signatario)}</span>}
                        {a.certificado?.issuer_cn && <span className="ml-2"><i className="fas fa-certificate mr-1" />{a.certificado.issuer_cn}</span>}
                    </p>
                </div>

                <div className="flex items-center gap-2 shrink-0">
                    {a.arquivo_assinado_path && (
                        <>
                            <a href={`/assinaturas/${a.id}/download-assinado?inline=1`} target="_blank" rel="noopener"
                                className="text-[11px] px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                                title="Visualizar PDF">
                                <i className="fas fa-eye" />
                            </a>
                            <a href={`/assinaturas/${a.id}/download-assinado`}
                                className="text-[11px] px-3 py-1.5 rounded-lg bg-white border border-blue-300 text-blue-700 hover:bg-blue-50 transition-colors"
                                title="Baixar PDF">
                                <i className="fas fa-download" />
                            </a>
                        </>
                    )}
                    {a.solicitacao && (
                        <a href={`/assinaturas/${a.solicitacao.id}/manifesto`}
                            className="text-[11px] px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                            title="Manifesto de auditoria">
                            <i className="fas fa-file-pdf" />
                        </a>
                    )}
                    {showAguardandoBadge && (
                        <button onClick={() => simularRestantes(a.id)}
                            className="text-[11px] px-3 py-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors flex items-center gap-1"
                            title="DEV: simular assinatura dos signatários pendentes e fechar o ciclo">
                            <i className="fas fa-flask" /> Simular restantes
                        </button>
                    )}
                    {permitirArquivar && (
                        <button onClick={onArquivar}
                            className="text-[11px] px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors flex items-center gap-1"
                            title="Classificar e arquivar este documento em uma pasta">
                            <i className="fas fa-folder-plus" /> Arquivar
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function simularRestantes(assinaturaId) {
    if (! confirm('DEV ONLY — Marcar todas as assinaturas pendentes desta solicitação como assinadas (sem certificado real) e disparar o webhook final?')) return;
    router.post(`/assinaturas/${assinaturaId}/simular-restantes`, {}, {
        preserveScroll: true,
    });
}

function ClassificarArquivarModal({ target, tiposDocumentais = [], pastas = [], onClose }) {
    const docs = target?.docs || [];
    const ids = target?.ids || [];

    // Quantos dos selecionados ainda nao tem tipo documental
    const semTipo = docs.filter(d => ! d.tipo_documental_id).length;

    // Hierarquia de pastas (filhos sob o pai)
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

    const { data, setData, post, processing, errors } = useForm({
        documento_ids:      ids,
        tipo_documental_id: '',
        pasta_id:           '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/assinaturas/classificar-arquivar', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    const pastaSel = pastasTree.find(p => String(p.id) === String(data.pasta_id));

    return (
        <Modal show={!! target} onClose={onClose}
            title={ids.length === 1 ? 'Classificar e Arquivar' : `Classificar e Arquivar (${ids.length})`}>
            <form onSubmit={submit} className="space-y-4">
                {/* Lista de docs selecionados */}
                <div className="bg-gray-50 border border-gray-200 rounded-xl p-3 max-h-32 overflow-y-auto">
                    <p className="text-[10px] uppercase font-semibold text-gray-500 mb-1">Documentos ({ids.length})</p>
                    <ul className="space-y-0.5">
                        {docs.slice(0, 10).map(d => (
                            <li key={d.id} className="text-xs text-gray-700 flex items-center gap-2">
                                <i className="fas fa-file-pdf text-red-400 text-[10px]" />
                                <span className="truncate">{d.nome}</span>
                                {d.tipo_documental_id && (
                                    <span className="text-[9px] px-1 rounded bg-blue-100 text-blue-700 ml-auto">com tipo</span>
                                )}
                            </li>
                        ))}
                        {docs.length > 10 && (
                            <li className="text-[10px] text-gray-400 italic">...e mais {docs.length - 10} documento(s)</li>
                        )}
                    </ul>
                </div>

                {/* Tipo Documental — opcional ou obrigatorio dependendo do estado */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Tipo Documental
                        {semTipo > 0 && <span className="text-amber-600 text-xs ml-1">({semTipo} sem tipo)</span>}
                        {semTipo === 0 && <span className="text-gray-400 text-xs ml-1">(opcional — todos ja tem)</span>}
                    </label>
                    <select value={data.tipo_documental_id}
                        onChange={(e) => setData('tipo_documental_id', e.target.value)}
                        className="ds-input">
                        <option value="">— {semTipo > 0 ? 'Selecione (sera aplicado nos sem tipo)' : 'Manter o existente'} —</option>
                        {tiposDocumentais.map(t => (
                            <option key={t.id} value={t.id}>{t.nome}</option>
                        ))}
                    </select>
                    <p className="mt-1 text-[10px] text-gray-400">
                        Documentos que ja tem tipo nao serao alterados. So preenche os que estao sem.
                    </p>
                    {errors.tipo_documental_id && <p className="mt-1 text-xs text-red-600">{errors.tipo_documental_id}</p>}
                </div>

                {/* Pasta */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Pasta de destino <span className="text-red-500">*</span>
                    </label>
                    {pastasTree.length === 0 ? (
                        <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                            Nenhuma pasta cadastrada. Crie no Repositorio antes de arquivar.
                        </p>
                    ) : (
                        <>
                            <select value={data.pasta_id}
                                onChange={(e) => setData('pasta_id', e.target.value)}
                                className="ds-input">
                                <option value="">— Selecione a pasta —</option>
                                {pastasTree.map(p => (
                                    <option key={p.id} value={p.id}>
                                        {'— '.repeat(p.nivel)}{p.nome}{p.descricao ? ` — ${p.descricao}` : ''}
                                    </option>
                                ))}
                            </select>
                            {pastaSel?.descricao && (
                                <p className="mt-1 text-[11px] text-gray-500 italic">
                                    <i className="fas fa-info-circle mr-1" />{pastaSel.descricao}
                                </p>
                            )}
                        </>
                    )}
                    {errors.pasta_id && <p className="mt-1 text-xs text-red-600">{errors.pasta_id}</p>}
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-folder-plus"
                        disabled={! data.pasta_id || pastasTree.length === 0}>
                        Arquivar {ids.length > 1 ? `${ids.length} docs` : ''}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function PendenteRow({ a, onAssinar, onRecusar }) {
    const ehExterno = !! a.documento?.sistema_origem;
    const meta = a.documento?.metadados_externos || {};
    const metaEntries = Object.entries(meta).filter(([, v]) => v !== null && v !== '');

    return (
        <div className="px-5 py-4 hover:bg-gray-50">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3 flex-1 min-w-0">
                    <div className={`w-10 h-10 ${ehExterno ? 'bg-violet-100' : 'bg-amber-100'} rounded-lg flex items-center justify-center shrink-0`}>
                        <i className={`fas fa-${ehExterno ? 'plug' : 'file-signature'} ${ehExterno ? 'text-violet-600' : 'text-amber-600'} text-sm`} />
                    </div>
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                            <Link href={`/documentos/${a.documento?.id}`} className="text-sm font-medium text-gray-800 hover:text-blue-600 truncate">
                                {a.documento?.nome}
                            </Link>
                            {a.documento?.tipo_documental?.nome && (
                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-bold uppercase">
                                    {a.documento.tipo_documental.nome}
                                </span>
                            )}
                            {ehExterno && (
                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 font-bold uppercase" title="Documento de sistema externo">
                                    <i className="fas fa-plug mr-1" />{a.documento.sistema_origem}
                                </span>
                            )}
                            {a.documento?.numero_externo && (
                                <span className="text-[10px] font-mono text-gray-500">{a.documento.numero_externo}</span>
                            )}
                        </div>
                        <p className="text-xs text-gray-500 mt-0.5">
                            Solicitado por <strong>{a.solicitacao?.solicitante?.name}</strong> em {new Date(a.created_at).toLocaleDateString('pt-BR')}
                        </p>
                        {a.solicitacao?.mensagem && (
                            <p className="text-[11px] text-gray-500 italic mt-0.5">"{a.solicitacao.mensagem}"</p>
                        )}
                        {metaEntries.length > 0 && (
                            <div className="mt-2 grid grid-cols-2 md:grid-cols-3 gap-x-3 gap-y-1 bg-violet-50 border border-violet-100 rounded-lg p-2">
                                {metaEntries.slice(0, 6).map(([k, v]) => (
                                    <div key={k} className="text-[11px]">
                                        <span className="text-gray-500 mr-1">{formatarLabel(k)}:</span>
                                        <span className="text-gray-800 font-medium">{formatarValor(k, v)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                        {a.solicitacao?.prazo && (
                            <p className="text-[10px] text-orange-500 mt-1">
                                <i className="fas fa-clock mr-1" />Prazo: {new Date(a.solicitacao.prazo).toLocaleDateString('pt-BR')}
                            </p>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <Button size="sm" icon="fas fa-pen-nib" onClick={onAssinar}>Assinar</Button>
                    <Button size="sm" variant="ghost" onClick={onRecusar}>Recusar</Button>
                </div>
            </div>
        </div>
    );
}

function formatarLabel(k) {
    return k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
function formatarValor(k, v) {
    if (typeof v === 'number' && (k.includes('valor') || k.includes('preco') || k.includes('total'))) {
        return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}/.test(v)) {
        return new Date(v).toLocaleDateString('pt-BR');
    }
    return String(v);
}

function mascararCpf(cpf) {
    const d = (cpf || '').replace(/\D/g, '');
    if (d.length !== 11) return cpf;
    return `${d.slice(0,3)}.***.***-${d.slice(-2)}`;
}

function RecusarModal({ assinatura, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ motivo: '' });

    const submit = (e) => {
        e.preventDefault();
        post(`/assinaturas/${assinatura.id}/recusar`, { onSuccess: onClose });
    };

    if (!assinatura) return null;

    return (
        <Modal show={!!assinatura} onClose={onClose} title="Recusar Assinatura">
            <form onSubmit={submit} className="space-y-4">
                <div className="bg-red-50 rounded-xl p-4">
                    <p className="text-sm font-medium text-red-800">{assinatura.documento?.nome}</p>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Motivo da recusa</label>
                    <textarea value={data.motivo} onChange={(e) => setData('motivo', e.target.value)}
                        className="ds-input !h-auto" rows={3} placeholder="Informe o motivo..." />
                    {errors.motivo && <p className="mt-1 text-xs text-red-600">{errors.motivo}</p>}
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant="danger" type="submit" loading={processing} icon="fas fa-times">Recusar</Button>
                </div>
            </form>
        </Modal>
    );
}
