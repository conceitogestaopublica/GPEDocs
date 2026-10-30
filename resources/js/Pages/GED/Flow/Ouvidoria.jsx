/**
 * Painel da ouvidoria (TR 4.33.25–26) e do e-SIC (TR 4.34): indicadores de prazo legal,
 * distribuição por tipo, canal, identificação, mês, assunto e setor, filtros e a lista.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import StatCard from '../../../Components/StatCard';
import Button from '../../../Components/Button';

const PRAZO = {
    em_dia: ['No prazo', 'bg-emerald-100 text-emerald-800'], vencendo: ['Vence em breve', 'bg-amber-100 text-amber-800'],
    vencido: ['Vencido', 'bg-red-100 text-red-700'], no_prazo: ['Respondida no prazo', 'bg-emerald-100 text-emerald-800'],
    fora_do_prazo: ['Respondida fora do prazo', 'bg-red-100 text-red-700'],
};
const SITUACOES = [['', 'Todas'], ['abertos', 'Em aberto'], ['vencendo', 'Vencendo'], ['vencidos', 'Vencidas'], ['respondidos', 'Respondidas']];
const data = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');
const nomeMes = (m) => { const [a, n] = m.split('-'); return new Date(a, n - 1, 1).toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' }); };

/** Barras horizontais simples: rótulo, barra proporcional e quantidade. */
function Barras({ titulo, itens }) {
    const max = Math.max(1, ...itens.map(i => i.qtd));
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4">
            <h3 className="text-sm font-semibold text-gray-700 mb-3">{titulo}</h3>
            {itens.length === 0 ? <p className="text-xs text-gray-400">Sem dados no período.</p> : (
                <ul className="space-y-2">
                    {itens.map((i, k) => (
                        <li key={k} className="text-xs">
                            <div className="flex justify-between gap-2 text-gray-600"><span className="truncate" title={i.rotulo}>{i.rotulo}</span><span className="font-semibold text-gray-800">{i.qtd}</span></div>
                            <div className="h-1.5 rounded-full bg-gray-100 mt-1"><div className="h-1.5 rounded-full bg-cyan-600" style={{ width: `${(100 * i.qtd) / max}%` }} /></div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Ouvidoria({ modalidade, filtros = {}, indicadores: ind, lista, opcoes }) {
    const esic = modalidade === 'esic';
    const rota = esic ? '/flow/esic' : '/flow/ouvidoria';
    const [f, setF] = useState({ tipo: '', situacao: '', identificacao: '', canal: '', setor: '', de: '', ate: '', busca: '', ...filtros });
    const aplicar = (extra = {}) => router.get(rota, Object.fromEntries(Object.entries({ ...f, ...extra }).filter(([, v]) => v !== '' && v != null)), { preserveScroll: true });
    const campo = (k) => ({ value: f[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });
    const meses = Object.entries(ind.por_mes || {}).map(([m, qtd]) => ({ rotulo: nomeMes(m), qtd }));

    return (
        <AdminLayout>
            <Head title={esic ? 'e-SIC' : 'Ouvidoria'} />
            <PageHeader title={esic ? 'e-SIC — pedidos de acesso à informação' : 'Ouvidoria'}
                subtitle={esic ? 'Pedidos pela Lei 12.527 (LAI): prazo de 20 dias, prorrogável por mais 10' : 'Manifestações pela Lei 13.460: prazo de 30 dias úteis, prorrogável por mais 30'} />

            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
                <StatCard title={esic ? 'Pedidos' : 'Manifestações'} value={ind.total} icon="fas fa-inbox" />
                <StatCard title="Em aberto" value={ind.abertos} icon="fas fa-folder-open" color="purple" />
                <StatCard title="Vencendo (5 dias)" value={ind.vencendo} icon="fas fa-hourglass-half" color="yellow" />
                <StatCard title="Vencidas" value={ind.vencidos} icon="fas fa-triangle-exclamation" color="red" />
                <StatCard title="Respondidas no prazo" value={ind.pct_no_prazo != null ? `${ind.pct_no_prazo}%` : '—'} icon="fas fa-check-double" color="green" />
                <StatCard title={esic ? 'Tempo médio (dias)' : 'Avaliação média'} value={esic ? (ind.dias_medio ?? '—') : (ind.avaliacao?.media != null ? `${ind.avaliacao.media} ★` : '—')} icon={esic ? 'fas fa-stopwatch' : 'fas fa-star'} color="blue" />
            </div>

            <form onSubmit={(e) => { e.preventDefault(); aplicar(); }} className="bg-white rounded-xl border border-gray-200 p-4 mb-4 grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 items-end">
                {! esic && (
                    <label className="text-xs text-gray-600">Tipo<select {...campo('tipo')} className="ds-input mt-1"><option value="">Todos</option>
                        {Object.entries(opcoes.tipos).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                )}
                <label className="text-xs text-gray-600">Situação<select {...campo('situacao')} className="ds-input mt-1">
                    {SITUACOES.map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                {! esic && (
                    <label className="text-xs text-gray-600">Identificação<select {...campo('identificacao')} className="ds-input mt-1"><option value="">Todas</option>
                        {Object.entries(opcoes.identificacoes).map(([v, r]) => <option key={v} value={v}>{r.split(' — ')[0]}</option>)}</select></label>
                )}
                <label className="text-xs text-gray-600">Canal<select {...campo('canal')} className="ds-input mt-1"><option value="">Todos</option>
                    {Object.entries(opcoes.canais).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                <label className="text-xs text-gray-600">Setor<select {...campo('setor')} className="ds-input mt-1"><option value="">Todos</option>
                    {opcoes.setores.map(s => <option key={s.id} value={s.id}>{s.nome}</option>)}</select></label>
                <label className="text-xs text-gray-600">De<input type="date" {...campo('de')} className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Até<input type="date" {...campo('ate')} className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Busca<input {...campo('busca')} placeholder="Número ou assunto" className="ds-input mt-1" /></label>
                <div className="flex gap-2 col-span-2 md:col-span-1">
                    <Button type="submit" icon="fas fa-filter">Filtrar</Button>
                    <Button type="button" variant="secondary" onClick={() => router.get(rota)}>Limpar</Button>
                </div>
            </form>

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                {esic ? <Barras titulo="Por mês" itens={meses} /> : <Barras titulo="Por tipo" itens={ind.por_tipo} />}
                <Barras titulo="Por canal" itens={ind.por_canal} />
                <Barras titulo="Por setor (onde está)" itens={ind.por_setor} />
                {esic ? <Barras titulo="Assuntos mais pedidos" itens={ind.por_assunto} /> : <Barras titulo="Por mês" itens={meses} />}
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Número</th><th className="px-3 py-2">Assunto</th>{! esic && <th className="px-3 py-2">Tipo</th>}
                            <th className="px-3 py-2">{esic ? 'Requerente' : 'Manifestante'}</th><th className="px-3 py-2">Canal</th><th className="px-3 py-2">Aberto em</th><th className="px-3 py-2">Prazo legal</th></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {lista.data.length === 0 && <tr><td colSpan={7} className="px-3 py-6 text-center text-gray-400">Nada encontrado com estes filtros.</td></tr>}
                        {lista.data.map(p => {
                            const [rot, cor] = PRAZO[p.prazo?.situacao] || [];
                            return (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-3 py-2 whitespace-nowrap"><Link href={`/processos/${p.id}`} className="text-cyan-700 font-medium hover:underline">{p.numero}</Link></td>
                                    <td className="px-3 py-2">{p.assunto}</td>
                                    {! esic && <td className="px-3 py-2">{p.manifestacao && <span className="rounded-full bg-violet-100 text-violet-800 px-2 py-0.5 text-xs">{p.manifestacao}</span>}</td>}
                                    <td className="px-3 py-2">{p.identificacao !== 'aberta' && <i className={`fas ${p.identificacao === 'sigilosa' ? 'fa-user-lock' : 'fa-user-secret'} text-amber-600 mr-1`} title={p.identificacao} />}{p.manifestante || '—'}</td>
                                    <td className="px-3 py-2 text-gray-600">{p.canal || '—'}</td>
                                    <td className="px-3 py-2 whitespace-nowrap text-gray-600">{data(p.aberto_em)}</td>
                                    <td className="px-3 py-2 whitespace-nowrap">{p.prazo ? <>{data(p.prazo.vence_em)} {rot && <span className={`ml-1 text-[11px] rounded-full px-2 py-0.5 ${cor}`}>{rot}</span>}</> : '—'}</td>
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
