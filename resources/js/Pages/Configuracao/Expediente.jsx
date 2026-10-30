/**
 * Configurações › Expediente e feriados (TR 4.13.9–4.13.11): dias úteis, horário de
 * funcionamento, feriados e pontos facultativos. Base do prazo em dias úteis.
 */
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';
import { useConfirm } from '../../Components/ConfirmProvider';

const DIAS = [[1, 'Seg'], [2, 'Ter'], [3, 'Qua'], [4, 'Qui'], [5, 'Sex'], [6, 'Sáb'], [7, 'Dom']];
const TIPO = { feriado: 'Feriado', ponto_facultativo: 'Ponto facultativo' };
const dataBR = (iso) => { const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}`; };
const diaSemana = (iso) => new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR', { weekday: 'short' });

export default function Expediente({ config, ano, feriados = [] }) {
    const confirmar = useConfirm();
    const exp = useForm({ dias_semana: config.dias_semana, hora_inicio: config.hora_inicio, hora_fim: config.hora_fim });
    const novo = useForm({ data: '', nome: '', tipo: 'feriado' });

    const alternarDia = (d) => exp.setData('dias_semana',
        exp.data.dias_semana.includes(d) ? exp.data.dias_semana.filter(x => x !== d) : [...exp.data.dias_semana, d].sort());

    return (
        <AdminLayout>
            <Head title="Expediente e feriados" />
            <PageHeader title="Expediente e feriados" subtitle="Dias úteis, horário de funcionamento, feriados e pontos facultativos — usados no prazo em dias úteis dos assuntos" />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card title="Expediente">
                    <form onSubmit={(e) => { e.preventDefault(); exp.put('/configuracoes/expediente', { preserveScroll: true }); }} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Dias úteis</label>
                            <div className="flex flex-wrap gap-1.5">
                                {DIAS.map(([d, r]) => (
                                    <button key={d} type="button" onClick={() => alternarDia(d)}
                                        className={`px-3 py-1.5 rounded-lg text-sm border ${exp.data.dias_semana.includes(d) ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-gray-600 border-gray-200'}`}>
                                        {r}
                                    </button>
                                ))}
                            </div>
                            {exp.errors.dias_semana && <p className="mt-1 text-xs text-red-600">{exp.errors.dias_semana}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Abre às</label>
                                <input type="time" value={exp.data.hora_inicio} onChange={(e) => exp.setData('hora_inicio', e.target.value)} className="ds-input" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Fecha às</label>
                                <input type="time" value={exp.data.hora_fim} onChange={(e) => exp.setData('hora_fim', e.target.value)} className="ds-input" />
                                {exp.errors.hora_fim && <p className="mt-1 text-xs text-red-600">{exp.errors.hora_fim}</p>}
                            </div>
                        </div>
                        <p className="text-xs text-gray-500">Prazo em dias úteis vence no fechamento do último dia útil. O que chega depois do fechamento conta a partir do dia útil seguinte.</p>
                        <Button type="submit" variant="primary" loading={exp.processing}>Salvar expediente</Button>
                    </form>
                </Card>

                <div className="lg:col-span-2">
                    <Card title={`Feriados e pontos facultativos — ${ano}`}>
                        <div className="flex flex-wrap items-center gap-2 mb-4">
                            <Button variant="secondary" icon="fas fa-chevron-left" onClick={() => router.get('/configuracoes/expediente', { ano: ano - 1 }, { preserveScroll: true })}>{ano - 1}</Button>
                            <Button variant="secondary" onClick={() => router.get('/configuracoes/expediente', { ano: ano + 1 }, { preserveScroll: true })}>{ano + 1} <i className="fas fa-chevron-right ml-1" /></Button>
                            <Button variant="secondary" icon="fas fa-flag" onClick={() => router.post('/configuracoes/expediente/feriados/nacionais', { ano }, { preserveScroll: true })}>
                                Incluir feriados nacionais de {ano}
                            </Button>
                        </div>

                        <form onSubmit={(e) => { e.preventDefault(); novo.post('/configuracoes/expediente/feriados', { preserveScroll: true, onSuccess: () => novo.reset() }); }}
                            className="grid grid-cols-1 md:grid-cols-12 gap-2 items-end mb-4">
                            <div className="md:col-span-3">
                                <label className="block text-xs font-medium text-gray-600 mb-1">Data</label>
                                <input type="date" value={novo.data.data} onChange={(e) => novo.setData('data', e.target.value)} className="ds-input" />
                            </div>
                            <div className="md:col-span-4">
                                <label className="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                                <input value={novo.data.nome} onChange={(e) => novo.setData('nome', e.target.value)} className="ds-input" placeholder="Ex.: Aniversário do município" maxLength={150} />
                            </div>
                            <div className="md:col-span-3">
                                <label className="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                                <select value={novo.data.tipo} onChange={(e) => novo.setData('tipo', e.target.value)} className="ds-input">
                                    <option value="feriado">Feriado</option>
                                    <option value="ponto_facultativo">Ponto facultativo</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <Button type="submit" variant="primary" icon="fas fa-plus" loading={novo.processing} disabled={! novo.data.data || ! novo.data.nome}>Incluir</Button>
                            </div>
                            {(novo.errors.data || novo.errors.nome) && <p className="md:col-span-12 text-xs text-red-600">{novo.errors.data || novo.errors.nome}</p>}
                        </form>

                        {feriados.length === 0 ? (
                            <p className="py-6 text-center text-sm text-gray-400">Nenhuma data cadastrada em {ano}.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <tbody className="divide-y divide-gray-100">
                                    {feriados.map(f => (
                                        <tr key={f.id}>
                                            <td className="py-2 pr-3 whitespace-nowrap text-gray-700">{dataBR(f.data)} <span className="text-xs text-gray-400">{diaSemana(f.data)}</span></td>
                                            <td className="py-2 pr-3 text-gray-800">{f.nome}</td>
                                            <td className="py-2 pr-3">
                                                <span className={`text-[11px] rounded-full px-2 py-0.5 ${f.tipo === 'feriado' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'}`}>{TIPO[f.tipo]}</span>
                                            </td>
                                            <td className="py-2 text-right">
                                                <button type="button" className="text-xs text-red-600 hover:underline"
                                                    onClick={async () => (await confirmar({ titulo: `Remover ${f.nome}?`, tom: 'atencao', rotuloConfirmar: 'Remover' }))
                                                        && router.delete(`/configuracoes/expediente/feriados/${f.id}`, { preserveScroll: true })}>
                                                    Remover
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
