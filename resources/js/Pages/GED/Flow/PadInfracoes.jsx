/**
 * Tipos de infração do PAD (TR 4.40.2): gravidade, base legal e o setor que recebe o
 * processo na abertura.
 */
import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';

const VAZIO = { nome: '', gravidade: 'media', base_legal: '', unidade_id: '', ativo: true };

export default function PadInfracoes({ infracoes = [], gravidades = {}, unidades = [] }) {
    const [editando, setEditando] = useState(null);
    const form = useForm(VAZIO);
    const abrir = (i) => { form.clearErrors(); form.setData(i ? { nome: i.nome, gravidade: i.gravidade, base_legal: i.base_legal || '', unidade_id: i.unidade_id || '', ativo: i.ativo } : VAZIO); setEditando(i ? i.id : 0); };
    const salvar = (e) => {
        e.preventDefault();
        const ok = { preserveScroll: true, onSuccess: () => setEditando(null) };
        editando ? form.put(`/flow/pad/infracoes/${editando}`, ok) : form.post('/flow/pad/infracoes', ok);
    };

    return (
        <AdminLayout>
            <Head title="Tipos de infração" />
            <PageHeader title="Tipos de infração (PAD)" subtitle="O setor escolhido recebe o PAD na abertura">
                <Button icon="fas fa-plus" onClick={() => abrir(null)}>Novo tipo</Button>
            </PageHeader>
            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Infração</th><th className="px-3 py-2">Gravidade</th><th className="px-3 py-2">Base legal</th><th className="px-3 py-2">Setor que recebe</th><th className="px-3 py-2">Situação</th><th className="px-3 py-2" /></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {infracoes.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-gray-400">Nenhum tipo cadastrado.</td></tr>}
                        {infracoes.map(i => (
                            <tr key={i.id} className={i.ativo ? '' : 'text-gray-400'}>
                                <td className="px-3 py-2 font-medium">{i.nome}</td>
                                <td className="px-3 py-2">{gravidades[i.gravidade]}</td>
                                <td className="px-3 py-2 text-xs">{i.base_legal || '—'}</td>
                                <td className="px-3 py-2">{i.unidade?.nome || '—'}</td>
                                <td className="px-3 py-2">{i.ativo ? 'Ativo' : 'Inativo'}</td>
                                <td className="px-3 py-2 text-right"><Button size="sm" variant="secondary" icon="fas fa-pen" onClick={() => abrir(i)}>Editar</Button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Modal show={editando !== null} onClose={() => setEditando(null)} title={editando ? 'Editar tipo de infração' : 'Novo tipo de infração'}>
                <form onSubmit={salvar} className="space-y-3">
                    <label className="block text-sm text-gray-700">Infração<input value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} className="ds-input mt-1" /></label>
                    {form.errors.nome && <p className="text-xs text-red-600">{form.errors.nome}</p>}
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm text-gray-700">Gravidade<select value={form.data.gravidade} onChange={(e) => form.setData('gravidade', e.target.value)} className="ds-input mt-1">
                            {Object.entries(gravidades).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                        <label className="block text-sm text-gray-700">Setor que recebe<select value={form.data.unidade_id} onChange={(e) => form.setData('unidade_id', e.target.value)} className="ds-input mt-1">
                            <option value="">Escolher na abertura</option>{unidades.map(u => <option key={u.id} value={u.id}>{u.nome}</option>)}</select></label>
                    </div>
                    <label className="block text-sm text-gray-700">Base legal<input value={form.data.base_legal} onChange={(e) => form.setData('base_legal', e.target.value)} className="ds-input mt-1" placeholder="Ex.: Lei Complementar 12/2010, art. 145" /></label>
                    <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! form.data.ativo} onChange={(e) => form.setData('ativo', e.target.checked)} /> Ativo</label>
                    <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button type="submit" loading={form.processing}>Salvar</Button></div>
                </form>
            </Modal>
        </AdminLayout>
    );
}