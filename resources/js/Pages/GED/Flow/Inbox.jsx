/**
 * GPE Flow — Inbox unificada (memorandos + circulares + oficios + processos).
 * Layout estilo /assinaturas: filtros + linhas com acoes inline (visualizar,
 * baixar, assinar, arquivar no GED) sem precisar abrir o item.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';
import AssinarModal from '../../../Components/AssinarModal';
import { SITUACAO_PRAZO } from '../../../Components/PrazosDoProcesso';

const VISTA_CONFIG = {
    pessoal: {
        titulo: 'Caixa Pessoal',
        subtitulo: 'Itens endereçados pessoalmente a você, em estado ativo',
        icone: 'fa-inbox',
        cor: 'blue',
        emptyTitulo: 'Sem itens pendentes pra você',
        emptyTexto: 'Quando alguem encaminhar algo diretamente a você, aparece aqui.',
    },
    setor: {
        titulo: 'Caixa Setor',
        subtitulo: 'Itens endereçados ao seu setor (qualquer um pode pegar)',
        icone: 'fa-users',
        cor: 'indigo',
        emptyTitulo: 'Sem itens pendentes no setor',
        emptyTexto: 'Itens encaminhados a sua unidade sem destinatário específico aparecem aqui.',
    },
    aguardando_assinatura: {
        titulo: 'Aguardando Assinatura',
        subtitulo: 'Você decidiu, falta assinar digitalmente para tornar oficial',
        icone: 'fa-file-signature',
        cor: 'purple',
        emptyTitulo: 'Sem assinaturas pendentes',
        emptyTexto: 'Decisoes de processo que você tomar aparecem aqui até serem assinadas (Lei 14.063/2020).',
    },
    em_tramitacao: {
        titulo: 'Em Tramitação',
        subtitulo: 'Itens em fluxo onde você participa, ainda não finalizados',
        icone: 'fa-share',
        cor: 'orange',
        emptyTitulo: 'Nada em tramitação',
        emptyTexto: 'Itens que você originou ou recebeu e ainda estão em andamento aparecem aqui.',
    },
    concluidos: {
        titulo: 'Concluídos',
        subtitulo: 'Itens finalizados — deferidos, indeferidos, arquivados ou cancelados',
        icone: 'fa-check-double',
        cor: 'green',
        emptyTitulo: 'Nenhum item concluído ainda',
        emptyTexto: 'Itens com decisão final ou arquivados aparecem aqui.',
    },
    saida: {
        titulo: 'Saída (Originados)',
        subtitulo: 'Tudo que você criou — em qualquer estado',
        icone: 'fa-paper-plane',
        cor: 'emerald',
        emptyTitulo: 'Você ainda não originou nada',
        emptyTexto: 'Memorandos, ofícios e processos que você criar aparecem aqui.',
    },
    rascunhos: {
        titulo: 'Rascunhos',
        subtitulo: 'Documentos que você começou e ainda não enviou',
        icone: 'fa-pencil-alt',
        cor: 'yellow',
        emptyTitulo: 'Nenhum rascunho',
        emptyTexto: 'Documentos com status rascunho aparecem aqui.',
    },
};

const TIPO_CONFIG = {
    memorando: { label: 'Memorando', icone: 'fa-envelope',     cor: 'amber',  link: 'memorandos' },
    circular:  { label: 'Circular',  icone: 'fa-bullhorn',     cor: 'rose',   link: 'circulares' },
    oficio:    { label: 'Ofício',    icone: 'fa-file-alt',     cor: 'cyan',   link: 'oficios' },
    processo:  { label: 'Processo',  icone: 'fa-folder-open',  cor: 'indigo', link: 'processos' },
};

export default function FlowInbox({ vista, items, filtros = {}, aviso_sem_unidade = false, pastas = [], meus_setores = [], contagens = {}, minhas_etiquetas = [] }) {
    const cfg = VISTA_CONFIG[vista];
    const lista = items?.data || [];
    const links = items?.links || null;
    const total = items?.total ?? lista.length;

    const [busca, setBusca]       = useState(filtros.busca || '');
    const [tipo, setTipo]         = useState(filtros.tipo || '');
    const [naoLidos, setNaoLidos] = useState(!! filtros.nao_lidos);
    const [dataDe, setDataDe]     = useState(filtros.data_de || '');
    const [dataAte, setDataAte]   = useState(filtros.data_ate || '');
    const [setor, setSetor]       = useState(filtros.setor ? String(filtros.setor) : '');

    // Modais inline
    const [assinarItem, setAssinarItem] = useState(null);
    // {tipo: 'processo'|'memorando'|'oficio'|'circular', item_id, nome}
    const [arquivarItem, setArquivarItem] = useState(null);

    const arquivarForm = useForm({ pasta_id: '' });

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

    const aplicar = (e, aba = filtros.aba, extra = {}) => {
        e?.preventDefault();
        router.get(window.location.pathname,
            {
                busca: busca || undefined, tipo: tipo || undefined,
                nao_lidos: naoLidos ? 1 : undefined,
                data_de: dataDe || undefined, data_ate: dataAte || undefined,
                setor: setor || undefined,
                aba: vista === 'setor' && aba !== 'abertos' ? aba : undefined,
                etiqueta: filtros.etiqueta || undefined,
                ...extra,
            },
            { preserveState: true, replace: true });
    };

    // Etiquetas do setor (TR 4.19): seleção em lote e gaveta
    const chaveItem = (i) => `${i.tipo}:${i.item_id}`;
    const [selecionados, setSelecionados] = useState([]);
    const [etiquetaLote, setEtiquetaLote] = useState('');
    const etiquetar = (acao) => router.post('/flow/etiquetar', {
        etiqueta_id: etiquetaLote, acao,
        itens: selecionados.map(k => { const [t, id] = k.split(':'); return { tipo: t, item_id: Number(id) }; }),
    }, { preserveScroll: true, onSuccess: () => setSelecionados([]) });
    const gaveta = minhas_etiquetas.find(e => e.id === filtros.etiqueta);

    // Não lidos por mim, por módulo (TR 4.10.5)
    const porTipo = vista === 'setor' ? contagens.setor_por_tipo : vista === 'pessoal' ? contagens.pessoal_por_tipo : null;
    const ROTULO_TIPO = { processo: ['processo', 'processos'], memorando: ['memorando', 'memorandos'], oficio: ['ofício', 'ofícios'] };

    const limpar = () => {
        setBusca(''); setTipo(''); setNaoLidos(false); setDataDe(''); setDataAte(''); setSetor('');
        router.get(window.location.pathname, {}, { preserveState: true, replace: true });
    };

    const handleArquivar = (e) => {
        e.preventDefault();
        if (! arquivarItem || ! arquivarForm.data.pasta_id) return;
        const url = {
            processo:  `/processos/${arquivarItem.item_id}/arquivar-no-ged`,
            memorando: `/memorandos/${arquivarItem.item_id}/arquivar-no-ged`,
            oficio:    `/oficios/${arquivarItem.item_id}/arquivar-no-ged`,
            circular:  `/circulares/${arquivarItem.item_id}/arquivar-no-ged`,
        }[arquivarItem.tipo];
        if (! url) return;
        arquivarForm.post(url, {
            preserveScroll: true,
            onSuccess: () => { arquivarForm.reset(); setArquivarItem(null); },
        });
    };

    return (
        <AdminLayout>
            <Head title={cfg.titulo} />
            <PageHeader title={cfg.titulo} subtitle={cfg.subtitulo} />

            {aviso_sem_unidade && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 flex items-start gap-2">
                    <i className="fas fa-exclamation-triangle text-amber-600 mt-0.5" />
                    <div>
                        <p className="text-xs font-semibold text-amber-800">Você não esta vinculado a nenhuma unidade do organograma</p>
                        <p className="text-[11px] text-amber-700">Peça ao admin para vincular seu usuário a uma unidade. Sem isso a Inbox do Setor fica vazia.</p>
                    </div>
                </div>
            )}

            {/* Caixa do setor em abas por situação (TR 4.9.9) */}
            {vista === 'setor' && ! aviso_sem_unidade && (
                <div className="flex flex-wrap items-center gap-1 mb-3 border-b border-gray-200">
                    {[
                        ['abertos', 'Em aberto', null],
                        ['nao_lidos', 'Não lidos por mim', contagens.setor_nao_lidos],
                        ['concluidos', 'Concluídos', null],
                    ].map(([v, rotulo, n]) => {
                        const ativa = (filtros.aba || 'abertos') === v;
                        return (
                            <button key={v} type="button" onClick={() => aplicar(null, v)}
                                className={`px-4 py-2 text-sm -mb-px border-b-2 transition-colors ${ativa ? 'border-cyan-600 text-cyan-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-800'}`}>
                                {rotulo}
                                {n > 0 && <span className="ml-1.5 text-[10px] font-bold bg-blue-100 text-blue-700 rounded-full px-1.5 py-0.5">{n}</span>}
                            </button>
                        );
                    })}
                </div>
            )}

            {porTipo && Object.keys(porTipo).length > 0 && (
                <p className="text-xs text-gray-600 mb-3">
                    <i className="fas fa-envelope mr-1 text-blue-500" />
                    Não lidos por você:{' '}
                    {Object.entries(porTipo).map(([t, n], i) => (
                        <span key={t}>{i > 0 && ' · '}<b>{n}</b> {(ROTULO_TIPO[t] || [t, t])[n === 1 ? 0 : 1]}</span>
                    ))}
                </p>
            )}

            {/* Gavetas: etiquetas do setor (TR 4.19.2, 4.19.9) */}
            {minhas_etiquetas.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5 mb-3">
                    <span className="text-xs text-gray-500 mr-1"><i className="fas fa-tags mr-1" />Etiquetas:</span>
                    <button type="button" onClick={() => aplicar(null, filtros.aba, { etiqueta: undefined })}
                        className={`text-xs rounded-full px-2.5 py-1 border ${! filtros.etiqueta ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200'}`}>Todas</button>
                    {minhas_etiquetas.map(e => (
                        <button key={e.id} type="button" onClick={() => aplicar(null, filtros.aba, { etiqueta: e.id })}
                            title={`${e.setor}${e.parent_id ? ' — dentro de ' + (minhas_etiquetas.find(x => x.id === e.parent_id)?.nome || '') : ''}`}
                            style={{ color: e.cor_texto, backgroundColor: e.cor_fundo }}
                            className={`text-xs rounded-full px-2.5 py-1 ${filtros.etiqueta === e.id ? 'ring-2 ring-offset-1 ring-cyan-600' : ''}`}>
                            {e.parent_id ? '↳ ' : ''}{e.nome}
                        </button>
                    ))}
                    <a href="/flow/etiquetas" className="text-xs text-cyan-700 hover:underline ml-1">Gerenciar</a>
                    {gaveta && <span className="text-xs text-gray-500 ml-2">Mostrando a gaveta <b>{gaveta.nome}</b> (e as de dentro dela)</span>}
                </div>
            )}

            {/* Filtros */}
            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-3">
                <form onSubmit={aplicar} className="flex flex-wrap items-end gap-2">
                    <div className="relative flex-1 min-w-[260px]">
                        <label className="block text-sm font-medium text-navy-900 mb-1">Buscar</label>
                        <i className="fas fa-search absolute left-3 top-[60%] -translate-y-1/2 text-gray-400 text-xs" />
                        <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                            placeholder="Número ou assunto..." className="ds-input pl-9" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-navy-900 mb-1">Tipo</label>
                        <select value={tipo} onChange={(e) => setTipo(e.target.value)} className="ds-input w-44">
                            <option value="">Todos</option>
                            <option value="memorando">Memorandos</option>
                            <option value="oficio">Ofícios</option>
                            <option value="processo">Processos</option>
                        </select>
                    </div>
                    {meus_setores.length > 1 && (
                        <div>
                            <label className="block text-sm font-medium text-navy-900 mb-1">Setor</label>
                            <select value={setor} onChange={(e) => setSetor(e.target.value)} className="ds-input w-56">
                                <option value="">Todos os meus setores</option>
                                {meus_setores.map(s => <option key={s.id} value={s.id}>{s.nome}</option>)}
                            </select>
                        </div>
                    )}
                    <div>
                        <label className="block text-sm font-medium text-navy-900 mb-1">De</label>
                        <input type="date" value={dataDe} onChange={(e) => setDataDe(e.target.value)} className="ds-input w-40" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-navy-900 mb-1">Até</label>
                        <input type="date" value={dataAte} onChange={(e) => setDataAte(e.target.value)} className="ds-input w-40" />
                    </div>
                    {(vista === 'pessoal' || vista === 'setor') && (
                        <label className="flex items-center gap-2 cursor-pointer pb-2.5">
                            <input type="checkbox" checked={naoLidos} onChange={(e) => setNaoLidos(e.target.checked)}
                                className="rounded border-gray-300 text-blue-600" />
                            <span className="text-xs text-gray-700">Apenas não lidos</span>
                        </label>
                    )}
                    <Button type="submit" icon="fas fa-filter">Filtrar</Button>
                    {(busca || tipo || naoLidos || dataDe || dataAte || setor) && (
                        <button type="button" onClick={limpar} className="text-xs text-gray-500 hover:text-gray-800 px-2 pb-2">
                            <i className="fas fa-times mr-1" />Limpar
                        </button>
                    )}
                    <span className="text-xs text-gray-400 ml-auto pb-2">{total} item(ns)</span>
                </form>
            </div>

            {/* Lista */}
            <Card padding={false}>
                {lista.length === 0 ? (
                    <div className="py-16 text-center text-gray-400">
                        <i className={`fas ${cfg.icone} text-4xl mb-3 block`} />
                        <p className="text-sm font-medium">{cfg.emptyTitulo}</p>
                        <p className="text-xs mt-1">{cfg.emptyTexto}</p>
                    </div>
                ) : (
                    <div className="divide-y divide-gray-100">
                        {/* Etiquetar em lote (TR 4.19.7) */}
                        {minhas_etiquetas.length > 0 && (
                            <div className="px-4 py-2 bg-gray-50 flex flex-wrap items-center gap-2 text-sm">
                                <label className="flex items-center gap-2 text-gray-600 cursor-pointer">
                                    <input type="checkbox" className="rounded border-gray-300 text-blue-600"
                                        checked={selecionados.length === lista.length}
                                        onChange={(e) => setSelecionados(e.target.checked ? lista.map(chaveItem) : [])} />
                                    {selecionados.length ? `${selecionados.length} selecionado(s)` : 'Selecionar todos'}
                                </label>
                                {selecionados.length > 0 && (
                                    <>
                                        <select value={etiquetaLote} onChange={(e) => setEtiquetaLote(e.target.value)} className="ds-input !h-8 !py-0 w-56">
                                            <option value="">Etiqueta…</option>
                                            {minhas_etiquetas.map(e => <option key={e.id} value={e.id}>{e.nome} ({e.setor})</option>)}
                                        </select>
                                        <Button variant="secondary" disabled={! etiquetaLote} onClick={() => etiquetar('aplicar')}>Aplicar</Button>
                                        <Button variant="secondary" disabled={! etiquetaLote} onClick={() => etiquetar('remover')}>Retirar</Button>
                                    </>
                                )}
                            </div>
                        )}
                        {lista.map(item => (
                            <ItemRow key={item.id} item={item}
                                selecionavel={minhas_etiquetas.length > 0}
                                selecionado={selecionados.includes(chaveItem(item))}
                                onSelecionar={(v) => setSelecionados(s => v ? [...s, chaveItem(item)] : s.filter(x => x !== chaveItem(item)))}
                                onEtiqueta={(id) => aplicar(null, filtros.aba, { etiqueta: id })}
                                onAssinar={() => setAssinarItem({
                                    id: item.assinatura_id,
                                    documento: { nome: `Decisão - ${item.numero}` },
                                    solicitacao: { mensagem: item.assunto },
                                })}
                                onArquivar={() => setArquivarItem({
                                    tipo: item.tipo,
                                    item_id: item.item_id,
                                    documento_id: item.documento_id,
                                    nome: item.numero,
                                })}
                            />
                        ))}
                    </div>
                )}
            </Card>

            {/* Paginacao */}
            {links && (
                <div className="mt-3 flex justify-center gap-1 flex-wrap">
                    {links.map((l, i) => {
                        const label = l.label
                            .replace('pagination.previous', '« Anterior')
                            .replace('pagination.next', 'Proximo »');
                        return (
                            <button key={i} disabled={! l.url}
                                onClick={() => l.url && router.get(l.url, {}, { preserveState: true })}
                                className={`text-xs px-3 py-1.5 rounded-lg ${
                                    l.active ? 'bg-blue-600 text-white' :
                                    l.url ? 'bg-white border border-gray-200 hover:bg-gray-50' :
                                    'bg-gray-50 text-gray-300 cursor-not-allowed'
                                }`}
                                dangerouslySetInnerHTML={{ __html: label }} />
                        );
                    })}
                </div>
            )}

            {/* Modal: Assinar inline */}
            {assinarItem && (
                <AssinarModal assinatura={assinarItem} onClose={() => setAssinarItem(null)} />
            )}

            {/* Modal: Arquivar no GPE Docs */}
            {arquivarItem && (
                <Modal show={!! arquivarItem} onClose={() => setArquivarItem(null)} title={`Arquivar ${arquivarItem.nome} no GPE Docs`}>
                    <form onSubmit={handleArquivar} className="space-y-4">
                        <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                            <i className="fas fa-info-circle mr-1" />
                            O PDF assinado da decisão será movido para a pasta escolhida.
                        </div>
                        {pastasTree.length === 0 ? (
                            <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center">
                                <p className="text-sm text-amber-800">Nenhuma pasta cadastrada nesta UG.</p>
                            </div>
                        ) : (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Pasta de destino <span className="text-red-500">*</span>
                                </label>
                                <select value={arquivarForm.data.pasta_id}
                                    onChange={(e) => arquivarForm.setData('pasta_id', e.target.value)}
                                    className="ds-input">
                                    <option value="">— Selecione —</option>
                                    {pastasTree.map(p => (
                                        <option key={p.id} value={p.id}>
                                            {'— '.repeat(p.nivel)}{p.nome}{p.descricao ? ` — ${p.descricao}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {(() => {
                                    const sel = pastasTree.find(p => String(p.id) === String(arquivarForm.data.pasta_id));
                                    return sel?.descricao ? (
                                        <p className="mt-1 text-[11px] text-gray-500 italic">
                                            <i className="fas fa-info-circle mr-1" />{sel.descricao}
                                        </p>
                                    ) : null;
                                })()}
                            </div>
                        )}
                        <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                            <Button variant="secondary" type="button" onClick={() => setArquivarItem(null)}>Cancelar</Button>
                            <Button type="submit" loading={arquivarForm.processing} icon="fas fa-folder-plus"
                                disabled={! arquivarForm.data.pasta_id || pastasTree.length === 0}>
                                Arquivar
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}
        </AdminLayout>
    );
}

function ItemRow({ item, onAssinar, onArquivar, selecionavel = false, selecionado = false, onSelecionar, onEtiqueta }) {
    const tipo = TIPO_CONFIG[item.tipo] || TIPO_CONFIG.processo;
    const cores = {
        amber:  { bg: 'bg-amber-100',  text: 'text-amber-700',  border: 'border-amber-200' },
        rose:   { bg: 'bg-rose-100',   text: 'text-rose-700',   border: 'border-rose-200' },
        cyan:   { bg: 'bg-cyan-100',   text: 'text-cyan-700',   border: 'border-cyan-200' },
        indigo: { bg: 'bg-indigo-100', text: 'text-indigo-700', border: 'border-indigo-200' },
    }[tipo.cor] || { bg: 'bg-gray-100', text: 'text-gray-600', border: 'border-gray-200' };

    const dataFmt = item.criado_em
        ? new Date(item.criado_em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
        : '-';

    const url = `/${tipo.link}/${item.item_id}`;
    const pdfUrl = item.tipo === 'memorando' ? `/memorandos/${item.item_id}/pdf`
                 : item.tipo === 'oficio'    ? `/oficios/${item.item_id}/pdf`
                 : item.tipo === 'circular'  ? `/circulares/${item.item_id}/pdf`
                 : null;

    const statusBadge = {
        aguardando_assinatura: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Aguardando' },
        concluido:             { bg: 'bg-green-100',  text: 'text-green-700',  label: 'Concluído' },
        cancelado:             { bg: 'bg-red-100',    text: 'text-red-700',    label: 'Cancelado' },
        arquivado:             { bg: 'bg-gray-100',   text: 'text-gray-600',   label: 'Arquivado' },
        em_tramitacao:         { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Em Tramitação' },
    }[item.status] || null;

    return (
        <div className={`px-4 py-3 hover:bg-gray-50 transition-colors ${! item.lido_por_mim ? 'border-l-4 border-blue-500' : ''}`}>
            <div className="flex items-start gap-3">
                {selecionavel && ['processo', 'memorando', 'oficio'].includes(item.tipo) && (
                    <input type="checkbox" checked={selecionado} onChange={(e) => onSelecionar?.(e.target.checked)}
                        className="mt-2.5 rounded border-gray-300 text-blue-600" title="Selecionar para etiquetar" />
                )}
                <div className={`w-9 h-9 ${cores.bg} rounded-lg flex items-center justify-center shrink-0`}>
                    <i className={`fas ${tipo.icone} ${cores.text} text-sm`} />
                </div>
                <Link href={url} className="flex-1 min-w-0 group">
                    <div className="flex items-center gap-2 flex-wrap">
                        {(item.etiquetas || []).map(e => (
                            <span key={e.id} role="button" title={`Etiqueta do setor ${e.setor} — clique para ver a gaveta`}
                                onClick={(ev) => { ev.preventDefault(); ev.stopPropagation(); onEtiqueta?.(e.id); }}
                                style={{ color: e.cor_texto, backgroundColor: e.cor_fundo }}
                                className="text-[10px] px-1.5 py-0.5 rounded-full font-semibold cursor-pointer">
                                {e.nome}
                            </span>
                        ))}
                        <span className={`text-[10px] px-1.5 py-0.5 rounded ${cores.bg} ${cores.text} font-bold uppercase tracking-wide`}>
                            {tipo.label}
                        </span>
                        {item.numero && (
                            <span className="text-[10px] font-mono text-gray-500">{item.numero}</span>
                        )}
                        {! item.lido_por_mim && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-bold uppercase">Novo</span>
                        )}
                        {! item.lido_por_mim && item.lido && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600 font-bold uppercase" title="Alguém do setor já abriu ou recebeu">Lido no setor</span>
                        )}
                        {item.copia && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-sky-100 text-sky-700 font-bold uppercase" title="Cópia para ciência">Cópia</span>
                        )}
                        {/* Prazo (TR 4.13.4, 4.13.5) */}
                        {item.prazo_situacao && (
                            <span className={`text-[9px] px-1.5 py-0.5 rounded-full font-bold uppercase ${SITUACAO_PRAZO[item.prazo_situacao].cor}`}
                                title={`Prazo: ${new Date(item.prazo).toLocaleString('pt-BR')}`}>
                                <i className="fas fa-clock mr-0.5" />{SITUACAO_PRAZO[item.prazo_situacao].rotulo} · {new Date(item.prazo).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}
                            </span>
                        )}
                        {statusBadge && (
                            <span className={`text-[9px] px-1.5 py-0.5 rounded ${statusBadge.bg} ${statusBadge.text} font-bold uppercase`}>
                                {statusBadge.label}
                            </span>
                        )}
                        {item.ja_arquivado_ged && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 font-bold uppercase">
                                <i className="fas fa-folder-open mr-0.5" />no Docs
                            </span>
                        )}
                        {item.confidencial && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 font-bold uppercase">
                                <i className="fas fa-lock mr-0.5" />Confidencial
                            </span>
                        )}
                    </div>
                    <p className={`text-sm mt-0.5 truncate group-hover:text-blue-600 ${item.lido_por_mim ? 'text-gray-700' : 'text-gray-900 font-semibold'}`}>
                        {item.assunto}
                    </p>
                    <p className="text-[11px] text-gray-500 mt-0.5">
                        {item.remetente_nome && <><i className="fas fa-user mr-1" />De {item.remetente_nome}</>}
                        {item.destino_unidade_nome && <span className="ml-2"><i className="fas fa-arrow-right mr-1" />{item.destino_unidade_nome}</span>}
                        {item.destino_usuario_nome && <span className="ml-2"><i className="fas fa-arrow-right mr-1" />{item.destino_usuario_nome}</span>}
                    </p>
                </Link>

                <div className="flex items-center gap-1.5 shrink-0">
                    {/* Assinar (se aguardando assinatura) */}
                    {item.pode_assinar && (
                        <button onClick={onAssinar}
                            className="text-[11px] px-2.5 py-1.5 rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition-colors"
                            title="Assinar decisão agora">
                            <i className="fas fa-pen-nib mr-1" />Assinar
                        </button>
                    )}

                    {/* Arquivar no GPE Docs (se concluido com pdf) */}
                    {item.pode_arquivar_ged && (
                        <button onClick={onArquivar}
                            className="text-[11px] px-2.5 py-1.5 rounded-lg bg-white border border-blue-300 text-blue-700 hover:bg-blue-50 transition-colors"
                            title="Arquivar no GPE Docs">
                            <i className="fas fa-folder-plus" />
                        </button>
                    )}

                    {/* Baixar PDF (memorando/oficio/circular) */}
                    {pdfUrl && (
                        <a href={pdfUrl} target="_blank" rel="noopener noreferrer"
                            className="text-[11px] px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                            title="Baixar PDF">
                            <i className="fas fa-download" />
                        </a>
                    )}

                    {/* Baixar PDF assinado da decisao (processo concluido) */}
                    {item.tipo === 'processo' && item.documento_id && (
                        <a href={`/documentos/${item.documento_id}/download`} target="_blank" rel="noopener noreferrer"
                            className="text-[11px] px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                            title="Baixar decisão">
                            <i className="fas fa-file-pdf" />
                        </a>
                    )}

                    {/* Visualizar (sempre) */}
                    <Link href={url}
                        className="text-[11px] px-2.5 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                        title="Abrir">
                        <i className="fas fa-eye" />
                    </Link>
                </div>

                <div className="text-right shrink-0">
                    <span className="text-[10px] text-gray-400">{dataFmt}</span>
                </div>
            </div>
        </div>
    );
}
