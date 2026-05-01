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

const VISTA_CONFIG = {
    pessoal: {
        titulo: 'Caixa Pessoal',
        subtitulo: 'Itens enderecados pessoalmente a voce, em estado ativo',
        icone: 'fa-inbox',
        cor: 'blue',
        emptyTitulo: 'Sem itens pendentes pra voce',
        emptyTexto: 'Quando alguem encaminhar algo diretamente a voce, aparece aqui.',
    },
    setor: {
        titulo: 'Caixa Setor',
        subtitulo: 'Itens enderecados ao seu setor (qualquer um pode pegar)',
        icone: 'fa-users',
        cor: 'indigo',
        emptyTitulo: 'Sem itens pendentes no setor',
        emptyTexto: 'Itens encaminhados a sua unidade sem destinatario especifico aparecem aqui.',
    },
    aguardando_assinatura: {
        titulo: 'Aguardando Assinatura',
        subtitulo: 'Voce decidiu, falta assinar digitalmente para tornar oficial',
        icone: 'fa-file-signature',
        cor: 'purple',
        emptyTitulo: 'Sem assinaturas pendentes',
        emptyTexto: 'Decisoes de processo que voce tomar aparecem aqui ate serem assinadas (Lei 14.063/2020).',
    },
    em_tramitacao: {
        titulo: 'Em Tramitacao',
        subtitulo: 'Itens em fluxo onde voce participa, ainda nao finalizados',
        icone: 'fa-share',
        cor: 'orange',
        emptyTitulo: 'Nada em tramitacao',
        emptyTexto: 'Itens que voce originou ou recebeu e ainda estao em andamento aparecem aqui.',
    },
    concluidos: {
        titulo: 'Concluidos',
        subtitulo: 'Itens finalizados — deferidos, indeferidos, arquivados ou cancelados',
        icone: 'fa-check-double',
        cor: 'green',
        emptyTitulo: 'Nenhum item concluido ainda',
        emptyTexto: 'Itens com decisao final ou arquivados aparecem aqui.',
    },
    saida: {
        titulo: 'Saida (Originados)',
        subtitulo: 'Tudo que voce criou — em qualquer estado',
        icone: 'fa-paper-plane',
        cor: 'emerald',
        emptyTitulo: 'Voce ainda nao originou nada',
        emptyTexto: 'Memorandos, oficios e processos que voce criar aparecem aqui.',
    },
    rascunhos: {
        titulo: 'Rascunhos',
        subtitulo: 'Documentos que voce comecou e ainda nao enviou',
        icone: 'fa-pencil-alt',
        cor: 'yellow',
        emptyTitulo: 'Nenhum rascunho',
        emptyTexto: 'Documentos com status rascunho aparecem aqui.',
    },
};

const TIPO_CONFIG = {
    memorando: { label: 'Memorando', icone: 'fa-envelope',     cor: 'amber',  link: 'memorandos' },
    circular:  { label: 'Circular',  icone: 'fa-bullhorn',     cor: 'rose',   link: 'circulares' },
    oficio:    { label: 'Oficio',    icone: 'fa-file-alt',     cor: 'cyan',   link: 'oficios' },
    processo:  { label: 'Processo',  icone: 'fa-folder-open',  cor: 'indigo', link: 'processos' },
};

export default function FlowInbox({ vista, items, filtros = {}, aviso_sem_unidade = false, pastas = [] }) {
    const cfg = VISTA_CONFIG[vista];
    const lista = items?.data || [];
    const links = items?.links || null;
    const total = items?.total ?? lista.length;

    const [busca, setBusca]       = useState(filtros.busca || '');
    const [tipo, setTipo]         = useState(filtros.tipo || '');
    const [naoLidos, setNaoLidos] = useState(!! filtros.nao_lidos);
    const [dataDe, setDataDe]     = useState(filtros.data_de || '');
    const [dataAte, setDataAte]   = useState(filtros.data_ate || '');

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

    const aplicar = (e) => {
        e?.preventDefault();
        router.get(window.location.pathname,
            {
                busca: busca || undefined, tipo: tipo || undefined,
                nao_lidos: naoLidos ? 1 : undefined,
                data_de: dataDe || undefined, data_ate: dataAte || undefined,
            },
            { preserveState: true, replace: true });
    };

    const limpar = () => {
        setBusca(''); setTipo(''); setNaoLidos(false); setDataDe(''); setDataAte('');
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
                        <p className="text-xs font-semibold text-amber-800">Voce nao esta vinculado a nenhuma unidade do organograma</p>
                        <p className="text-[11px] text-amber-700">Peca ao admin para vincular seu usuario a uma unidade. Sem isso a Inbox do Setor fica vazia.</p>
                    </div>
                </div>
            )}

            {/* Filtros */}
            <div className="bg-white rounded-xl border border-gray-200 p-3 mb-3">
                <form onSubmit={aplicar} className="flex flex-wrap items-end gap-2">
                    <div className="relative flex-1 min-w-[260px]">
                        <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Buscar</label>
                        <i className="fas fa-search absolute left-3 top-[60%] -translate-y-1/2 text-gray-400 text-xs" />
                        <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                            placeholder="Numero ou assunto..." className="ds-input pl-9" />
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Tipo</label>
                        <select value={tipo} onChange={(e) => setTipo(e.target.value)} className="ds-input w-44">
                            <option value="">Todos</option>
                            <option value="memorando">Memorandos</option>
                            <option value="oficio">Oficios</option>
                            <option value="processo">Processos</option>
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
                    {(vista === 'pessoal' || vista === 'setor') && (
                        <label className="flex items-center gap-2 cursor-pointer pb-2.5">
                            <input type="checkbox" checked={naoLidos} onChange={(e) => setNaoLidos(e.target.checked)}
                                className="rounded border-gray-300 text-blue-600" />
                            <span className="text-xs text-gray-700">Apenas nao lidos</span>
                        </label>
                    )}
                    <Button type="submit" icon="fas fa-filter">Filtrar</Button>
                    {(busca || tipo || naoLidos || dataDe || dataAte) && (
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
                        {lista.map(item => (
                            <ItemRow key={item.id} item={item}
                                onAssinar={() => setAssinarItem({
                                    id: item.assinatura_id,
                                    documento: { nome: `Decisao - ${item.numero}` },
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
                            O PDF assinado da decisao sera movido para a pasta escolhida.
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

function ItemRow({ item, onAssinar, onArquivar }) {
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
        concluido:             { bg: 'bg-green-100',  text: 'text-green-700',  label: 'Concluido' },
        cancelado:             { bg: 'bg-red-100',    text: 'text-red-700',    label: 'Cancelado' },
        arquivado:             { bg: 'bg-gray-100',   text: 'text-gray-600',   label: 'Arquivado' },
        em_tramitacao:         { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Em Tramitacao' },
    }[item.status] || null;

    return (
        <div className={`px-4 py-3 hover:bg-gray-50 transition-colors ${! item.lido ? 'border-l-4 border-blue-500' : ''}`}>
            <div className="flex items-start gap-3">
                <div className={`w-9 h-9 ${cores.bg} rounded-lg flex items-center justify-center shrink-0`}>
                    <i className={`fas ${tipo.icone} ${cores.text} text-sm`} />
                </div>
                <Link href={url} className="flex-1 min-w-0 group">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className={`text-[10px] px-1.5 py-0.5 rounded ${cores.bg} ${cores.text} font-bold uppercase tracking-wide`}>
                            {tipo.label}
                        </span>
                        {item.numero && (
                            <span className="text-[10px] font-mono text-gray-500">{item.numero}</span>
                        )}
                        {! item.lido && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-bold uppercase">Novo</span>
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
                    <p className={`text-sm mt-0.5 truncate group-hover:text-blue-600 ${item.lido ? 'text-gray-700' : 'text-gray-900 font-semibold'}`}>
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
                            title="Assinar decisao agora">
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
                            title="Baixar decisao">
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
