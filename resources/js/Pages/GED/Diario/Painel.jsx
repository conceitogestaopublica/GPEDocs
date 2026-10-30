/**
 * Painel do Diário Oficial (TR 4.43.15–4.43.22): contagens, edições pendentes, últimas
 * publicadas e os atos recebidos (pendentes e publicados).
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import StatCard from '../../../Components/StatCard';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

export const TIPO = { ordinaria: 'Ordinária', extraordinaria: 'Extraordinária' };
export const dataBR = (s) => (s ? new Date(`${s.slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR') : '');

export default function Painel({ aba, recebidos, contagem: c, pendentes = [], ultimas = [], estrutura = [] }) {
    const [nova, setNova] = useState(null);

    return (
        <AdminLayout>
            <Head title="Diário Oficial" />
            <PageHeader title="Diário Oficial" subtitle="Atos recebidos, edições pendentes e publicadas">
                <Link href="/diario/configuracao"><Button variant="secondary" icon="fas fa-sitemap">Cadernos e responsáveis</Button></Link>
                <Button icon="fas fa-plus" onClick={() => setNova({ data: new Date().toISOString().slice(0, 10), incluir_pendentes: true })}>Nova edição</Button>
            </PageHeader>

            <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
                <StatCard title="Atos sem edição" value={c.atos_pendentes} icon="fas fa-inbox" color="yellow" />
                <StatCard title="Edições pendentes" value={c.edicoes_pendentes} icon="fas fa-hourglass-half" color="purple" />
                <StatCard title="Edições publicadas" value={c.edicoes_publicadas} icon="fas fa-newspaper" color="green" />
                <StatCard title="Atos publicados" value={c.atos_publicados} icon="fas fa-check-double" color="blue" />
                <StatCard title="Responsáveis ativos" value={c.responsaveis} icon="fas fa-user-tie" />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <Card title="Edições pendentes">
                    {pendentes.length === 0 ? <p className="text-sm text-gray-400">Nenhuma.</p> : (
                        <ul className="divide-y divide-gray-100 text-sm">
                            {pendentes.map(e => (
                                <li key={e.id} className="py-2 flex items-center gap-2">
                                    <Link href={`/diario/edicoes/${e.id}`} className="text-cyan-700 hover:underline">{TIPO[e.tipo]} de {dataBR(e.data)}</Link>
                                    {e.automatica && <span className="text-[10px] rounded-full bg-sky-100 text-sky-800 px-1.5">automática</span>}
                                    <span className="ml-auto text-xs text-gray-500">{e.atos} ato(s)</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
                <Card title="Últimas edições publicadas">
                    {ultimas.length === 0 ? <p className="text-sm text-gray-400">Nenhuma ainda.</p> : (
                        <ul className="divide-y divide-gray-100 text-sm">
                            {ultimas.map(e => (
                                <li key={e.id} className="py-2 flex items-center gap-2">
                                    <Link href={`/diario/edicoes/${e.id}`} className="text-cyan-700 hover:underline">{e.titulo}</Link>
                                    <span className="ml-auto text-xs text-gray-500">{e.atos} ato(s) · {e.paginas} pág. · {e.publicada_por}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <Card title="Atos recebidos">
                <div className="flex gap-2 mb-3">
                    {[['pendentes', 'Pendentes'], ['publicados', 'Publicados']].map(([v, r]) => (
                        <button key={v} type="button" onClick={() => router.get('/diario', { aba: v }, { preserveScroll: true })}
                            className={`px-3 py-1 rounded-lg text-sm border ${aba === v ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-gray-700 border-gray-200'}`}>{r}</button>
                    ))}
                </div>
                <table className="min-w-full text-sm">
                    <thead className="text-xs text-gray-500 text-left"><tr><th className="py-1">Ato</th><th>Caderno / seção</th><th>Edição</th><th>Enviado por</th><th /></tr></thead>
                    <tbody className="divide-y divide-gray-100">
                        {recebidos.data.length === 0 && <tr><td colSpan={5} className="py-4 text-center text-gray-400">Nada aqui.</td></tr>}
                        {recebidos.data.map(m => (
                            <tr key={m.id}>
                                <td className="py-2"><Link href={`/atos/${m.ato.id}`} className="text-cyan-700 hover:underline">{m.ato.identificacao}</Link><span className="block text-xs text-gray-500">{m.ato.ementa}</span></td>
                                <td className="text-xs">{aba === 'pendentes' ? (
                                    <select value={m.estrutura_id || ''} onChange={(e) => router.put(`/diario/materias/${m.id}`, { estrutura_id: e.target.value || null }, { preserveScroll: true })} className="ds-input !h-8 text-xs">
                                        <option value="">Sem seção</option>{estrutura.map(n => <option key={n.id} value={n.id}>{n.caminho}</option>)}</select>) : m.grupo}</td>
                                <td className="text-xs">{m.edicao ? <Link href={`/diario/edicoes/${m.edicao.id}`} className="text-cyan-700 hover:underline">{m.edicao.titulo}</Link> : <span className="text-amber-700">Aguardando edição</span>}</td>
                                <td className="text-xs text-gray-500">{m.enviada_por}</td>
                                <td className="text-right">{aba === 'pendentes' && <button type="button" className="text-xs text-red-600 hover:underline"
                                    onClick={() => router.delete(`/diario/materias/${m.id}`, { data: { do_diario: true }, preserveScroll: true })}>retirar do diário</button>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>

            <Modal show={!! nova} onClose={() => setNova(null)} title="Nova edição">
                {nova && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Data<input type="date" value={nova.data} onChange={(e) => setNova({ ...nova, data: e.target.value })} className="ds-input mt-1" /></label>
                        <p className="text-xs text-gray-500">A primeira edição do dia é ordinária; as demais, extraordinárias.</p>
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={nova.incluir_pendentes} onChange={(e) => setNova({ ...nova, incluir_pendentes: e.target.checked })} /> Incluir os {c.atos_pendentes} ato(s) sem edição</label>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setNova(null)}>Cancelar</Button>
                            <Button onClick={() => router.post('/diario/edicoes', nova)}>Criar</Button></div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}