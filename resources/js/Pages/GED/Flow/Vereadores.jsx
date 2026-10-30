/**
 * Vereadores do protocolo legislativo (TR 4.37.1): quem encaminha requerimentos e indicações.
 * Inativar mantém o histórico das proposições.
 */
import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';

const VAZIO = { nome: '', partido: '', email: '', ativo: true };

export default function Vereadores({ vereadores = [] }) {
    const [editando, setEditando] = useState(null);   // null fechado; 0 novo; id editando
    const form = useForm(VAZIO);
    const abrir = (v) => { form.clearErrors(); form.setData(v ? { nome: v.nome, partido: v.partido || '', email: v.email || '', ativo: v.ativo } : VAZIO); setEditando(v ? v.id : 0); };
    const salvar = (e) => {
        e.preventDefault();
        const ok = { preserveScroll: true, onSuccess: () => setEditando(null) };
        editando ? form.put(`/flow/legislativo/vereadores/${editando}`, ok) : form.post('/flow/legislativo/vereadores', ok);
    };

    return (
        <AdminLayout>
            <Head title="Vereadores" />
            <PageHeader title="Vereadores" subtitle="Quem encaminha requerimentos e indicações ao Executivo">
                <Button icon="fas fa-plus" onClick={() => abrir(null)}>Novo vereador</Button>
            </PageHeader>

            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Nome</th><th className="px-3 py-2">Partido</th><th className="px-3 py-2">E-mail</th><th className="px-3 py-2">Proposições</th><th className="px-3 py-2">Situação</th><th className="px-3 py-2" /></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {vereadores.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-gray-400">Nenhum vereador cadastrado.</td></tr>}
                        {vereadores.map(v => (
                            <tr key={v.id} className={v.ativo ? '' : 'text-gray-400'}>
                                <td className="px-3 py-2 font-medium">{v.nome}</td>
                                <td className="px-3 py-2">{v.partido || '—'}</td>
                                <td className="px-3 py-2">{v.email || '—'}</td>
                                <td className="px-3 py-2">{v.proposicoes}</td>
                                <td className="px-3 py-2">{v.ativo ? <span className="text-[11px] rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5">Ativo</span> : <span className="text-[11px] rounded-full bg-gray-100 text-gray-600 px-2 py-0.5">Inativo</span>}</td>
                                <td className="px-3 py-2 text-right"><Button size="sm" variant="secondary" icon="fas fa-pen" onClick={() => abrir(v)}>Editar</Button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Modal show={editando !== null} onClose={() => setEditando(null)} title={editando ? 'Editar vereador' : 'Novo vereador'}>
                <form onSubmit={salvar} className="space-y-3">
                    <label className="block text-sm text-gray-700">Nome<input value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} className="ds-input mt-1" /></label>
                    {form.errors.nome && <p className="text-xs text-red-600">{form.errors.nome}</p>}
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm text-gray-700">Partido<input value={form.data.partido} onChange={(e) => form.setData('partido', e.target.value)} className="ds-input mt-1" /></label>
                        <label className="block text-sm text-gray-700">E-mail<input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="ds-input mt-1" /></label>
                    </div>
                    {form.errors.email && <p className="text-xs text-red-600">{form.errors.email}</p>}
                    <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! form.data.ativo} onChange={(e) => form.setData('ativo', e.target.checked)} /> Ativo (em mandato)</label>
                    <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button type="submit" loading={form.processing}>Salvar</Button></div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
