/**
 * Desativar setor (TR 4.12.9, 4.12.10): quem está no setor é realocado antes para outro
 * setor ativo. Usado no organograma e em Configurações › UG › Organograma.
 */
import { useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from './Modal';
import Button from './Button';

/** Árvore → lista plana de setores ativos com o caminho ("Secretaria › Departamento"). */
export function setoresAtivos(arvore, filhosDe = (n) => n.filhos || [], caminho = []) {
    return arvore.flatMap(n => n.ativo === false ? [] : [
        { id: n.id, caminho: [...caminho, n.nome].join(' › ') },
        ...setoresAtivos(filhosDe(n), filhosDe, [...caminho, n.nome]),
    ]);
}

export default function DesativarSetorModal({ setor, opcoes = [], url, servidores = null, onClose }) {
    const form = useForm({ destino_id: '' });
    const [filtro, setFiltro] = useState('');
    useEffect(() => { form.reset(); form.clearErrors(); setFiltro(''); }, [setor?.id]);
    const lista = useMemo(() => {
        const f = filtro.trim().toLowerCase();
        return opcoes.filter(o => o.id !== setor?.id && (! f || o.caminho.toLowerCase().includes(f))).slice(0, 200);
    }, [opcoes, filtro, setor?.id]);

    const enviar = (e) => {
        e.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <Modal show={!! setor} onClose={onClose} title={`Desativar "${setor?.nome || ''}"`}>
            <form onSubmit={enviar} className="space-y-3">
                <p className="text-sm text-gray-600">
                    {servidores === 0
                        ? 'Nenhum servidor está neste setor. Documentos e histórico continuam ligados a ele.'
                        : <>Os servidores deste setor{servidores ? <> (<strong>{servidores}</strong>)</> : ''} vão para o setor escolhido abaixo. Documentos e histórico continuam ligados ao setor desativado.</>}
                </p>
                {servidores !== 0 && (
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Realocar servidores para</label>
                        <input type="search" value={filtro} onChange={(e) => setFiltro(e.target.value)} className="ds-input mb-2" placeholder="Filtrar setores…" />
                        <select value={form.data.destino_id} onChange={(e) => form.setData('destino_id', e.target.value)} className="ds-input" size={6}>
                            {lista.map(o => <option key={o.id} value={o.id}>{o.caminho}</option>)}
                        </select>
                    </div>
                )}
                {form.errors.destino_id && <p className="text-xs text-red-600">{form.errors.destino_id}</p>}
                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" variant="danger" icon="fas fa-pause" loading={form.processing}
                        disabled={servidores > 0 && ! form.data.destino_id}>Desativar</Button>
                </div>
            </form>
        </Modal>
    );
}
