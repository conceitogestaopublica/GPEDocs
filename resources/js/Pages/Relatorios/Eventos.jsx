/**
 * Relatório de eventos (TR 4.25.12–16): acessos, cadastros, configurações e operações com código,
 * data/hora, usuário, setor, IP, SO, dispositivo e navegador; filtros, CSV e agendamento.
 */
import { Head } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import DataTable from '../../Components/DataTable';
import { Agendados, Exportar, Filtros, Gerados } from '../../Components/RelatoriosTrilha';

export default function Eventos({ linhas, filtros, limiteDireto, categorias, codigos, setores, usuarios, gerados, agendados, frequencias, destaque }) {
    const colunas = [
        { key: 'codigo', label: 'Código', render: (r) => <code className="text-xs">{r.codigo}</code> },
        { key: 'quando', label: 'Data e hora', className: 'whitespace-nowrap', render: (r) => <>{r.data}<span className="block text-xs text-gray-500">{r.hora}</span></> },
        { key: 'usuario', label: 'Usuário', render: (r) => <>{r.usuario}{r.setor && <span className="block text-xs text-gray-500">{r.setor}</span>}</> },
        { key: 'descricao', label: 'Evento', render: (r) => <><span className="text-[11px] uppercase tracking-wide text-gray-400">{r.categoria}</span>
            <span className={`block ${r.sucesso ? '' : 'text-red-700'}`}>{r.descricao}</span></> },
        { key: 'ip', label: 'Acesso', render: (r) => <span className="text-xs">{r.ip || '—'}<span className="block text-gray-500">{[r.so, r.dispositivo, r.navegador].filter(Boolean).join(' · ')}</span></span> },
    ];
    return (
        <AdminLayout>
            <Head title="Relatório de eventos" />
            <PageHeader title="Relatório de eventos" subtitle="Acessos, cadastros, configurações e operações dos usuários">
                <Exportar tipo="eventos" filtros={filtros} total={linhas.total} limite={limiteDireto} />
            </PageHeader>
            <div className="grid grid-cols-1 gap-4 2xl:grid-cols-4">
                <Card className="2xl:col-span-3">
                    <Filtros base="/relatorios/eventos" filtros={filtros} setores={setores} usuarios={usuarios} extras={(campo) => (
                        <>
                            <label className="text-xs text-gray-600">Tipo<select {...campo('categoria')} className="ds-input mt-1"><option value="">Todos</option>
                                {Object.entries(categorias).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                            <label className="text-xs text-gray-600">Código<select {...campo('codigo')} className="ds-input mt-1"><option value="">Todos</option>
                                {codigos.map((c) => <option key={c}>{c}</option>)}</select></label>
                            <label className="text-xs text-gray-600">Situação<select {...campo('sucesso')} className="ds-input mt-1"><option value="">Todas</option>
                                <option value="1">Concluídos</option><option value="0">Não concluídos / recusados</option></select></label>
                        </>
                    )} />
                    <p className="mb-2 text-xs text-gray-500">{linhas.total} evento(s) no período.</p>
                    <DataTable columns={colunas} data={linhas.data} pagination={linhas} searchable={false} fillHeight={false} emptyMessage="Nenhum evento com estes filtros." />
                </Card>
                <div className="space-y-4">
                    <Gerados gerados={gerados} destaque={destaque} />
                    <Agendados tipo="eventos" agendados={agendados} frequencias={frequencias} filtros={filtros} />
                </div>
            </div>
        </AdminLayout>
    );
}
