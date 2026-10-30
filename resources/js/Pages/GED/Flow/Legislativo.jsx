/**
 * Protocolo legislativo (TR 4.37): requerimentos e indicações de vereadores, com consulta por
 * tipo, vereador, situação do prazo, período e recebidas/respondidas, e o relatório gerencial
 * (PDF e CSV) com os mesmos filtros.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import StatCard from '../../../Components/StatCard';
import Button from '../../../Components/Button';

const PRAZO = {
    em_dia: ['No prazo', 'bg-emerald-100 text-emerald-800'], vencendo: ['Vence em breve', 'bg-amber-100 text-amber-800'],
    vencido: ['Vencida', 'bg-red-100 text-red-700'], no_prazo: ['Respondida no prazo', 'bg-emerald-100 text-emerald-800'],
    fora_do_prazo: ['Respondida fora do prazo', 'bg-red-100 text-red-700'],
};
const data = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');

function Barras({ titulo, itens }) {
    const max = Math.max(1, ...itens.map(i => i.qtd));
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4">
            <h3 className="text-sm font-semibold text-gray-700 mb-3">{titulo}</h3>
            {itens.length === 0 ? <p className="text-xs text-gray-400">Sem dados.</p> : (
                <ul className="space-y-2">
                    {itens.map((i, k) => (
                        <li key={k} className="text-xs">
                            <div className="flex justify-between gap-2 text-gray-600"><span className="truncate">{i.rotulo}</span><span className="font-semibold text-gray-800">{i.qtd}</span></div>
                            <div className="h-1.5 rounded-full bg-gray-100 mt-1"><div className="h-1.5 rounded-full bg-cyan-600" style={{ width: `${(100 * i.qtd) / max}%` }} /></div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Legislativo({ filtros = {}, indicadores: ind, lista, opcoes }) {
    const [f, setF] = useState({ proposicao: '', vereador: '', situacao: '', prazo: '', de: '', ate: '', busca: '', ...filtros });
    const limpo = (o) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v != null));
    const aplicar = () => router.get('/flow/legislativo', limpo(f), { preserveScroll: true });
    const campo = (k) => ({ value: f[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });
    const relatorio = (formato) => `/flow/legislativo/relatorio?${new URLSearchParams(limpo({ ...filtros, formato }))}`;

    return (
        <AdminLayout>
            <Head title="Protocolo legislativo" />
            <PageHeader title="Protocolo legislativo" subtitle="Requerimentos, indicações e pedidos de informação dos vereadores, com o prazo de resposta">
                <a href={relatorio('pdf')} target="_blank" rel="noreferrer"><Button variant="secondary" icon="fas fa-file-pdf">Relatório PDF</Button></a>
                <a href={relatorio('csv')}><Button variant="secondary" icon="fas fa-file-csv">Planilha CSV</Button></a>
            </PageHeader>

            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
                <StatCard title="Proposições" value={ind.total} icon="fas fa-landmark" />
                <StatCard title="Sem resposta" value={ind.recebidas} icon="fas fa-inbox" color="purple" />
                <StatCard title="Respondidas" value={ind.respondidas} icon="fas fa-reply" color="green" />
                <StatCard title="Vencendo (5 dias)" value={ind.vencendo} icon="fas fa-hourglass-half" color="yellow" />
                <StatCard title="Vencidas" value={ind.vencidas} icon="fas fa-triangle-exclamation" color="red" />
                <StatCard title="Respondidas no prazo" value={ind.pct_no_prazo != null ? `${ind.pct_no_prazo}%` : '—'} icon="fas fa-check-double" color="blue" />
            </div>

            <form onSubmit={(e) => { e.preventDefault(); aplicar(); }} className="bg-white rounded-xl border border-gray-200 p-4 mb-4 grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 items-end">
                <label className="text-xs text-gray-600">Tipo<select {...campo('proposicao')} className="ds-input mt-1"><option value="">Todos</option>
                    {Object.entries(opcoes.proposicoes).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                <label className="text-xs text-gray-600">Vereador<select {...campo('vereador')} className="ds-input mt-1"><option value="">Todos</option>
                    {opcoes.vereadores.map(v => <option key={v.id} value={v.id}>{v.nome}{v.partido ? ` (${v.partido})` : ''}{v.ativo ? '' : ' — inativo'}</option>)}</select></label>
                <label className="text-xs text-gray-600">Recebidas/respondidas<select {...campo('situacao')} className="ds-input mt-1">
                    <option value="">Todas</option><option value="recebidas">Sem resposta</option><option value="respondidas">Respondidas</option></select></label>
                <label className="text-xs text-gray-600">Prazo<select {...campo('prazo')} className="ds-input mt-1"><option value="">Qualquer</option>
                    {Object.entries(PRAZO).map(([v, [r]]) => <option key={v} value={v}>{r}</option>)}</select></label>
                <label className="text-xs text-gray-600">De<input type="date" {...campo('de')} className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Até<input type="date" {...campo('ate')} className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Busca<input {...campo('busca')} placeholder="Protocolo, nº ou assunto" className="ds-input mt-1" /></label>
                <div className="flex gap-2">
                    <Button type="submit" icon="fas fa-filter">Filtrar</Button>
                    <Button type="button" variant="secondary" onClick={() => router.get('/flow/legislativo')}>Limpar</Button>
                </div>
            </form>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                <Barras titulo="Por tipo de proposição" itens={ind.por_tipo} />
                <Barras titulo="Por vereador" itens={ind.por_vereador} />
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Protocolo</th><th className="px-3 py-2">Proposição</th><th className="px-3 py-2">Vereador</th><th className="px-3 py-2">Assunto</th>
                            <th className="px-3 py-2">Recebida</th><th className="px-3 py-2">Prazo</th><th className="px-3 py-2">Respondida</th></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {lista.data.length === 0 && <tr><td colSpan={7} className="px-3 py-6 text-center text-gray-400">Nenhuma proposição com estes filtros.</td></tr>}
                        {lista.data.map(p => {
                            const [rot, cor] = PRAZO[p.prazo?.situacao] || [];
                            return (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-3 py-2 whitespace-nowrap"><Link href={`/processos/${p.id}`} className="text-cyan-700 font-medium hover:underline">{p.numero}</Link></td>
                                    <td className="px-3 py-2 whitespace-nowrap">{p.proposicao}{p.proposicao_numero ? ` nº ${p.proposicao_numero}` : ''}</td>
                                    <td className="px-3 py-2">{p.vereador}{p.partido ? <span className="text-gray-400"> ({p.partido})</span> : ''}</td>
                                    <td className="px-3 py-2">{p.assunto}</td>
                                    <td className="px-3 py-2 whitespace-nowrap text-gray-600">{data(p.recebido_em)}</td>
                                    <td className="px-3 py-2 whitespace-nowrap">{p.prazo ? <>{data(p.prazo.vence_em)} {rot && <span className={`ml-1 text-[11px] rounded-full px-2 py-0.5 ${cor}`}>{rot}</span>}</> : '—'}</td>
                                    <td className="px-3 py-2 whitespace-nowrap text-gray-600">{data(p.respondido_em) || '—'}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                {lista.last_page > 1 && (
                    <div className="flex flex-wrap gap-1 p-3 border-t border-gray-100">
                        {lista.links.map((l, i) => (
                            <button key={i} type="button" disabled={! l.url} onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true })}
                                className={`px-2.5 py-1 text-xs rounded border ${l.active ? 'bg-cyan-600 text-white border-cyan-600' : 'border-gray-200 text-gray-700 disabled:opacity-40'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
