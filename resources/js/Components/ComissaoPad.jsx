/**
 * PAD na tela do processo (TR 4.40): tipo de infração, gravidade, base legal e a comissão
 * processante (presidente, membros, secretário), que passa a ver o processo sigiloso.
 */
import { useState } from 'react';
import { router } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';

export default function ComissaoPad({ processoId, pad, usuarios = [] }) {
    const [novo, setNovo] = useState({ user_id: '', papel: 'membro' });
    const i = pad.infracao;

    return (
        <Card title="Processo administrativo disciplinar" className="mb-6">
            {i && (
                <div className="flex flex-wrap items-center gap-2 text-sm mb-4">
                    <span className="rounded-full bg-red-50 text-red-700 px-2.5 py-0.5 text-xs font-semibold">{i.nome}</span>
                    <span className="text-xs text-gray-600">Gravidade: {i.gravidade}</span>
                    {i.base_legal && <span className="text-xs text-gray-500">· {i.base_legal}</span>}
                </div>
            )}
            <p className="text-sm font-medium text-gray-700 mb-2">Comissão processante</p>
            {pad.comissao.length === 0 ? <p className="text-sm text-gray-400">Comissão ainda não designada.</p> : (
                <ul className="divide-y divide-gray-100 text-sm">
                    {pad.comissao.map(m => (
                        <li key={m.id} className="py-1.5 flex items-center gap-2">
                            <i className="fas fa-user-shield text-gray-400" /><span>{m.nome}</span>
                            <span className="text-[11px] rounded-full bg-gray-100 text-gray-700 px-2 py-0.5">{m.papel}</span>
                            {pad.pode_gerir && (
                                <button type="button" className="ml-auto text-xs text-red-600 hover:underline"
                                    onClick={() => router.delete(`/flow/pad/${processoId}/comissao/${m.id}`, { preserveScroll: true })}>retirar</button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {pad.pode_gerir && (
                <div className="mt-3 flex flex-wrap gap-2 items-end">
                    <select value={novo.user_id} onChange={(e) => setNovo({ ...novo, user_id: e.target.value })} className="ds-input max-w-xs">
                        <option value="">Servidor…</option>
                        {usuarios.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                    </select>
                    <select value={novo.papel} onChange={(e) => setNovo({ ...novo, papel: e.target.value })} className="ds-input max-w-[180px]">
                        {Object.entries(pad.papeis).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
                    </select>
                    <Button size="sm" icon="fas fa-user-plus" disabled={! novo.user_id}
                        onClick={() => router.post(`/flow/pad/${processoId}/comissao`, novo, { preserveScroll: true, onSuccess: () => setNovo({ user_id: '', papel: 'membro' }) })}>Incluir na comissão</Button>
                </div>
            )}
        </Card>
    );
}