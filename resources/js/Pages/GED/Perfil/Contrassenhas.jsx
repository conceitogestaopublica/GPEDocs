/**
 * Meu perfil › Contrassenhas (TR 4.18) — gerar senha de uso limitado para um
 * servidor emitir ofício, memorando ou circular em seu nome; acompanhar,
 * cancelar e ver o relatório do que foi emitido. Também lista as que você recebeu.
 */
import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import Button from '../../../Components/Button';
import { useConfirm } from '../../../Components/ConfirmProvider';

const COR = { ativa: 'bg-emerald-100 text-emerald-700', esgotada: 'bg-gray-100 text-gray-600', vencida: 'bg-gray-100 text-gray-600', cancelada: 'bg-red-100 text-red-700' };
const Selo = ({ s }) => <span className={`text-[11px] px-2 py-0.5 rounded-full font-medium ${COR[s]}`}>{s}</span>;

export default function Contrassenhas({ minhas, recebidas, relatorio, servidores, nova }) {
    const confirmar = useConfirm();
    const { data, setData, post, processing, errors, reset } = useForm({ digitador_id: '', usos_max: 5, dias: 30, observacao: '' });

    return (
        <AdminLayout>
            <Head title="Contrassenhas" />
            <PageHeader title="Meu perfil" subtitle="Autorize um servidor a emitir ofícios, memorandos e circulares em seu nome" />
            <PerfilAbas />

            {nova && (
                <div className="ds-card p-5 mb-4 border-amber-300 bg-amber-50">
                    <p className="text-sm font-semibold text-amber-900"><i className="fas fa-key mr-2" />Contrassenha para {nova.digitador}</p>
                    <p className="text-3xl font-mono font-bold tracking-[0.3em] text-amber-900 my-3">{nova.codigo}</p>
                    <p className="text-xs text-amber-800">Passe este código a {nova.digitador}. Ele <b>não será mostrado de novo</b> — se perder, cancele e gere outro.</p>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <form onSubmit={(e) => { e.preventDefault(); post('/perfil/contrassenhas', { preserveScroll: true, onSuccess: () => reset() }); }}
                    className="ds-card p-5 space-y-3 h-fit">
                    <p className="text-sm font-semibold text-gray-800"><i className="fas fa-plus-circle mr-2 text-cyan-600" />Gerar contrassenha</p>
                    <div>
                        <label className="block text-xs text-gray-600 mb-1">Servidor que vai digitar</label>
                        <select value={data.digitador_id} onChange={(e) => setData('digitador_id', e.target.value)} className="ds-input" required>
                            <option value="">Escolha…</option>
                            {servidores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                        {errors.digitador_id && <p className="text-xs text-red-600 mt-1">{errors.digitador_id}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <label className="block text-xs text-gray-600 mb-1">Usos (documentos)</label>
                            <input type="number" min={1} max={100} value={data.usos_max} onChange={(e) => setData('usos_max', e.target.value)} className="ds-input" />
                        </div>
                        <div>
                            <label className="block text-xs text-gray-600 mb-1">Validade (dias)</label>
                            <input type="number" min={1} max={90} value={data.dias} onChange={(e) => setData('dias', e.target.value)} className="ds-input" />
                        </div>
                    </div>
                    {(errors.usos_max || errors.dias) && <p className="text-xs text-red-600">{errors.usos_max || errors.dias}</p>}
                    <input value={data.observacao} onChange={(e) => setData('observacao', e.target.value)} className="ds-input"
                        placeholder="Observação (opcional) — ex.: ofícios da licitação 12/2026" maxLength={300} />
                    <Button type="submit" loading={processing} icon="fas fa-key" className="w-full">Gerar</Button>
                </form>

                <div className="lg:col-span-2 space-y-4">
                    <div className="ds-card overflow-x-auto">
                        <p className="text-sm font-semibold text-gray-800 px-5 pt-4 pb-2">Contrassenhas que gerei</p>
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                                <th className="px-5 py-2">Para</th><th className="px-3 py-2">Código</th><th className="px-3 py-2">Usos</th>
                                <th className="px-3 py-2">Válida até</th><th className="px-3 py-2">Situação</th><th />
                            </tr></thead>
                            <tbody>
                                {minhas.map((c) => (
                                    <tr key={c.id} className="border-b border-gray-50">
                                        <td className="px-5 py-2.5 text-gray-800">{c.digitador}{c.observacao && <span className="block text-[11px] text-gray-400">{c.observacao}</span>}</td>
                                        <td className="px-3 py-2.5 font-mono text-gray-500">…{c.final}</td>
                                        <td className="px-3 py-2.5 text-gray-600">{c.usos} de {c.usos_max}</td>
                                        <td className="px-3 py-2.5 text-gray-600">{c.valida_ate}</td>
                                        <td className="px-3 py-2.5"><Selo s={c.situacao} /></td>
                                        <td className="px-3 py-2.5 text-right">
                                            {c.situacao === 'ativa' && (
                                                <button type="button" className="text-xs text-red-600 hover:underline" onClick={async () => {
                                                    if (await confirmar({ titulo: `Cancelar a contrassenha de ${c.digitador}?`, tom: 'perigo', rotuloConfirmar: 'Cancelar contrassenha' }))
                                                        router.post(`/perfil/contrassenhas/${c.id}/cancelar`, {}, { preserveScroll: true });
                                                }}>Cancelar</button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {minhas.length === 0 && <tr><td colSpan={6} className="px-5 py-6 text-center text-gray-400">Nenhuma ainda.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    <div className="ds-card overflow-x-auto">
                        <p className="text-sm font-semibold text-gray-800 px-5 pt-4 pb-2">Documentos emitidos em meu nome</p>
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                                <th className="px-5 py-2">Quando</th><th className="px-3 py-2">Documento</th><th className="px-3 py-2">Digitado por</th>
                            </tr></thead>
                            <tbody>
                                {relatorio.map((d, i) => (
                                    <tr key={i} className="border-b border-gray-50">
                                        <td className="px-5 py-2.5 text-gray-500 whitespace-nowrap">{d.quando}</td>
                                        <td className="px-3 py-2.5"><Link href={d.link} className="text-cyan-700 hover:underline">{d.tipo} {d.numero}</Link>
                                            <span className="block text-xs text-gray-500">{d.assunto}</span></td>
                                        <td className="px-3 py-2.5 text-gray-700">{d.digitador}</td>
                                    </tr>
                                ))}
                                {relatorio.length === 0 && <tr><td colSpan={3} className="px-5 py-6 text-center text-gray-400">Nenhum documento emitido em seu nome.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    {recebidas.length > 0 && (
                        <div className="ds-card overflow-x-auto">
                            <p className="text-sm font-semibold text-gray-800 px-5 pt-4 pb-2">Contrassenhas que recebi</p>
                            <table className="w-full text-sm">
                                <tbody>
                                    {recebidas.map((c) => (
                                        <tr key={c.id} className="border-b border-gray-50">
                                            <td className="px-5 py-2.5 text-gray-800">De {c.autorizador}</td>
                                            <td className="px-3 py-2.5 font-mono text-gray-500">…{c.final}</td>
                                            <td className="px-3 py-2.5 text-gray-600">{c.usos} de {c.usos_max} usos</td>
                                            <td className="px-3 py-2.5 text-gray-600">até {c.valida_ate}</td>
                                            <td className="px-3 py-2.5"><Selo s={c.situacao} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
