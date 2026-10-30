/**
 * Acessos diários por usuário (TR 4.25.4): entradas no dia, primeira e última, IPs e dispositivos,
 * com filtros de período, setor e usuário, CSV e agendamento.
 */
import { Head } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import DataTable from '../../Components/DataTable';
import { Agendados, Exportar, Filtros, Gerados } from '../../Components/RelatoriosTrilha';

export default function Acessos({ linhas, filtros, limiteDireto, setores, usuarios, gerados, agendados, frequencias, destaque }) {
    const colunas = [
        { key: 'dia', label: 'Data', className: 'whitespace-nowrap' },
        { key: 'usuario', label: 'Usuário', render: (r) => <>{r.usuario}<span className="block text-xs text-gray-500">{r.email}</span></> },
        { key: 'setor', label: 'Setor', render: (r) => r.setor || '—' },
        { key: 'entradas', label: 'Entradas', className: 'text-right' },
        { key: 'primeira', label: 'Primeira / última', render: (r) => `${r.primeira} – ${r.ultima}` },
        { key: 'ips', label: 'IPs', className: 'text-right' },
        { key: 'dispositivos', label: 'Dispositivos' },
    ];
    return (
        <AdminLayout>
            <Head title="Acessos diários" />
            <PageHeader title="Acessos diários" subtitle="Entradas no sistema por usuário e dia">
                <Exportar tipo="acessos" filtros={filtros} total={linhas.total} limite={limiteDireto} />
            </PageHeader>
            <div className="grid grid-cols-1 gap-4 2xl:grid-cols-4">
                <Card className="2xl:col-span-3">
                    <Filtros base="/relatorios/acessos" filtros={filtros} setores={setores} usuarios={usuarios} />
                    <p className="mb-2 text-xs text-gray-500">{linhas.total} linha(s) (usuário × dia) no período.</p>
                    <DataTable columns={colunas} data={linhas.data} pagination={linhas} searchable={false} fillHeight={false} emptyMessage="Nenhum acesso com estes filtros." />
                </Card>
                <div className="space-y-4">
                    <Gerados gerados={gerados} destaque={destaque} />
                    <Agendados tipo="acessos" agendados={agendados} frequencias={frequencias} filtros={filtros} />
                </div>
            </div>
        </AdminLayout>
    );
}
