/**
 * Documentos físicos recebidos no processo (TR 4.32.28): registrar o que chegou no balcão e
 * imprimir o comprovante para quem entregou.
 */
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

export default function RecebimentosFisicos({ processoId, recebimentos = [], podeRegistrar = false, className = '' }) {
    const [aberto, setAberto] = useState(false);
    const form = useForm({ descricao: '', folhas: '', entregue_por_nome: '', entregue_por_documento: '' });
    if (! recebimentos.length && ! podeRegistrar) return null;

    const salvar = (e) => {
        e.preventDefault();
        form.post(`/processos/${processoId}/recebimentos`, { preserveScroll: true, onSuccess: () => { setAberto(false); form.reset(); } });
    };

    return (
        <Card title="Documentos físicos recebidos" className={className}>
            {recebimentos.length === 0 && <p className="text-sm text-gray-400 mb-3">Nenhum documento físico registrado.</p>}
            <ul className="divide-y divide-gray-100 mb-3">
                {recebimentos.map((r) => (
                    <li key={r.id} className="py-2 flex items-start gap-3">
                        <i className="fas fa-file-import text-gray-400 mt-1" />
                        <div className="flex-1 min-w-0">
                            <p className="text-sm text-gray-800">{r.descricao}{r.folhas ? <span className="text-gray-500"> · {r.folhas} folha(s)</span> : null}</p>
                            <p className="text-[11px] text-gray-500">Entregue por {r.entregue_por_nome} · recebido por {r.servidor} em {new Date(r.em).toLocaleString('pt-BR')} · {r.codigo}</p>
                        </div>
                        <a href={r.comprovante_url} target="_blank" rel="noreferrer" className="text-xs text-cyan-700 hover:underline whitespace-nowrap"><i className="fas fa-print mr-1" />Comprovante</a>
                    </li>
                ))}
            </ul>
            {podeRegistrar && <Button size="sm" variant="secondary" icon="fas fa-file-import" onClick={() => setAberto(true)}>Registrar documento físico recebido</Button>}

            <Modal show={aberto} onClose={() => setAberto(false)} title="Documento físico recebido">
                <form onSubmit={salvar} className="space-y-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">O que foi recebido</label>
                        <textarea value={form.data.descricao} onChange={(e) => form.setData('descricao', e.target.value)} rows={3} className="ds-input" placeholder="Ex.: Planta baixa original assinada pelo engenheiro" />
                        {form.errors.descricao && <p className="mt-1 text-xs text-red-600">{form.errors.descricao}</p>}
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Folhas</label>
                            <input type="number" min={1} value={form.data.folhas} onChange={(e) => form.setData('folhas', e.target.value)} className="ds-input" />
                        </div>
                        <div className="col-span-2">
                            <label className="block text-xs font-medium text-gray-600 mb-1">Entregue por</label>
                            <input value={form.data.entregue_por_nome} onChange={(e) => form.setData('entregue_por_nome', e.target.value)} className="ds-input" />
                            {form.errors.entregue_por_nome && <p className="mt-1 text-xs text-red-600">{form.errors.entregue_por_nome}</p>}
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Documento de quem entregou (opcional)</label>
                        <input value={form.data.entregue_por_documento} onChange={(e) => form.setData('entregue_por_documento', e.target.value)} className="ds-input" placeholder="CPF ou RG" />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setAberto(false)}>Cancelar</Button>
                        <Button type="submit" icon="fas fa-check" loading={form.processing}>Registrar</Button>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}
