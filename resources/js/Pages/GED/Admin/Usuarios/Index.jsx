/**
 * Administracao — Gerenciamento de Usuarios — GED
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Button from '../../../../Components/Button';
import DataTable from '../../../../Components/DataTable';
import Modal from '../../../../Components/Modal';

export default function Usuarios({ usuarios, roles }) {
    const [showInvite, setShowInvite] = useState(false);
    const data = usuarios?.data || usuarios || [];
    const rolesList = roles || [];

    const columns = [
        { key: 'name', label: 'Nome', render: (row) => (
            <div className="flex items-center gap-3">
                <div className="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                    <span className="text-white text-xs font-bold">{(row.name || 'U').substring(0, 2).toUpperCase()}</span>
                </div>
                <div>
                    <p className="text-sm font-medium text-gray-700">{row.name}</p>
                    <p className="text-xs text-gray-400">{row.email}</p>
                </div>
            </div>
        )},
        { key: 'roles', label: 'Perfil', render: (row) => (
            <div className="flex gap-1 flex-wrap">
                {(row.roles || []).map(r => (
                    <span key={r.id} className="text-[10px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">{r.nome}</span>
                ))}
                {(!row.roles || row.roles.length === 0) && <span className="text-xs text-gray-400">Sem perfil</span>}
            </div>
        )},
        { key: 'created_at', label: 'Criado em', render: (row) => (
            <span className="text-xs text-gray-400">{row.created_at ? new Date(row.created_at).toLocaleDateString('pt-BR') : '-'}</span>
        )},
    ];

    return (
        <AdminLayout>
            <Head title="Usuarios" />
            <PageHeader title="Usuarios" subtitle="Gerenciar usuarios e permissoes do sistema">
                <Button icon="fas fa-user-plus" onClick={() => setShowInvite(true)}>Convidar Usuario</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 p-6">
                <DataTable
                    columns={columns}
                    data={data}
                    pagination={usuarios?.links ? usuarios : null}
                    actions={(row) => (
                        <>
                            <button onClick={() => {
                                if (confirm('Desativar usuario?')) router.delete(`/admin/usuarios/${row.id}`);
                            }} className="text-red-400 hover:text-red-600 px-1 text-xs">
                                <i className="fas fa-ban" /> Desativar
                            </button>
                        </>
                    )}
                />
            </div>

            <InviteModal show={showInvite} onClose={() => setShowInvite(false)} roles={rolesList} />
        </AdminLayout>
    );
}

function InviteModal({ show, onClose, roles }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        role_ids: [],
    });

    const toggleRole = (roleId) => {
        const current = data.role_ids || [];
        const updated = current.includes(roleId)
            ? current.filter(id => id !== roleId)
            : [...current, roleId];
        setData('role_ids', updated);
    };

    const submit = (e) => {
        e.preventDefault();
        post('/admin/usuarios', { onSuccess: () => { reset(); onClose(); } });
    };

    return (
        <Modal show={show} onClose={onClose} title="Convidar Usuario">
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                        className="ds-input" placeholder="Nome completo" />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                        className="ds-input" placeholder="email@exemplo.com" />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                    <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                        className="ds-input" placeholder="Senha inicial" />
                    {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Perfis</label>
                    <div className="space-y-2">
                        {roles.map(role => (
                            <label key={role.id} className="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" checked={(data.role_ids || []).includes(role.id)}
                                    onChange={() => toggleRole(role.id)}
                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span className="text-sm text-gray-700">{role.nome}</span>
                                {role.descricao && <span className="text-xs text-gray-400">— {role.descricao}</span>}
                            </label>
                        ))}
                    </div>
                </div>
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-user-plus">Convidar</Button>
                </div>
            </form>
        </Modal>
    );
}
