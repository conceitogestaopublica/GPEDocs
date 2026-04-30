/**
 * Configuracoes — Usuarios (lista)
 *
 * Botao "Novo" navega para /configuracoes/usuarios/create.
 * Filtro de busca e tipo (interno/externo) via querystring server-side.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Button from '../../../../Components/Button';
import DataTable from '../../../../Components/DataTable';

export default function Usuarios({ usuarios, filtros = {} }) {
    const data = usuarios?.data || usuarios || [];
    const [busca, setBusca] = useState(filtros.busca || '');
    const [tipo, setTipo]   = useState(filtros.tipo || '');

    const aplicarFiltros = (novoBusca = busca, novoTipo = tipo) => {
        router.get('/configuracoes/usuarios',
            { busca: novoBusca || undefined, tipo: novoTipo || undefined },
            { preserveState: true, replace: true });
    };

    const onBuscaSubmit = (e) => {
        e.preventDefault();
        aplicarFiltros();
    };

    const limparFiltros = () => {
        setBusca('');
        setTipo('');
        router.get('/configuracoes/usuarios', {}, { preserveState: true, replace: true });
    };

    const columns = [
        { key: 'name', label: 'Usuario', render: (row) => (
            <div className="flex items-center gap-3">
                <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                    row.tipo === 'externo'
                        ? 'bg-gradient-to-br from-amber-500 to-orange-600'
                        : 'bg-gradient-to-br from-blue-500 to-indigo-600'
                }`}>
                    <span className="text-white text-xs font-bold">{(row.name || 'U').substring(0, 2).toUpperCase()}</span>
                </div>
                <div>
                    <p className="text-sm font-medium text-gray-700">{row.name}</p>
                    <p className="text-xs text-gray-400">{row.email}{row.cpf && ' · ' + mascararCpf(row.cpf)}</p>
                </div>
            </div>
        )},
        { key: 'tipo', label: 'Tipo', render: (row) => (
            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${
                row.tipo === 'externo'
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-blue-100 text-blue-700'
            }`}>
                {row.tipo === 'externo' ? 'Externo' : 'Interno'}
            </span>
        )},
        { key: 'unidade', label: 'Vinculo', render: (row) => (
            <div className="text-xs text-gray-600">
                {row.ug ? (
                    <>
                        <p className="font-medium">{row.ug.nome}</p>
                        {row.unidade && <p className="text-[10px] text-gray-400">{row.unidade.nome}</p>}
                    </>
                ) : (
                    <span className="text-gray-400">—</span>
                )}
            </div>
        )},
        { key: 'roles', label: 'Perfil', render: (row) => (
            <div className="flex gap-1 flex-wrap">
                {(row.roles || []).map(r => (
                    <span key={r.id} className="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-medium">{r.nome}</span>
                ))}
                {(!row.roles || row.roles.length === 0) && <span className="text-xs text-gray-400">Sem perfil</span>}
            </div>
        )},
    ];

    return (
        <AdminLayout>
            <Head title="Usuarios" />
            <PageHeader title="Usuarios" subtitle="Cadastro de usuarios e vinculo com organograma">
                <Link href="/configuracoes/usuarios/create"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors">
                    <i className="fas fa-user-plus" />
                    Novo Usuario
                </Link>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 p-4 mb-3">
                <form onSubmit={onBuscaSubmit} className="flex flex-wrap items-center gap-2">
                    <div className="relative flex-1 min-w-[260px]">
                        <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                        <input type="text" value={busca} onChange={(e) => setBusca(e.target.value)}
                            placeholder="Buscar por nome, e-mail ou CPF..."
                            className="ds-input pl-9" />
                    </div>
                    <select value={tipo} onChange={(e) => { setTipo(e.target.value); aplicarFiltros(busca, e.target.value); }}
                        className="ds-input w-40">
                        <option value="">Todos os tipos</option>
                        <option value="interno">Internos</option>
                        <option value="externo">Externos</option>
                    </select>
                    <Button type="submit" icon="fas fa-search" variant="secondary">Buscar</Button>
                    {(busca || tipo) && (
                        <button type="button" onClick={limparFiltros}
                            className="text-xs text-gray-500 hover:text-gray-800 px-2">
                            <i className="fas fa-times mr-1" />Limpar
                        </button>
                    )}
                    <span className="text-xs text-gray-400 ml-auto">
                        {usuarios?.total ?? data.length} usuario(s)
                    </span>
                </form>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 p-6">
                <DataTable
                    columns={columns}
                    data={data}
                    pagination={usuarios?.links ? usuarios : null}
                    actions={(row) => (
                        <>
                            <Link href={`/configuracoes/usuarios/${row.id}/edit`}
                                className="text-blue-500 hover:text-blue-700 px-1 text-xs">
                                <i className="fas fa-edit" /> Editar
                            </Link>
                            <button onClick={() => {
                                if (confirm(`Excluir usuario "${row.name}"?`)) router.delete(`/configuracoes/usuarios/${row.id}`);
                            }} className="text-red-400 hover:text-red-600 px-1 text-xs ml-2">
                                <i className="fas fa-trash" /> Excluir
                            </button>
                        </>
                    )}
                />
            </div>
        </AdminLayout>
    );
}

function mascararCpf(cpf) {
    const d = (cpf || '').replace(/\D/g, '');
    if (d.length !== 11) return cpf;
    return `${d.slice(0,3)}.***.***-${d.slice(-2)}`;
}
