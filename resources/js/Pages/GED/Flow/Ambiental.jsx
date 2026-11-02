/**
 * Licenças ambientais: requerimentos em análise, licenças emitidas com validade e condicionantes pendentes,
 * vencendo (prazo da renovação), indeferidas, vencidas e renovadas.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';

const dia = (s) => (s ? new Date(`${String(s).slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR') : '—');
const COR = { em_analise: 'bg-amber-100 text-amber-800', emitida: 'bg-emerald-100 text-emerald-800', indeferida: 'bg-red-100 text-red-700',
    vencida: 'bg-gray-200 text-gray-700', renovada: 'bg-sky-100 text-sky-800' };

export default function Ambiental({ licencas = [], contagem = {}, vencendo = 0, filtros = {}, statusRotulos = {} }) {
    const [busca, setBusca] = useState(filtros.busca || '');
    const ir = (extra) => router.get('/flow/ambiental', { busca: busca || undefined, ...extra }, { preserveState: true, replace: true });

    return (
        <AdminLayout>
            <Head title="Licenças ambientais" />
            <PageHeader title="Licenças ambientais" subtitle="Requerimentos, licenças emitidas, condicionantes e vencimentos">
                <Button variant="secondary" icon="fas fa-sliders" href="/flow/ambiental/configuracao">Configurar</Button>
            </PageHeader>

            <div className="grid grid-cols-2 gap-3 md:grid-cols-6 mb-4">
                <button type="button" onClick={() => ir({})} className={`rounded-xl border p-3 text-left ${! filtros.status && ! filtros.vencendo ? 'border-cyan-600 bg-cyan-50' : 'border-gray-200 bg-white'}`}>
                    <p className="text-2xl font-semibold">{Object.values(contagem).reduce((a, b) => a + Number(b), 0)}</p><p className="text-xs text-gray-600">Todas</p>
                </button>
                {Object.entries(statusRotulos).map(([k, r]) => (
                    <button key={k} type="button" onClick={() => ir({ status: k })} className={`rounded-xl border p-3 text-left ${filtros.status === k ? 'border-cyan-600 bg-cyan-50' : 'border-gray-200 bg-white'}`}>
                        <p className="text-2xl font-semibold">{contagem[k] || 0}</p><p className="text-xs text-gray-600">{r}</p>
                    </button>
                ))}
            </div>
            {vencendo > 0 && (
                <button type="button" onClick={() => ir({ vencendo: 1 })} className="mb-4 w-full rounded-xl border border-amber-300 bg-amber-50 p-3 text-left text-sm text-amber-900">
                    <i className="fas fa-hourglass-half mr-2" /><b>{vencendo}</b> licença(s) vencendo dentro do prazo de renovação — ver
                </button>
            )}

            <Card padding={false}>
                <form onSubmit={(e) => { e.preventDefault(); ir({ status: filtros.status || undefined }); }} className="flex gap-2 p-3 border-b border-gray-100">
                    <input value={busca} onChange={(e) => setBusca(e.target.value)} className="ds-input flex-1" placeholder="Número da licença, processo, empreendedor ou empreendimento" />
                    <Button type="submit" icon="fas fa-search">Buscar</Button>
                </form>
                {licencas.length === 0 ? <p className="p-6 text-center text-sm text-gray-400">Nenhuma licença.</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-xs text-gray-500">
                                <tr><th className="px-3 py-2 text-left">Licença / processo</th><th className="px-3 py-2 text-left">Empreendimento</th><th className="px-3 py-2 text-left">Atividade</th>
                                    <th className="px-3 py-2 text-left">Classe</th><th className="px-3 py-2 text-left">Situação</th><th className="px-3 py-2 text-left">Validade</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {licencas.map(l => (
                                    <tr key={l.id}>
                                        <td className="px-3 py-2">
                                            <Link href={`/processos/${l.processo_id}`} className="font-medium text-cyan-700 hover:underline">{l.numero || l.numero_protocolo}</Link>
                                            <span className="block text-xs text-gray-500">{l.modalidade_rotulo}{l.numero ? ` · ${l.numero_protocolo}` : ''}</span>
                                        </td>
                                        <td className="px-3 py-2">{l.empreendimento_nome || '—'}<span className="block text-xs text-gray-500">{l.empreendedor_nome}</span></td>
                                        <td className="px-3 py-2 text-xs"><span className="font-mono">{l.atividade_codigo}</span> {l.atividade}</td>
                                        <td className="px-3 py-2">{l.classe}</td>
                                        <td className="px-3 py-2">
                                            <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${COR[l.status]}`}>{l.status_rotulo}</span>
                                            {l.condicionantes_pendentes > 0 && <span className="block text-[11px] text-amber-700 mt-0.5">{l.condicionantes_pendentes} condicionante(s) pendente(s)</span>}
                                        </td>
                                        <td className={`px-3 py-2 text-xs ${l.vencendo ? 'font-semibold text-amber-700' : ''}`}>{l.valida_ate ? dia(l.valida_ate) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </AdminLayout>
    );
}
