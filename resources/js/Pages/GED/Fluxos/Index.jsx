/**
 * Lista de Fluxos de Trabalho — GED
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import DataTable from '../../../Components/DataTable';

export default function FluxosIndex({ fluxos }) {
    const data = fluxos?.data || fluxos || [];

    const columns = [
        { key: 'nome', label: 'Nome', render: (row) => (
            <Link href={`/fluxos/${row.id}/edit`} className="text-blue-600 hover:underline font-medium">{row.nome}</Link>
        )},
        { key: 'descricao', label: 'Descricao', render: (row) => (
            <span className="text-gray-500 truncate max-w-xs block">{row.descricao || '-'}</span>
        )},
        { key: 'ativo', label: 'Status', render: (row) => (
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${row.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                {row.ativo ? 'Ativo' : 'Inativo'}
            </span>
        )},
        { key: 'instancias_count', label: 'Instancias', render: (row) => (
            <span className="text-gray-500">{row.instancias_count || 0}</span>
        )},
        { key: 'created_at', label: 'Criado em', render: (row) => (
            <span className="text-xs text-gray-400">{row.created_at ? new Date(row.created_at).toLocaleDateString('pt-BR') : '-'}</span>
        )},
    ];

    return (
        <AdminLayout>
            <Head title="Fluxos de Trabalho" />
            <PageHeader title="Fluxos de Trabalho" subtitle="Criar e gerenciar fluxos de aprovacao automatizados">
                <Button icon="fas fa-plus" href="/fluxos/create">Novo Fluxo</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 p-6">
                <DataTable
                    columns={columns}
                    data={data}
                    pagination={fluxos?.links ? fluxos : null}
                    actions={(row) => (
                        <>
                            <Link href={`/fluxos/${row.id}/edit`} className="text-blue-500 hover:text-blue-700 px-1">
                                <i className="fas fa-edit text-xs" />
                            </Link>
                            <button onClick={() => {
                                if (confirm('Excluir este fluxo?')) router.delete(`/fluxos/${row.id}`);
                            }} className="text-red-400 hover:text-red-600 px-1">
                                <i className="fas fa-trash text-xs" />
                            </button>
                        </>
                    )}
                    emptyMessage="Nenhum fluxo de trabalho criado"
                />
            </div>
        </AdminLayout>
    );
}
