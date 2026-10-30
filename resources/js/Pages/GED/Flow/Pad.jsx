/**
 * PADs (TR 4.40): processos disciplinares que o usuário pode ver (sigilo), com infração,
 * comissão, setor atual e o próximo prazo.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

const data = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');
const COR = { Leve: 'bg-gray-100 text-gray-700', 'Média': 'bg-amber-100 text-amber-800', Grave: 'bg-red-100 text-red-700' };

export default function Pad({ lista, busca = '', pode_gerir }) {
    const [q, setQ] = useState(busca);

    return (
        <AdminLayout>
            <Head title="PAD" />
            <PageHeader title="Processos disciplinares (PAD)" subtitle="Sigilosos: aparecem só para a comissão, quem conduz PADs e os setores por onde tramitam">
                {pode_gerir && <Link href="/flow/pad/infracoes"><Button variant="secondary" icon="fas fa-list">Tipos de infração</Button></Link>}
                <Link href="/processos/create"><Button icon="fas fa-plus">Abrir PAD</Button></Link>
            </PageHeader>
            <form onSubmit={(e) => { e.preventDefault(); router.get('/flow/pad', q ? { busca: q } : {}); }} className="mb-4 flex gap-2 max-w-xl">
                <input value={q} onChange={(e) => setQ(e.target.value)} className="ds-input" placeholder="Número ou assunto" />
                <Button type="submit" icon="fas fa-search">Buscar</Button>
            </form>
            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Número</th><th className="px-3 py-2">Assunto</th><th className="px-3 py-2">Infração</th><th className="px-3 py-2">Comissão</th><th className="px-3 py-2">Está em</th><th className="px-3 py-2">Próximo prazo</th><th className="px-3 py-2">Aberto em</th></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {lista.data.length === 0 && <tr><td colSpan={7} className="px-3 py-6 text-center text-gray-400">Nenhum PAD visível para você.</td></tr>}
                        {lista.data.map(p => (
                            <tr key={p.id} className="hover:bg-gray-50">
                                <td className="px-3 py-2 whitespace-nowrap"><Link href={`/processos/${p.id}`} className="text-cyan-700 font-medium hover:underline">{p.numero}</Link></td>
                                <td className="px-3 py-2">{p.assunto}</td>
                                <td className="px-3 py-2">{p.infracao}{p.gravidade && <span className={`ml-1 text-[11px] rounded-full px-2 py-0.5 ${COR[p.gravidade]}`}>{p.gravidade}</span>}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">{p.comissao.length ? p.comissao.join(', ') : '—'}</td>
                                <td className="px-3 py-2 text-xs">{p.setor || '—'}</td>
                                <td className="px-3 py-2 text-xs whitespace-nowrap">{p.proximo_prazo ? `${p.proximo_prazo.titulo} · ${data(p.proximo_prazo.vence_em)}` : '—'}</td>
                                <td className="px-3 py-2 whitespace-nowrap text-gray-600">{data(p.aberto_em)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}