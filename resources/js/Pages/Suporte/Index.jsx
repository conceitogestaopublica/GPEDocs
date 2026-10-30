/**
 * Central de suporte (TR 4.26.2, 4.26.10): para o atendente, a fila de todos os atendimentos por situação e
 * nível, com a disponibilidade dele; para o usuário, o histórico dos atendimentos dele por situação.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';

const COR = { aberto: 'bg-amber-100 text-amber-800', em_atendimento: 'bg-cyan-100 text-cyan-800', resolvido: 'bg-emerald-100 text-emerald-700',
    fechado: 'bg-slate-100 text-slate-600', cancelado: 'bg-red-100 text-red-700' };
const dt = (s) => new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

export default function Index({ atendimentos, contagem, filtros, ehAtendente, statusRotulos, niveis, atendentes, eu }) {
    const [busca, setBusca] = useState(filtros.busca || '');
    const ir = (mudar) => router.get('/suporte', Object.fromEntries(Object.entries({ ...filtros, busca, ...mudar }).filter(([, v]) => v !== '' && v != null)), { preserveScroll: true });
    const total = Object.values(contagem).reduce((s, n) => s + n, 0);

    return (
        <AdminLayout>
            <Head title="Central de suporte" />
            <PageHeader title="Central de suporte" subtitle={ehAtendente ? 'Fila de atendimento e histórico por situação' : 'Seus atendimentos de suporte'}>
                <a href="/suporte/ajuda"><Button size="sm" variant="secondary" icon="fas fa-book-open">Central de ajuda</Button></a>
            </PageHeader>

            {ehAtendente && (
                <Card className="mb-4">
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <label className="flex items-center gap-2 font-medium">
                            <input type="checkbox" checked={!! eu?.disponivel} onChange={(e) => router.post('/suporte/disponibilidade', { disponivel: e.target.checked }, { preserveScroll: true })} />
                            Estou disponível para atender
                        </label>
                        {eu?.nivel && <span className="text-xs text-gray-500">{niveis[eu.nivel]}</span>}
                        <span className="ml-auto text-xs text-gray-500">On-line agora: {atendentes.length ? atendentes.map((a) => a.nome).join(', ') : 'ninguém'}</span>
                    </div>
                    {! eu && <p className="mt-2 text-xs text-amber-700">Você tem permissão de atendente mas não está cadastrado com nível em Configurar suporte → Atendentes.</p>}
                </Card>
            )}

            <div className="mb-3 flex flex-wrap items-center gap-2">
                {ehAtendente && (
                    <div className="inline-flex overflow-hidden rounded-lg border border-gray-300 text-sm">
                        {[['todos', 'Fila (todos)'], ['meus', 'Meus']].map(([k, r]) => (
                            <button key={k} type="button" onClick={() => ir({ escopo: k })} className={`px-3 py-1.5 ${filtros.escopo === k ? 'bg-cyan-700 text-white' : 'bg-white text-gray-600'}`}>{r}</button>
                        ))}
                    </div>
                )}
                <button type="button" onClick={() => ir({ status: null })} className={`rounded-full px-3 py-1 text-xs ${! filtros.status ? 'bg-gray-800 text-white' : 'bg-white border border-gray-300 text-gray-600'}`}>Todos ({total})</button>
                {Object.entries(statusRotulos).map(([k, r]) => (
                    <button key={k} type="button" onClick={() => ir({ status: k })} className={`rounded-full px-3 py-1 text-xs ${filtros.status === k ? 'bg-gray-800 text-white' : `${COR[k]}`}`}>{r} ({contagem[k] || 0})</button>
                ))}
                {ehAtendente && (
                    <select value={filtros.nivel || ''} onChange={(e) => ir({ nivel: e.target.value })} className="ds-input !w-auto">
                        <option value="">Todos os níveis</option>{Object.entries(niveis).map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                    </select>
                )}
                <form onSubmit={(e) => { e.preventDefault(); ir({}); }} className="ml-auto"><input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Número ou título" className="ds-input" /></form>
            </div>

            <Card>
                <table className="w-full text-sm">
                    <thead><tr className="border-b text-left text-xs text-gray-500"><th className="py-2">Número</th><th>Atendimento</th><th>Solicitante</th><th>Atendente</th><th>Nível</th><th>Situação</th><th>Atualizado</th></tr></thead>
                    <tbody>
                        {atendimentos.map((a) => (
                            <tr key={a.id} className="border-b border-gray-100 align-top hover:bg-gray-50">
                                <td className="py-2 whitespace-nowrap"><Link href={`/suporte/atendimentos/${a.id}`} className="font-semibold text-cyan-700 hover:underline">{a.numero}</Link>
                                    {a.nao_lidas > 0 && <span className="ml-1 rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{a.nao_lidas}</span>}</td>
                                <td><span className="text-gray-800">{a.titulo}</span><span className="block text-xs text-gray-500">{a.assunto || 'Sem assunto'}{a.canal === 'email' ? ' · por e-mail' : ''}</span></td>
                                <td className="text-xs">{a.solicitante}</td>
                                <td className="text-xs">{a.atendente || '—'}</td>
                                <td className="text-xs">{a.nivel}</td>
                                <td><span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${COR[a.status]}`}>{a.status_rotulo}</span>
                                    {a.avaliacao_nota && <span className="block text-[11px] text-amber-600">{'★'.repeat(a.avaliacao_nota)}</span>}</td>
                                <td className="whitespace-nowrap text-xs text-gray-500">{dt(a.atualizado_em)}</td>
                            </tr>
                        ))}
                        {! atendimentos.length && <tr><td colSpan={7} className="py-6 text-center text-gray-400">Nenhum atendimento. Para pedir ajuda, abra o chat (canto inferior direito) → aba Suporte.</td></tr>}
                    </tbody>
                </table>
            </Card>
        </AdminLayout>
    );
}
