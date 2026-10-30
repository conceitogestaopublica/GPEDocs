/**
 * Estrutura do Diário Oficial — cadernos, seções e subseções (TR 4.43.11) — e os responsáveis,
 * que montam e publicam as edições (TR 4.43.12, 4.43.13, 4.43.19).
 */
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

const NIVEL = { 1: 'Caderno', 2: 'Seção', 3: 'Subseção' };

export default function Configuracao({ estrutura = [], responsaveis = [], usuarios = [] }) {
    const [no, setNo] = useState(null);
    const [resp, setResp] = useState(null);
    const salvarNo = () => (no.id ? router.put(`/diario/estrutura/${no.id}`, no, { preserveScroll: true, onSuccess: () => setNo(null) })
        : router.post('/diario/estrutura', no, { preserveScroll: true, onSuccess: () => setNo(null) }));
    const salvarResp = () => (resp.id ? router.put(`/diario/responsaveis/${resp.id}`, resp, { preserveScroll: true, onSuccess: () => setResp(null) })
        : router.post('/diario/responsaveis', resp, { preserveScroll: true, onSuccess: () => setResp(null) }));

    return (
        <AdminLayout>
            <Head title="Cadernos e responsáveis" />
            <PageHeader title="Cadernos e responsáveis" subtitle="Estrutura do Diário Oficial e quem publica as edições" />
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card title="Cadernos, seções e subseções">
                    <ul className="text-sm divide-y divide-gray-100">
                        {estrutura.length === 0 && <li className="py-3 text-gray-400">Nenhum caderno ainda.</li>}
                        {estrutura.map(n => (
                            <li key={n.id} className={`py-1.5 flex items-center gap-2 ${n.ativo ? '' : 'text-gray-400'}`} style={{ paddingLeft: `${(n.nivel - 1) * 20}px` }}>
                                <i className={`fas ${n.nivel === 1 ? 'fa-book' : n.nivel === 2 ? 'fa-bookmark' : 'fa-angle-right'} text-gray-400`} />
                                <span className={n.nivel === 1 ? 'font-semibold' : ''}>{n.nome}</span>
                                <span className="text-[10px] text-gray-400">{NIVEL[n.nivel]}</span>
                                <span className="ml-auto flex gap-2 text-xs">
                                    {n.nivel < 3 && <button type="button" className="text-cyan-700 hover:underline" onClick={() => setNo({ nome: '', parent_id: n.id, ordem: 0, ativo: true, _pai: n.nome })}>+ {NIVEL[n.nivel + 1].toLowerCase()}</button>}
                                    <button type="button" className="text-gray-600 hover:underline" onClick={() => setNo({ ...n })}>editar</button>
                                    <button type="button" className="text-red-600 hover:underline" onClick={() => router.delete(`/diario/estrutura/${n.id}`, { preserveScroll: true })}>excluir</button>
                                </span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3"><Button size="sm" icon="fas fa-plus" onClick={() => setNo({ nome: '', parent_id: null, ordem: 0, ativo: true })}>Caderno</Button></div>
                </Card>
                <Card title="Responsáveis pelo diário" subtitle="Montam e publicam as edições, sem nova autenticação na publicação">
                    <ul className="text-sm divide-y divide-gray-100">
                        {responsaveis.length === 0 && <li className="py-3 text-gray-400">Nenhum responsável.</li>}
                        {responsaveis.map(r => (
                            <li key={r.id} className={`py-2 flex items-center gap-2 ${r.ativo ? '' : 'text-gray-400'}`}>
                                <i className="fas fa-user-tie text-gray-400" /><span>{r.nome}</span><span className="text-xs text-gray-500">{r.funcao}</span>
                                <span className={`text-[10px] rounded-full px-1.5 ${r.ativo ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'}`}>{r.ativo ? 'ativo' : 'inativo'}</span>
                                <button type="button" className="ml-auto text-xs text-gray-600 hover:underline" onClick={() => setResp({ ...r })}>editar</button>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-3"><Button size="sm" icon="fas fa-plus" onClick={() => setResp({ user_id: '', funcao: '', telefone: '', email: '', ativo: true })}>Responsável</Button></div>
                </Card>
            </div>

            <Modal show={!! no} onClose={() => setNo(null)} title={no?.id ? 'Editar' : (no?.parent_id ? `Novo item em ${no?._pai || ''}` : 'Novo caderno')}>
                {no && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Nome<input value={no.nome} onChange={(e) => setNo({ ...no, nome: e.target.value })} className="ds-input mt-1" /></label>
                        <label className="block text-sm text-gray-700">Ordem<input type="number" min="0" value={no.ordem} onChange={(e) => setNo({ ...no, ordem: e.target.value })} className="ds-input mt-1" /></label>
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! no.ativo} onChange={(e) => setNo({ ...no, ativo: e.target.checked })} /> Ativo</label>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setNo(null)}>Cancelar</Button><Button onClick={salvarNo}>Salvar</Button></div>
                    </div>
                )}
            </Modal>
            <Modal show={!! resp} onClose={() => setResp(null)} title={resp?.id ? 'Editar responsável' : 'Novo responsável'}>
                {resp && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Servidor<select value={resp.user_id} onChange={(e) => setResp({ ...resp, user_id: e.target.value })} className="ds-input mt-1" disabled={!! resp.id}>
                            <option value="">Escolha…</option>{usuarios.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}</select></label>
                        <label className="block text-sm text-gray-700">Função<input value={resp.funcao || ''} onChange={(e) => setResp({ ...resp, funcao: e.target.value })} className="ds-input mt-1" placeholder="Editor responsável" /></label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="block text-sm text-gray-700">Telefone<input value={resp.telefone || ''} onChange={(e) => setResp({ ...resp, telefone: e.target.value })} className="ds-input mt-1" /></label>
                            <label className="block text-sm text-gray-700">E-mail<input value={resp.email || ''} onChange={(e) => setResp({ ...resp, email: e.target.value })} className="ds-input mt-1" /></label>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! resp.ativo} onChange={(e) => setResp({ ...resp, ativo: e.target.checked })} /> Ativo</label>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setResp(null)}>Cancelar</Button><Button onClick={salvarResp}>Salvar</Button></div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}