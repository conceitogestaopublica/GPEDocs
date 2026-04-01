/**
 * Meus Documentos — GED
 *
 * Lista de documentos do usuario com filtros e acoes.
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import DataTable from '../../../Components/DataTable';

export default function DocumentosIndex({ documentos, filtros }) {
    const data = documentos?.data || documentos || [];

    const columns = [
        { key: 'nome', label: 'Documento', render: (row) => (
            <Link href={`/documentos/${row.id}`} className="flex items-center gap-3 text-gray-700 hover:text-blue-600">
                <i className={`${getFileIcon(row.mime_type)} text-lg`} />
                <div>
                    <p className="text-sm font-medium">{row.nome}</p>
                    <p className="text-[10px] text-gray-400">{row.tipo_nome || 'Sem tipo'}</p>
                </div>
            </Link>
        )},
        { key: 'status', label: 'Status', render: (row) => {
            const map = {
                publicado: 'bg-green-100 text-green-700',
                rascunho: 'bg-yellow-100 text-yellow-700',
                arquivado: 'bg-gray-100 text-gray-600',
            };
            return <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${map[row.status] || 'bg-gray-100 text-gray-500'}`}>{row.status}</span>;
        }},
        { key: 'versao_atual', label: 'Versao', render: (row) => <span className="text-xs text-gray-500">v{row.versao_atual}</span> },
        { key: 'tamanho', label: 'Tamanho', render: (row) => <span className="text-xs text-gray-500">{formatBytes(row.tamanho)}</span> },
        { key: 'updated_at', label: 'Modificado', render: (row) => (
            <span className="text-xs text-gray-400">{row.updated_at ? new Date(row.updated_at).toLocaleDateString('pt-BR') : '-'}</span>
        )},
    ];

    return (
        <AdminLayout>
            <Head title="Meus Documentos" />
            <PageHeader title="Meus Documentos" subtitle="Documentos criados ou compartilhados com voce">
                <Button icon="fas fa-upload" href="/capturar">Novo Documento</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 p-6">
                <DataTable
                    columns={columns}
                    data={data}
                    pagination={documentos?.links ? documentos : null}
                    onSearch={(val) => router.get('/documentos', { search: val }, { preserveState: true })}
                    actions={(row) => (
                        <>
                            <a href={`/documentos/${row.id}/download`} className="text-gray-400 hover:text-blue-600 px-1" title="Download">
                                <i className="fas fa-download text-xs" />
                            </a>
                            <button onClick={() => {
                                if (confirm('Excluir este documento?')) router.delete(`/documentos/${row.id}`);
                            }} className="text-gray-400 hover:text-red-600 px-1" title="Excluir">
                                <i className="fas fa-trash text-xs" />
                            </button>
                        </>
                    )}
                    emptyMessage="Nenhum documento encontrado. Faca seu primeiro upload!"
                />
            </div>
        </AdminLayout>
    );
}

function getFileIcon(mime) {
    if (!mime) return 'fas fa-file text-gray-400';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-red-400';
    if (mime.includes('image')) return 'fas fa-file-image text-purple-400';
    if (mime.includes('word')) return 'fas fa-file-word text-blue-400';
    if (mime.includes('sheet') || mime.includes('excel')) return 'fas fa-file-excel text-green-400';
    return 'fas fa-file text-gray-400';
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
