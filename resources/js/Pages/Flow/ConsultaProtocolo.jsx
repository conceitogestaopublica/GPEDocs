/**
 * Atendimento › Consultar Protocolo — busca por número, CPF/CNPJ ou nome do
 * requerente, nos processos e nas solicitações do Portal do Cidadão.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';

const STATUS_PROCESSO = {
    aberto:                ['Aberto', 'bg-blue-100 text-blue-700'],
    em_tramitacao:         ['Em Tramitação', 'bg-yellow-100 text-yellow-700'],
    aguardando_assinatura: ['Aguardando Assinatura', 'bg-purple-100 text-purple-700'],
    concluido:             ['Concluído', 'bg-green-100 text-green-700'],
    cancelado:             ['Cancelado', 'bg-red-100 text-red-700'],
    arquivado:             ['Arquivado', 'bg-gray-100 text-gray-700'],
};

const COR_SOLICITACAO = {
    aberta:         'bg-blue-100 text-blue-700',
    em_atendimento: 'bg-amber-100 text-amber-700',
    atendida:       'bg-green-100 text-green-700',
    recusada:       'bg-red-100 text-red-700',
    cancelada:      'bg-gray-100 text-gray-600',
};

const data = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');

function Selo({ cor, children }) {
    return (
        <span className={`text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-semibold ${cor}`}>
            {children}
        </span>
    );
}

export default function ConsultaProtocolo({ q, processos, solicitacoes, statusSolicitacao, limite }) {
    const [busca, setBusca] = useState(q || '');
    const buscou = (q || '').length >= 3;

    const buscar = (e) => {
        e.preventDefault();
        router.get('/flow/atendimento/consultar', busca.trim() ? { q: busca.trim() } : {}, { preserveState: true });
    };

    return (
        <AdminLayout>
            <Head title="Consultar Protocolo" />
            <PageHeader
                title="Consultar Protocolo"
                subtitle="Situação de processos e solicitações pelo número do protocolo, CPF/CNPJ ou nome do requerente"
            />

            <form onSubmit={buscar} className="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex gap-3">
                <div className="relative flex-1">
                    <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" aria-hidden="true" />
                    <label htmlFor="consulta-protocolo" className="sr-only">Número do protocolo, CPF/CNPJ ou nome</label>
                    <input
                        id="consulta-protocolo"
                        autoFocus
                        type="text"
                        value={busca}
                        onChange={(e) => setBusca(e.target.value)}
                        placeholder="Ex.: PROT-2026/000123, SOL-2026-00001, 529.982.247-25 ou Maria da Silva"
                        className="w-full pl-9 pr-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                    />
                </div>
                <button type="submit" className="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    Consultar
                </button>
            </form>

            {!buscou && (
                <Card>
                    <p className="text-sm text-gray-500 text-center py-8">
                        Digite ao menos 3 caracteres: número do protocolo, CPF/CNPJ ou nome de quem pediu.
                    </p>
                </Card>
            )}

            {buscou && (
                <div className="space-y-4">
                    <Card padding={false}>
                        <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <h2 className="text-sm font-bold text-gray-800">
                                <i className="fas fa-folder-open text-indigo-500 mr-2" aria-hidden="true" />
                                Processos ({processos.length}{processos.length === limite ? '+' : ''})
                            </h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th className="px-4 py-2 text-left font-semibold">Protocolo / Assunto</th>
                                    <th className="px-4 py-2 text-left font-semibold">Requerente</th>
                                    <th className="px-4 py-2 text-left font-semibold">Onde está</th>
                                    <th className="px-4 py-2 text-center font-semibold">Situação</th>
                                    <th className="px-4 py-2 text-center font-semibold">Aberto em</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {processos.map((p) => {
                                    const [rotulo, cor] = STATUS_PROCESSO[p.status] ?? [p.status, 'bg-gray-100 text-gray-600'];
                                    return (
                                        <tr key={p.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <Link href={`/processos/${p.id}`} className="font-mono text-xs text-blue-700 font-semibold hover:underline">
                                                    {p.protocolo}
                                                </Link>
                                                <p className="text-gray-800">{p.assunto}</p>
                                                {p.tipo && <p className="text-xs text-gray-400">{p.tipo}</p>}
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="text-gray-700">{p.requerente || '—'}</p>
                                                {p.documento && <p className="text-xs text-gray-400">{p.documento}</p>}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-gray-600">{p.setor || '—'}</td>
                                            <td className="px-4 py-3 text-center"><Selo cor={cor}>{rotulo}</Selo></td>
                                            <td className="px-4 py-3 text-center text-xs text-gray-500">{data(p.aberto_em)}</td>
                                        </tr>
                                    );
                                })}
                                {processos.length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-400 text-sm">Nenhum processo encontrado.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </Card>

                    <Card padding={false}>
                        <div className="px-4 py-3 border-b border-gray-100">
                            <h2 className="text-sm font-bold text-gray-800">
                                <i className="fas fa-inbox text-blue-500 mr-2" aria-hidden="true" />
                                Solicitações do Portal ({solicitacoes.length}{solicitacoes.length === limite ? '+' : ''})
                            </h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th className="px-4 py-2 text-left font-semibold">Código / Serviço</th>
                                    <th className="px-4 py-2 text-left font-semibold">Requerente</th>
                                    <th className="px-4 py-2 text-center font-semibold">Situação</th>
                                    <th className="px-4 py-2 text-center font-semibold">Aberta em</th>
                                    <th className="px-4 py-2 text-center font-semibold">Ação</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {solicitacoes.map((s) => (
                                    <tr key={s.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="font-mono text-xs text-gray-500">{s.codigo}</p>
                                            <p className="text-gray-800">{s.servico}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="text-gray-700">{s.requerente || '—'}</p>
                                            {s.documento && <p className="text-xs text-gray-400">{s.documento}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Selo cor={COR_SOLICITACAO[s.status] ?? 'bg-gray-100 text-gray-600'}>
                                                {statusSolicitacao[s.status] ?? s.status}
                                            </Selo>
                                        </td>
                                        <td className="px-4 py-3 text-center text-xs text-gray-500">{data(s.aberta_em)}</td>
                                        <td className="px-4 py-3 text-center space-x-1">
                                            <Link href={`/flow/portal/solicitacoes/${s.id}`}
                                                className="inline-block px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-100">
                                                Abrir
                                            </Link>
                                            {s.processo_id && (
                                                <Link href={`/processos/${s.processo_id}`}
                                                    className="inline-block px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold hover:bg-indigo-100">
                                                    Processo
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {solicitacoes.length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-400 text-sm">Nenhuma solicitação encontrada.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </Card>
                </div>
            )}
        </AdminLayout>
    );
}
