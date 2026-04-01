/**
 * Administracao — Perfis e Permissoes — GED
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Button from '../../../../Components/Button';
import Modal from '../../../../Components/Modal';
import Card from '../../../../Components/Card';

const PERMISSION_GROUPS = [
    {
        label: 'Documentos',
        icon: 'fas fa-file-alt',
        permissions: ['documento.visualizar', 'documento.criar', 'documento.editar', 'documento.excluir', 'documento.download'],
    },
    {
        label: 'Repositorio',
        icon: 'fas fa-folder-open',
        permissions: ['pasta.visualizar', 'pasta.criar', 'pasta.editar', 'pasta.excluir'],
    },
    {
        label: 'Fluxos',
        icon: 'fas fa-project-diagram',
        permissions: ['fluxo.visualizar', 'fluxo.criar', 'fluxo.editar', 'fluxo.gerenciar'],
    },
    {
        label: 'Administracao',
        icon: 'fas fa-cog',
        permissions: ['admin.usuarios', 'admin.roles', 'admin.configuracoes'],
    },
];

export default function Roles({ roles, permissions }) {
    const [showForm, setShowForm] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const rolesList = roles || [];
    const permList = permissions || [];

    const openEdit = (role) => {
        setEditingRole(role);
        setShowForm(true);
    };

    const openNew = () => {
        setEditingRole(null);
        setShowForm(true);
    };

    return (
        <AdminLayout>
            <Head title="Perfis e Permissoes" />
            <PageHeader title="Perfis e Permissoes" subtitle="Configurar papeis de acesso (RBAC)">
                <Button icon="fas fa-plus" onClick={openNew}>Novo Perfil</Button>
            </PageHeader>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {rolesList.map(role => (
                    <Card key={role.id}>
                        <div className="flex items-start justify-between mb-3">
                            <div>
                                <h3 className="text-sm font-bold text-gray-800">{role.nome}</h3>
                                {role.descricao && <p className="text-xs text-gray-400 mt-0.5">{role.descricao}</p>}
                            </div>
                            <div className="flex gap-1">
                                <button onClick={() => openEdit(role)} className="text-blue-500 hover:text-blue-700 p-1">
                                    <i className="fas fa-edit text-xs" />
                                </button>
                                <button onClick={() => {
                                    if (confirm('Excluir este perfil?')) router.delete(`/admin/roles/${role.id}`);
                                }} className="text-red-400 hover:text-red-600 p-1">
                                    <i className="fas fa-trash text-xs" />
                                </button>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {(role.permissions || []).map(p => (
                                <span key={p.id} className="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{p.nome}</span>
                            ))}
                            {(!role.permissions || role.permissions.length === 0) && (
                                <span className="text-xs text-gray-400">Sem permissoes</span>
                            )}
                        </div>
                        <div className="mt-3 pt-3 border-t border-gray-100">
                            <p className="text-xs text-gray-400">{role.users_count || 0} usuarios</p>
                        </div>
                    </Card>
                ))}
            </div>

            <RoleFormModal show={showForm} onClose={() => { setShowForm(false); setEditingRole(null); }}
                role={editingRole} permissions={permList} />
        </AdminLayout>
    );
}

function RoleFormModal({ show, onClose, role, permissions }) {
    const isEdit = !!role?.id;
    const { data, setData, post, put, processing, errors, reset } = useForm({
        nome: role?.nome || '',
        descricao: role?.descricao || '',
        permission_ids: role?.permissions?.map(p => p.id) || [],
    });

    const togglePermission = (permId) => {
        const current = data.permission_ids || [];
        setData('permission_ids', current.includes(permId) ? current.filter(id => id !== permId) : [...current, permId]);
    };

    const toggleGroup = (groupPerms) => {
        const permIds = permissions.filter(p => groupPerms.includes(p.nome)).map(p => p.id);
        const allSelected = permIds.every(id => data.permission_ids.includes(id));
        if (allSelected) {
            setData('permission_ids', data.permission_ids.filter(id => !permIds.includes(id)));
        } else {
            setData('permission_ids', [...new Set([...data.permission_ids, ...permIds])]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/roles/${role.id}`, { onSuccess: () => { reset(); onClose(); } });
        } else {
            post('/admin/roles', { onSuccess: () => { reset(); onClose(); } });
        }
    };

    return (
        <Modal show={show} onClose={onClose} title={isEdit ? 'Editar Perfil' : 'Novo Perfil'} maxWidth="2xl">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input type="text" value={data.nome} onChange={(e) => setData('nome', e.target.value)}
                            className="ds-input" placeholder="Ex: Gestor Documental" />
                        {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                        <input type="text" value={data.descricao} onChange={(e) => setData('descricao', e.target.value)}
                            className="ds-input" placeholder="Descricao do perfil" />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-3">Permissoes</label>
                    <div className="space-y-4 max-h-64 overflow-y-auto">
                        {PERMISSION_GROUPS.map(group => {
                            const groupPermIds = permissions.filter(p => group.permissions.includes(p.nome)).map(p => p.id);
                            const allSelected = groupPermIds.length > 0 && groupPermIds.every(id => data.permission_ids.includes(id));

                            return (
                                <div key={group.label} className="border border-gray-100 rounded-lg p-3">
                                    <label className="flex items-center gap-2 cursor-pointer mb-2">
                                        <input type="checkbox" checked={allSelected}
                                            onChange={() => toggleGroup(group.permissions)}
                                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        <i className={`${group.icon} text-xs text-gray-500`} />
                                        <span className="text-sm font-semibold text-gray-700">{group.label}</span>
                                    </label>
                                    <div className="grid grid-cols-2 gap-1 pl-6">
                                        {permissions.filter(p => group.permissions.includes(p.nome)).map(perm => (
                                            <label key={perm.id} className="flex items-center gap-2 cursor-pointer py-1">
                                                <input type="checkbox" checked={data.permission_ids.includes(perm.id)}
                                                    onChange={() => togglePermission(perm.id)}
                                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                <span className="text-xs text-gray-600">{perm.nome.split('.')[1] || perm.nome}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-save">
                        {isEdit ? 'Salvar Alteracoes' : 'Criar Perfil'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
