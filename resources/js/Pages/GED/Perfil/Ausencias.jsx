/**
 * Meu perfil › Ausências — férias, afastamentos e licenças (TR 4.7.15).
 * Enquanto o período vale, quem escolhe signatário ou destinatário vê o aviso.
 */
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import Button from '../../../Components/Button';
import { useConfirm } from '../../../Components/ConfirmProvider';

const COR = { vigente: 'bg-amber-100 text-amber-800', futura: 'bg-blue-100 text-blue-700', encerrada: 'bg-gray-100 text-gray-500' };

export default function Ausencias({ tipos, ausencias }) {
    const confirmar = useConfirm();
    const { data, setData, post, processing, errors, reset } = useForm({ tipo: 'ferias', inicio: '', fim: '', observacao: '' });

    return (
        <AdminLayout>
            <Head title="Ausências" />
            <PageHeader title="Meu perfil" subtitle="Férias, afastamentos e licenças" />
            <PerfilAbas />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <form onSubmit={(e) => { e.preventDefault(); post('/perfil/ausencias', { preserveScroll: true, onSuccess: () => reset() }); }}
                    className="ds-card p-5 space-y-3 h-fit">
                    <p className="text-sm font-semibold text-gray-800"><i className="fas fa-umbrella-beach mr-2 text-cyan-600" />Novo período</p>
                    <p className="text-xs text-gray-500">Enquanto valer, quem for escolher você como signatário verá que está ausente.</p>
                    <select value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} className="ds-input">
                        {Object.entries(tipos).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
                    </select>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <label className="block text-xs text-gray-600 mb-1">Início</label>
                            <input type="date" value={data.inicio} onChange={(e) => setData('inicio', e.target.value)} className="ds-input" required />
                        </div>
                        <div>
                            <label className="block text-xs text-gray-600 mb-1">Fim</label>
                            <input type="date" value={data.fim} min={data.inicio || undefined} onChange={(e) => setData('fim', e.target.value)} className="ds-input" required />
                        </div>
                    </div>
                    {(errors.inicio || errors.fim) && <p className="text-xs text-red-600">{errors.inicio || errors.fim}</p>}
                    <input value={data.observacao} onChange={(e) => setData('observacao', e.target.value)} className="ds-input"
                        placeholder="Observação (opcional) — ex.: substituto" maxLength={300} />
                    <Button type="submit" loading={processing} icon="fas fa-plus" className="w-full">Cadastrar período</Button>
                </form>

                <div className="lg:col-span-2 ds-card">
                    {ausencias.length === 0 ? (
                        <p className="text-sm text-gray-400 text-center py-10">Nenhum período cadastrado.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                                    <th className="px-4 py-3">Tipo</th><th className="px-4 py-3">Período</th><th className="px-4 py-3">Situação</th><th />
                                </tr>
                            </thead>
                            <tbody>
                                {ausencias.map((a) => (
                                    <tr key={a.id} className="border-b border-gray-50">
                                        <td className="px-4 py-3 font-medium text-gray-800">{a.tipo}
                                            {a.observacao && <p className="text-xs text-gray-400 font-normal">{a.observacao}</p>}</td>
                                        <td className="px-4 py-3 text-gray-600">{a.inicio} a {a.fim} <span className="text-xs text-gray-400">({a.dias} dia{a.dias > 1 ? 's' : ''})</span></td>
                                        <td className="px-4 py-3"><span className={`text-[11px] px-2 py-0.5 rounded-full font-medium ${COR[a.situacao]}`}>{a.situacao}</span></td>
                                        <td className="px-4 py-3 text-right">
                                            {a.situacao !== 'encerrada' && (
                                                <button type="button" title="Remover" className="text-red-500 hover:text-red-700"
                                                    onClick={async () => {
                                                        if (await confirmar({ titulo: 'Remover este período?', tom: 'perigo', rotuloConfirmar: 'Remover' }))
                                                            router.delete(`/perfil/ausencias/${a.id}`, { preserveScroll: true });
                                                    }}>
                                                    <i className="fas fa-trash" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
