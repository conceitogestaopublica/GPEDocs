/**
 * Livro de Controle de Oficios — tabela enxuta para auditoria.
 * Numero / Ano / Assunto / Destinatario / Orgao / Data / Status
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export default function ControleOficios({ oficios, anos = [], filtros = {} }) {
    const [ano, setAno]     = useState(filtros.ano || '');
    const [busca, setBusca] = useState(filtros.busca || '');

    const aplicar = (e) => {
        e?.preventDefault();
        router.get('/oficios/controle', { ano, busca }, { preserveState: true });
    };

    const limpar = () => {
        setAno('');
        setBusca('');
        router.get('/oficios/controle');
    };

    const exportarCsv = () => {
        const header = 'Numero;Ano;Assunto;Destinatario;Orgao;Data Envio;Lido em;Status';
        const linhas = (oficios?.data || []).map(o => {
            const dataEnvio = o.enviado_em ? new Date(o.enviado_em).toLocaleString('pt-BR') : '';
            const lidoEm    = o.lido_em ? new Date(o.lido_em).toLocaleString('pt-BR') : '';
            const numAno    = parseAno(o.numero);
            const escape = (s) => `"${String(s ?? '').replace(/"/g, '""')}"`;
            return [
                escape(o.numero),
                escape(numAno),
                escape(o.assunto),
                escape(o.destinatario_nome),
                escape(o.destinatario_orgao),
                escape(dataEnvio),
                escape(lidoEm),
                escape(o.status),
            ].join(';');
        });
        const csv = '﻿' + [header, ...linhas].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `controle-oficios-${ano || 'todos'}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AdminLayout>
            <Head title="Livro de Controle - Oficios" />
            <PageHeader title="Livro de Controle - Oficios" subtitle="Registro de oficios emitidos">
                <Button variant="secondary" icon="fas fa-file-csv" onClick={exportarCsv}>Exportar CSV</Button>
                <Button variant="secondary" icon="fas fa-plus" onClick={() => setShowRegistrar(true)}>Registrar Manual</Button>
                <Button icon="fas fa-paper-plane" href="/oficios/create">Novo Oficio Eletronico</Button>
            </PageHeader>

            <Card className="mb-3">
                <form onSubmit={aplicar} className="flex flex-wrap items-end gap-2">
                    <div className="flex-1 min-w-[260px]">
                        <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Buscar</label>
                        <input type="text" value={busca} onChange={e => setBusca(e.target.value)}
                            placeholder="Numero, assunto, destinatario ou orgao" className="ds-input" />
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Ano</label>
                        <select value={ano} onChange={e => setAno(e.target.value)} className="ds-input w-32">
                            <option value="">Todos</option>
                            {anos.map(a => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <Button type="submit" icon="fas fa-filter">Filtrar</Button>
                    {(ano || busca) && (
                        <button type="button" onClick={limpar} className="text-xs text-gray-500 hover:text-gray-800 px-2 pb-2">
                            <i className="fas fa-times mr-1" />Limpar
                        </button>
                    )}
                </form>
            </Card>

            <Card padding={false}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-4 py-3 text-left w-32">Numero</th>
                                <th className="px-4 py-3 text-left w-20">Ano</th>
                                <th className="px-4 py-3 text-left">Assunto</th>
                                <th className="px-4 py-3 text-left">Destinatario</th>
                                <th className="px-4 py-3 text-left">Orgao</th>
                                <th className="px-4 py-3 text-left w-36">Data Envio</th>
                                <th className="px-4 py-3 text-left w-28">Status</th>
                                <th className="px-4 py-3 text-center w-16">Ver</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(oficios?.data || []).length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-10 text-center text-gray-400">
                                    Nenhum oficio encontrado.
                                </td></tr>
                            )}
                            {(oficios?.data || []).map(o => (
                                <tr key={o.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2.5 font-mono text-xs">{o.numero}</td>
                                    <td className="px-4 py-2.5 text-xs">{parseAno(o.numero)}</td>
                                    <td className="px-4 py-2.5 text-sm text-gray-800 max-w-xs truncate" title={o.assunto}>
                                        {o.assunto}
                                    </td>
                                    <td className="px-4 py-2.5 text-sm text-gray-700">{o.destinatario_nome}</td>
                                    <td className="px-4 py-2.5 text-xs text-gray-600">{o.destinatario_orgao || '-'}</td>
                                    <td className="px-4 py-2.5 text-xs text-gray-600">
                                        {o.enviado_em ? new Date(o.enviado_em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '-'}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <StatusBadge status={o.status} />
                                    </td>
                                    <td className="px-4 py-2.5 text-center">
                                        <Link href={`/oficios/${o.id}`} className="text-blue-600 hover:text-blue-800">
                                            <i className="fas fa-eye" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {oficios?.links && oficios.last_page > 1 && (
                    <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                        <span className="text-xs text-gray-500">
                            Mostrando {oficios.from}-{oficios.to} de {oficios.total}
                        </span>
                        <div className="flex gap-1">
                            {oficios.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'} preserveScroll
                                    className={`px-3 py-1.5 text-xs rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white border text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </Card>
        </AdminLayout>
    );
}

function StatusBadge({ status }) {
    const map = {
        enviado:    'bg-blue-100 text-blue-700',
        entregue:   'bg-cyan-100 text-cyan-700',
        lido:       'bg-emerald-100 text-emerald-700',
        respondido: 'bg-violet-100 text-violet-700',
        arquivado:  'bg-gray-100 text-gray-600',
    };
    const cls = map[status] || 'bg-gray-100 text-gray-600';
    return <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${cls}`}>{status}</span>;
}

function parseAno(numero) {
    // Formato esperado: OF-2026/000001 ou similar
    const m = /(\d{4})/.exec(numero || '');
    return m ? m[1] : '-';
}
