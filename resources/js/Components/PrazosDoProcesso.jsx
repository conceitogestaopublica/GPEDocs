/**
 * Prazos e atividades lançados à mão no processo (TR 4.13, 4.32.16, 4.32.17, 4.38.16):
 * vários por processo, para todos, um setor ou só para mim; realizar, editar e excluir
 * ficam no histórico; alerta individual N dias antes (no sistema ou também por e-mail).
 */
import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';
import { useConfirm } from './ConfirmProvider';

export const SITUACAO_PRAZO = {
    com_folga: { rotulo: 'Com folga', cor: 'bg-green-100 text-green-700' },
    vencendo:  { rotulo: 'Vencendo', cor: 'bg-amber-100 text-amber-700' },
    vencido:   { rotulo: 'Vencido', cor: 'bg-red-100 text-red-700' },
    realizado: { rotulo: 'Realizado', cor: 'bg-gray-100 text-gray-600' },
};
const ESCOPO = { todos: 'Para todos', setor: 'Para o setor', pessoal: 'Só para mim' };

const fmt = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
// valor para <input type="datetime-local"> na hora de Brasília (a tela do servidor é BRT)
const paraCampo = (iso) => {
    if (! iso) return '';
    const d = new Date(iso);
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
};

export default function PrazosDoProcesso({ processoId, prazos = [], unidades = [], podeIncluir = true }) {
    const confirmar = useConfirm();
    const [editando, setEditando] = useState(null);   // null | 'novo' | prazo
    const form = useForm({ titulo: '', descricao: '', vence_em: '', escopo: 'todos', unidade_id: '', alerta_dias: '', alerta_email: false });

    const abrir = (p) => {
        setEditando(p);
        form.clearErrors();
        form.setData(p === 'novo'
            ? { titulo: '', descricao: '', vence_em: '', escopo: 'todos', unidade_id: '', alerta_dias: '1', alerta_email: false }
            : { titulo: p.titulo, descricao: p.descricao || '', vence_em: paraCampo(p.vence_em), escopo: p.escopo,
                unidade_id: p.unidade_id ? String(p.unidade_id) : '', alerta_dias: p.alerta_dias ?? '', alerta_email: !! p.alerta_email });
    };
    const salvar = (e) => {
        e.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: () => setEditando(null) };
        editando === 'novo' ? form.post(`/processos/${processoId}/prazos`, opcoes) : form.put(`/prazos/${editando.id}`, opcoes);
    };

    return (
        <Card title={`Prazos e atividades (${prazos.filter(p => p.situacao !== 'realizado').length} em aberto)`}>
            {podeIncluir && (
                <div className="mb-3">
                    <Button variant="secondary" icon="fas fa-plus" onClick={() => abrir('novo')}>Novo prazo</Button>
                </div>
            )}
            {prazos.length === 0 ? (
                <p className="text-sm text-gray-400">Nenhum prazo lançado. O prazo da etapa em andamento aparece na linha do tempo.</p>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {prazos.map(p => (
                        <li key={p.id} className="py-2.5 flex items-start justify-between gap-3 flex-wrap">
                            <div className="min-w-0">
                                <p className={`text-sm font-medium ${p.situacao === 'realizado' ? 'text-gray-500 line-through' : 'text-gray-800'}`}>{p.titulo}</p>
                                <p className="text-xs text-gray-500">
                                    Vence {fmt(p.vence_em)} · {p.escopo === 'setor' ? `Setor: ${p.setor}` : ESCOPO[p.escopo]} · por {p.criador}
                                    {p.alerta_dias !== null && p.alerta_dias !== undefined && <> · aviso {p.alerta_dias} dia(s) antes{p.alerta_email ? ' (e-mail)' : ''}</>}
                                </p>
                                {p.situacao === 'realizado' && <p className="text-xs text-gray-500">Realizado em {fmt(p.concluido_em)} por {p.concluidor}</p>}
                                {p.descricao && <p className="text-xs text-gray-600 mt-0.5 whitespace-pre-wrap">{p.descricao}</p>}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className={`text-[11px] font-medium rounded-full px-2 py-0.5 ${SITUACAO_PRAZO[p.situacao].cor}`}>{SITUACAO_PRAZO[p.situacao].rotulo}</span>
                                {p.situacao !== 'realizado' && (
                                    <>
                                        <button type="button" title="Marcar como realizado" className="text-xs text-green-700 hover:underline"
                                            onClick={() => router.post(`/prazos/${p.id}/concluir`, {}, { preserveScroll: true })}>
                                            <i className="fas fa-check mr-1" />Realizado
                                        </button>
                                        <button type="button" title="Editar" className="text-xs text-blue-700 hover:underline" onClick={() => abrir(p)}>Editar</button>
                                    </>
                                )}
                                <button type="button" title="Excluir" className="text-xs text-red-600 hover:underline"
                                    onClick={async () => (await confirmar({ titulo: `Excluir o prazo "${p.titulo}"?`, descricao: 'Fica registrado no histórico do processo.', tom: 'atencao', rotuloConfirmar: 'Excluir' }))
                                        && router.delete(`/prazos/${p.id}`, { preserveScroll: true })}>
                                    Excluir
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Modal show={editando !== null} onClose={() => setEditando(null)} title={editando === 'novo' ? 'Novo prazo' : 'Editar prazo'}>
                <form onSubmit={salvar} className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">O que precisa ser feito <span className="text-red-500">*</span></label>
                        <input value={form.data.titulo} onChange={(e) => form.setData('titulo', e.target.value)} maxLength={150} className="ds-input" placeholder="Ex.: Responder ao requerente" />
                        {form.errors.titulo && <p className="mt-1 text-xs text-red-600">{form.errors.titulo}</p>}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Vence em <span className="text-red-500">*</span></label>
                            <input type="datetime-local" value={form.data.vence_em} onChange={(e) => form.setData('vence_em', e.target.value)} className="ds-input" />
                            {form.errors.vence_em && <p className="mt-1 text-xs text-red-600">{form.errors.vence_em}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Para quem</label>
                            <select value={form.data.escopo} onChange={(e) => form.setData('escopo', e.target.value)} className="ds-input">
                                {Object.entries(ESCOPO).map(([v, r]) => <option key={v} value={v}>{r}</option>)}
                            </select>
                        </div>
                    </div>
                    {form.data.escopo === 'setor' && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Setor</label>
                            <select value={form.data.unidade_id} onChange={(e) => form.setData('unidade_id', e.target.value)} className="ds-input">
                                <option value="">Escolha…</option>
                                {unidades.map(u => <option key={u.id} value={u.id}>{' '.repeat(((u.nivel || 1) - 1) * 3)}{u.nome}</option>)}
                            </select>
                            {form.errors.unidade_id && <p className="mt-1 text-xs text-red-600">{form.errors.unidade_id}</p>}
                        </div>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Avisar quantos dias antes</label>
                            <input type="number" min="0" max="365" value={form.data.alerta_dias} onChange={(e) => form.setData('alerta_dias', e.target.value)} className="ds-input" placeholder="Sem aviso" />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer pb-2">
                            <input type="checkbox" checked={form.data.alerta_email} onChange={(e) => form.setData('alerta_email', e.target.checked)} className="rounded border-gray-300 text-blue-600" />
                            Avisar também por e-mail
                        </label>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                        <textarea value={form.data.descricao} onChange={(e) => form.setData('descricao', e.target.value)} rows={2} className="ds-input !h-auto" />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button type="submit" variant="primary" loading={form.processing}>Salvar</Button>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}
