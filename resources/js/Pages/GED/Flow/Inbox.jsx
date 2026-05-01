/**
 * GPE Flow — Inbox unificada (memorandos + circulares + oficios + processos)
 * Usado pelas rotas pessoal, setor, encaminhados, rascunhos e arquivados.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

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

export default function FlowInbox({ vista, items, filtros = {}, aviso_sem_unidade = false }) {
    const cfg = VISTA_CONFIG[vista];
    const lista = items?.data || [];
    const links = items?.links || null;
    const total = items?.total ?? lista.length;

    const [busca, setBusca]       = useState(filtros.busca || '');
    const [tipo, setTipo]         = useState(filtros.tipo || '');
    const [naoLidos, setNaoLidos] = useState(!! filtros.nao_lidos);

    const aplicar = (e) => {
        e?.preventDefault();
        router.get(window.location.pathname,
            { busca: busca || undefined, tipo: tipo || undefined, nao_lidos: naoLidos ? 1 : undefined },
            { preserveState: true, replace: true });
    };

    const limpar = () => {
        setBusca(''); setTipo(''); setNaoLidos(false);
        router.get(window.location.pathname, {}, { preserveState: true, replace: true });
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
                    {(vista === 'pessoal' || vista === 'setor') && (
                        <label className="flex items-center gap-2 cursor-pointer pb-2.5">
                            <input type="checkbox" checked={naoLidos} onChange={(e) => setNaoLidos(e.target.checked)}
                                className="rounded border-gray-300 text-blue-600" />
                            <span className="text-xs text-gray-700">Apenas nao lidos</span>
                        </label>
                    )}
                    <Button type="submit" icon="fas fa-filter">Filtrar</Button>
                    {(busca || tipo || naoLidos) && (
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
                        {lista.map(item => <ItemRow key={item.id} item={item} />)}
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
        </AdminLayout>
    );
}

function ItemRow({ item }) {
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

    return (
        <Link href={url} className={`block px-4 py-3 hover:bg-gray-50 transition-colors ${! item.lido ? 'border-l-4 border-blue-500' : ''}`}>
            <div className="flex items-start gap-3">
                <div className={`w-9 h-9 ${cores.bg} rounded-lg flex items-center justify-center shrink-0`}>
                    <i className={`fas ${tipo.icone} ${cores.text} text-sm`} />
                </div>
                <div className="flex-1 min-w-0">
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
                        {item.confidencial && (
                            <span className="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 font-bold uppercase">
                                <i className="fas fa-lock mr-0.5" />Confidencial
                            </span>
                        )}
                    </div>
                    <p className={`text-sm mt-0.5 truncate ${item.lido ? 'text-gray-700' : 'text-gray-900 font-semibold'}`}>
                        {item.assunto}
                    </p>
                    <p className="text-[11px] text-gray-500 mt-0.5">
                        {item.remetente_nome && <><i className="fas fa-user mr-1" />De {item.remetente_nome}</>}
                        {item.destino_unidade_nome && <span className="ml-2"><i className="fas fa-arrow-right mr-1" />{item.destino_unidade_nome}</span>}
                        {item.destino_usuario_nome && <span className="ml-2"><i className="fas fa-arrow-right mr-1" />{item.destino_usuario_nome}</span>}
                    </p>
                </div>
                <div className="text-right shrink-0">
                    <span className="text-[10px] text-gray-400">{dataFmt}</span>
                    {item.status && (
                        <p className="text-[10px] text-gray-500 mt-0.5">{item.status}</p>
                    )}
                </div>
            </div>
        </Link>
    );
}
