/**
 * Atos oficiais (TR 4.42): lista com filtros por tipo, situação, ano e texto.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';

export const COR_STATUS = { rascunho: 'bg-gray-100 text-gray-700', assinatura: 'bg-amber-100 text-amber-800', publicado: 'bg-emerald-100 text-emerald-800', revogado: 'bg-red-100 text-red-700' };
const data = (s) => (s ? new Date(`${s}T12:00:00`).toLocaleDateString('pt-BR') : '');

export default function Index({ atos, filtros = {}, status = {}, tipos = [], pode_elaborar, pode_tipos }) {
    const [f, setF] = useState({ tipo: '', status: '', ano: '', busca: '', ...filtros });
    const campo = (k) => ({ value: f[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });

    return (
        <AdminLayout>
            <Head title="Atos oficiais" />
            <PageHeader title="Atos oficiais" subtitle="Leis, decretos, portarias, editais, atas e contratos — PDF/A com QR, assinatura e publicação">
                {pode_tipos && <Link href="/atos/tipos"><Button variant="secondary" icon="fas fa-sliders">Tipos de ato</Button></Link>}
                {pode_elaborar && <Link href="/atos/create"><Button icon="fas fa-plus">Novo ato</Button></Link>}
            </PageHeader>
            <form onSubmit={(e) => { e.preventDefault(); router.get('/atos', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveScroll: true }); }}
                className="bg-white rounded-xl border border-gray-200 p-4 mb-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <label className="text-xs text-gray-600">Tipo<select {...campo('tipo')} className="ds-input mt-1"><option value="">Todos</option>{tipos.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}</select></label>
                <label className="text-xs text-gray-600">Situação<select {...campo('status')} className="ds-input mt-1"><option value="">Todas</option>{Object.entries(status).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                <label className="text-xs text-gray-600">Ano<input type="number" {...campo('ano')} className="ds-input mt-1" placeholder="2026" /></label>
                <label className="text-xs text-gray-600">Busca<input {...campo('busca')} className="ds-input mt-1" placeholder="Número, ementa ou texto" /></label>
                <div className="flex gap-2"><Button type="submit" icon="fas fa-filter">Filtrar</Button><Button type="button" variant="secondary" onClick={() => router.get('/atos')}>Limpar</Button></div>
            </form>
            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Ato</th><th className="px-3 py-2">Ementa</th><th className="px-3 py-2">Data</th><th className="px-3 py-2">Situação</th><th className="px-3 py-2">Elaborado por</th></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {atos.data.length === 0 && <tr><td colSpan={5} className="px-3 py-6 text-center text-gray-400">Nenhum ato encontrado.</td></tr>}
                        {atos.data.map(a => (
                            <tr key={a.id} className="hover:bg-gray-50">
                                <td className="px-3 py-2 whitespace-nowrap"><Link href={`/atos/${a.id}`} className="text-cyan-700 font-medium hover:underline">{a.identificacao}</Link></td>
                                <td className="px-3 py-2">{a.ementa}</td>
                                <td className="px-3 py-2 whitespace-nowrap">{data(a.data_ato)}</td>
                                <td className="px-3 py-2"><span className={`text-[11px] rounded-full px-2 py-0.5 ${COR_STATUS[a.status]}`}>{a.status_rotulo}</span></td>
                                <td className="px-3 py-2 text-gray-600">{a.autor}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {atos.last_page > 1 && (
                    <div className="flex flex-wrap gap-1 p-3 border-t border-gray-100">
                        {atos.links.map((l, i) => (
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