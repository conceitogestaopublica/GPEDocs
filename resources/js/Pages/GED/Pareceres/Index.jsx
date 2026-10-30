/**
 * Pareceres (TR 4.39): lista com busca, conclusão e período; emitir (quem é autorizado) e,
 * para quem administra, os setores autorizados a emitir.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';

export const COR_CONCLUSAO = { favoravel: 'bg-emerald-100 text-emerald-800', desfavoravel: 'bg-red-100 text-red-700', parcial: 'bg-amber-100 text-amber-800', informativo: 'bg-gray-100 text-gray-700' };
const data = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');

export default function Index({ pareceres, filtros = {}, conclusoes = {}, pode_emitir, config }) {
    const [f, setF] = useState({ busca: '', conclusao: '', de: '', ate: '', ...filtros });
    const [setores, setSetores] = useState(config?.setores || []);
    const campo = (k) => ({ value: f[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });
    const aplicar = (e) => { e.preventDefault(); router.get('/pareceres', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveScroll: true }); };

    return (
        <AdminLayout>
            <Head title="Pareceres" />
            <PageHeader title="Pareceres" subtitle="Pareceres numerados, com conclusão, anexos e verificação por QR">
                {pode_emitir && <Link href="/pareceres/create"><Button icon="fas fa-plus">Emitir parecer</Button></Link>}
            </PageHeader>

            <form onSubmit={aplicar} className="bg-white rounded-xl border border-gray-200 p-4 mb-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <label className="text-xs text-gray-600 col-span-2 md:col-span-1">Busca<input {...campo('busca')} placeholder="Número, assunto ou ementa" className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Conclusão<select {...campo('conclusao')} className="ds-input mt-1"><option value="">Todas</option>
                    {Object.entries(conclusoes).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                <label className="text-xs text-gray-600">De<input type="date" {...campo('de')} className="ds-input mt-1" /></label>
                <label className="text-xs text-gray-600">Até<input type="date" {...campo('ate')} className="ds-input mt-1" /></label>
                <div className="flex gap-2"><Button type="submit" icon="fas fa-filter">Filtrar</Button>
                    <Button type="button" variant="secondary" onClick={() => router.get('/pareceres')}>Limpar</Button></div>
            </form>

            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto mb-6">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Número</th><th className="px-3 py-2">Assunto</th><th className="px-3 py-2">Conclusão</th><th className="px-3 py-2">Processo</th><th className="px-3 py-2">Parecerista</th><th className="px-3 py-2">Data</th></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {pareceres.data.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-gray-400">Nenhum parecer encontrado.</td></tr>}
                        {pareceres.data.map(p => (
                            <tr key={p.id} className="hover:bg-gray-50">
                                <td className="px-3 py-2 whitespace-nowrap"><Link href={`/pareceres/${p.id}`} className="text-cyan-700 font-medium hover:underline">{p.numero}</Link></td>
                                <td className="px-3 py-2">{p.assunto}</td>
                                <td className="px-3 py-2"><span className={`text-[11px] rounded-full px-2 py-0.5 ${COR_CONCLUSAO[p.conclusao]}`}>{p.conclusao_rotulo}</span></td>
                                <td className="px-3 py-2">{p.processo ? <Link href={`/processos/${p.processo.id}`} className="text-cyan-700 hover:underline">{p.processo.numero}</Link> : '—'}</td>
                                <td className="px-3 py-2">{p.parecerista}</td>
                                <td className="px-3 py-2 whitespace-nowrap text-gray-600">{data(p.emitido_em)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {pareceres.last_page > 1 && (
                    <div className="flex flex-wrap gap-1 p-3 border-t border-gray-100">
                        {pareceres.links.map((l, i) => (
                            <button key={i} type="button" disabled={! l.url} onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true })}
                                className={`px-2.5 py-1 text-xs rounded border ${l.active ? 'bg-cyan-600 text-white border-cyan-600' : 'border-gray-200 text-gray-700 disabled:opacity-40'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>

            {config && (
                <Card title="Setores autorizados a emitir" subtitle="Sem nenhum marcado, qualquer servidor com a permissão de emitir parecer pode emitir">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-1 max-h-64 overflow-y-auto">
                        {config.unidades.map(u => (
                            <label key={u.id} className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={setores.includes(u.id)}
                                    onChange={(e) => setSetores(e.target.checked ? [...setores, u.id] : setores.filter(s => s !== u.id))} /> {u.nome}
                            </label>
                        ))}
                    </div>
                    <div className="mt-3 flex justify-end"><Button icon="fas fa-save" onClick={() => router.post('/pareceres/setores', { setores }, { preserveScroll: true })}>Salvar setores</Button></div>
                </Card>
            )}
        </AdminLayout>
    );
}
