/**
 * Classificação de um processo, memorando, ofício, circular ou documento (TR 4.27): a atual,
 * reclassificar com motivo (quem mantém o plano) e o histórico das trocas.
 */
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const data = (d) => (d ? new Date(d).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '');

export default function ClassificacaoDoItem({ classificacao, className = '' }) {
    const [aberto, setAberto] = useState(false);
    const form = useForm({ classificacao_id: '', motivo: '' });
    if (! classificacao) return null;
    const { modulo, item_id, atual, atual_id, pode, opcoes = [], historico = [] } = classificacao;

    const salvar = (e) => {
        e.preventDefault();
        form.post(`/admin/classificacao/item/${modulo}/${item_id}`, { preserveScroll: true, onSuccess: () => { setAberto(false); form.reset(); } });
    };

    return (
        <Card title="Classificação" className={className}>
            <div className="flex items-start justify-between gap-3">
                <p className={`text-sm ${atual ? 'text-gray-800' : 'text-gray-400'}`}>{atual || 'Sem classificação'}</p>
                {pode && <Button size="sm" variant="secondary" icon="fas fa-sitemap" onClick={() => setAberto(true)}>Reclassificar</Button>}
            </div>
            {historico.length > 0 && (
                <ul className="mt-3 divide-y divide-gray-100 border-t border-gray-100">
                    {historico.map(h => (
                        <li key={h.id} className="py-2 text-xs">
                            <p className="text-gray-700">{h.de || 'Sem classificação'} → <b>{h.para || 'Sem classificação'}</b></p>
                            <p className="text-gray-500">{data(h.em)} · {h.automatica ? 'Automática' : (h.usuario || '-')}{h.motivo ? ` · ${h.motivo}` : ''}</p>
                        </li>
                    ))}
                </ul>
            )}

            <Modal show={aberto} onClose={() => setAberto(false)} title="Reclassificar">
                <form onSubmit={salvar} className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Nova classificação</label>
                        <select value={form.data.classificacao_id} onChange={(e) => form.setData('classificacao_id', e.target.value)} className="ds-input">
                            <option value="">Selecione…</option>
                            {opcoes.filter(o => o.id !== atual_id).map(o => <option key={o.id} value={o.id}>{'  '.repeat(o.nivel - 1)}{o.rotulo}</option>)}
                        </select>
                        {form.errors.classificacao_id && <p className="mt-1 text-xs text-red-600">{form.errors.classificacao_id}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                        <textarea value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} rows={3} maxLength={1000} className="ds-input" />
                        {form.errors.motivo && <p className="mt-1 text-xs text-red-600">{form.errors.motivo}</p>}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setAberto(false)}>Cancelar</Button>
                        <Button type="submit" variant="primary" loading={form.processing} disabled={! form.data.classificacao_id || ! form.data.motivo.trim()}>Reclassificar</Button>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}
