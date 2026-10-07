/**
 * Lixeira — documentos excluídos e pastas inativas, com restauração.
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import { useConfirm } from '../../../Components/ConfirmProvider';

export default function LixeiraIndex({ documentos, pastas = [] }) {
    const confirmar = useConfirm();
    const docs = documentos?.data || [];

    const restaurarDocumento = async (d) => {
        if (await confirmar({ titulo: `Restaurar "${d.nome}"?`, descricao: 'O documento volta para a pasta de origem.', rotuloConfirmar: 'Restaurar' })) {
            router.post(`/lixeira/documentos/${d.id}/restaurar`, {}, { preserveScroll: true });
        }
    };

    const reativarPasta = async (p) => {
        const extra = p.subpastas > 0 ? ` e as ${p.subpastas} subpasta(s) inativadas com ela` : '';
        if (await confirmar({ titulo: `Reativar a pasta "${p.nome}"?`, descricao: `A pasta${extra} voltam a aparecer no repositório.`, rotuloConfirmar: 'Reativar' })) {
            router.post(`/pastas/${p.id}/reativar`, {}, { preserveScroll: true });
        }
    };

    return (
        <AdminLayout>
            <Head title="Lixeira" />
            <PageHeader title="Lixeira" subtitle="Documentos excluídos e pastas inativas — nada aqui foi apagado do armazenamento" />

            <div className="space-y-6">
                <Card title={`Documentos excluídos (${documentos?.total ?? docs.length})`}>
                    {docs.length === 0 ? (
                        <p className="py-6 text-center text-sm text-gray-400">Nenhum documento excluído</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Documento</th>
                                    <th className="px-4 py-3 text-left font-semibold">Tipo</th>
                                    <th className="px-4 py-3 text-left font-semibold">Pasta</th>
                                    <th className="px-4 py-3 text-left font-semibold">Excluído em</th>
                                    <th className="px-4 py-3 text-left font-semibold">Por</th>
                                    <th className="px-4 py-3 w-28" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {docs.map((d) => (
                                    <tr key={d.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-gray-800 font-medium">{d.nome}</td>
                                        <td className="px-4 py-3 text-gray-500">{d.tipo || '-'}</td>
                                        <td className="px-4 py-3 text-gray-500">{d.pasta || 'Raiz'}</td>
                                        <td className="px-4 py-3 text-gray-400 text-xs">{formatarData(d.deleted_at)}</td>
                                        <td className="px-4 py-3 text-gray-500">{d.excluido_por || '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <button onClick={() => restaurarDocumento(d)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                <i className="fas fa-undo mr-1" />Restaurar
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    {documentos?.links && documentos.last_page > 1 && (
                        <div className="pt-3 flex gap-1 justify-end">
                            {documentos.links.map((link, i) => (
                                <Link key={i} href={link.url || '#'} preserveScroll
                                    className={`px-3 py-1.5 text-xs rounded-md ${link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white border text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    )}
                </Card>

                <Card title={`Pastas inativas (${pastas.length})`}>
                    {pastas.length === 0 ? (
                        <p className="py-6 text-center text-sm text-gray-400">Nenhuma pasta inativa</p>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {pastas.map((p) => (
                                <li key={p.id} className="py-3 flex items-center gap-3">
                                    <i className="fas fa-folder text-gray-400" />
                                    <div className="flex-1">
                                        <p className="text-sm text-gray-800">{p.nome}</p>
                                        {p.subpastas > 0 && <p className="text-[11px] text-gray-400">{p.subpastas} subpasta(s) inativa(s) dentro</p>}
                                    </div>
                                    <button onClick={() => reativarPasta(p)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        <i className="fas fa-undo mr-1" />Reativar
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}

function formatarData(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
