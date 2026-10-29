/**
 * Rascunhos (TR 4.16.6–4.16.9): os meus e os do meu setor, salvos sozinhos pelo editor.
 * Qualquer servidor do setor continua; quem está editando agora aparece na lista.
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import { useConfirm } from '../../../Components/ConfirmProvider';

const quando = (iso) => new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export default function Rascunhos({ rascunhos = [], filtro = 'todos' }) {
    const confirmar = useConfirm();
    const descartar = async (r) => {
        if (await confirmar({ titulo: 'Descartar o rascunho?', descricao: `"${r.titulo || 'Sem assunto'}" e os anexos guardados somem.`, tom: 'perigo', rotuloConfirmar: 'Descartar' })) {
            router.delete(`/rascunhos/${r.id}`, { preserveScroll: true });
        }
    };

    return (
        <AdminLayout>
            <Head title="Rascunhos" />
            <PageHeader title="Rascunhos" subtitle="Salvos sozinhos enquanto você escreve — os do seu setor podem ser continuados por qualquer um do setor." />
            <div className="flex gap-2 mb-3">
                {[['todos', 'Meus e do setor'], ['meus', 'Só os meus']].map(([v, rot]) => (
                    <Link key={v} href={`/flow/rascunhos${v === 'meus' ? '?de=meus' : ''}`} preserveScroll
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium ${filtro === v ? 'bg-cyan-600 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'}`}>{rot}</Link>
                ))}
            </div>
            <Card>
                {rascunhos.length === 0 ? (
                    <p className="py-10 text-center text-sm text-gray-400"><i className="fas fa-pencil-alt text-2xl block mb-2" />Nenhum rascunho. Eles aparecem aqui quando você começa a escrever um memorando, uma circular ou um ofício.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead><tr className="text-left text-xs text-gray-500 border-b border-gray-200">
                                <th className="py-2 pr-3 font-medium">Tipo</th><th className="pr-3 font-medium">Assunto</th><th className="pr-3 font-medium">Setor</th>
                                <th className="pr-3 font-medium">Começado por</th><th className="pr-3 font-medium">Última alteração</th><th />
                            </tr></thead>
                            <tbody className="divide-y divide-gray-100">
                                {rascunhos.map((r) => (
                                    <tr key={r.id}>
                                        <td className="py-2 pr-3"><span className="text-[11px] font-semibold rounded bg-gray-100 text-gray-700 px-1.5 py-0.5">{r.modulo}</span></td>
                                        <td className="pr-3 text-gray-800">{r.titulo || <span className="text-gray-400 italic">Sem assunto</span>}
                                            {r.em_edicao_por && <span className="block text-[11px] text-amber-700"><i className="fas fa-lock mr-1" />{r.em_edicao_por} está editando agora</span>}</td>
                                        <td className="pr-3 text-gray-600">{r.setor || '—'}</td>
                                        <td className="pr-3 text-gray-600">{r.meu ? 'Você' : r.autor}</td>
                                        <td className="pr-3 text-gray-600 whitespace-nowrap">{quando(r.atualizado_em)}{r.editado_por && <span className="block text-[11px] text-gray-400">por {r.editado_por}</span>}</td>
                                        <td className="text-right whitespace-nowrap">
                                            <Link href={r.url} className="text-cyan-700 font-medium hover:underline mr-3"><i className="fas fa-pen mr-1" />Continuar</Link>
                                            {! r.em_edicao_por && <button type="button" onClick={() => descartar(r)} className="text-red-600 text-xs hover:underline">Descartar</button>}
                                        </td>
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
