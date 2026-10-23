/**
 * Solicitar assinatura de vários documentos de uma vez — servidores e externos. Com
 * externos, o lote vira um pacote: um link e um e-mail por pessoa para todos os documentos.
 * Usado no Repositório e na lista de Documentos.
 */
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import Button from './Button';
import Modal from './Modal';

export default function SolicitarAssinaturaLoteModal({ show, onClose, documentoIds, usuarios, onSuccess }) {
    const { data, setData, post, processing, reset, errors } = useForm({
        documento_ids: [],
        signatarios: [],
        externos: [],
        titulo_pacote: '',
        mensagem: '',
        prazo: '',
        sequencial: false,
    });
    const novoExterno = () => setData('externos', [...data.externos, { nome: '', cpf: '', email: '', telefone: '' }]);
    const mudarExterno = (i, campo, valor) => setData('externos', data.externos.map((e, j) => j === i ? { ...e, [campo]: valor } : e));
    const removerExterno = (i) => setData('externos', data.externos.filter((_, j) => j !== i));
    const total = data.signatarios.length + data.externos.length;

    useEffect(() => {
        if (show) setData('documento_ids', documentoIds);
    }, [show, documentoIds]);

    const toggleUser = (id) => {
        const list = data.signatarios.includes(id) ? data.signatarios.filter(x => x !== id) : [...data.signatarios, id];
        setData('signatarios', list);
    };

    const submit = (e) => {
        e.preventDefault();
        post('/assinaturas/solicitar-lote', {
            onSuccess: () => { reset(); onClose(); onSuccess(); },
        });
    };

    return (
        <Modal show={show} onClose={onClose} title={`Solicitar Assinatura (${documentoIds.length} documento(s))`} maxWidth="4xl">
            <form onSubmit={submit} className="space-y-4">
                <div className="bg-blue-50 rounded-xl p-3">
                    <p className="text-xs text-blue-700">
                        <i className="fas fa-info-circle mr-1" />
                        A assinatura será solicitada para <strong>{documentoIds.length}</strong> documento(s) selecionado(s).
                    </p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Selecione os signatários</label>
                    <div className="max-h-48 overflow-y-auto border border-gray-200 rounded-xl p-2 space-y-1">
                        {usuarios.map(u => (
                            <label key={u.id} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 rounded px-2 py-1.5">
                                <input type="checkbox" checked={data.signatarios.includes(u.id)}
                                    onChange={() => toggleUser(u.id)}
                                    className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                                <span className="text-gray-700">{u.name}</span>
                                <span className="text-xs text-gray-400 ml-auto">{u.email}</span>
                            </label>
                        ))}
                        {usuarios.length === 0 && (
                            <p className="text-xs text-gray-400 text-center py-3">Nenhum usuário disponível</p>
                        )}
                    </div>
                </div>

                {/* Externos: um link e um e-mail por pessoa para todos os documentos (pacote) */}
                <div className="border border-cyan-100 bg-cyan-50/40 rounded-xl p-3 space-y-2">
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium text-gray-700">
                            Signatários externos <span className="text-xs font-normal text-gray-500">(assinam por link — só PDF)</span>
                        </p>
                        <button type="button" onClick={novoExterno} className="text-xs text-blue-700 hover:underline">
                            <i className="fas fa-user-plus mr-1" />Adicionar externo
                        </button>
                    </div>
                    {data.externos.map((ext, i) => (
                        <div key={i} className="grid grid-cols-1 md:grid-cols-[1.35fr_1fr_1.75fr_0.95fr_auto] gap-2 items-start">
                            <div>
                                <input value={ext.nome} onChange={(e) => mudarExterno(i, 'nome', e.target.value)} className="ds-input" placeholder="Nome completo" required />
                                {errors[`externos.${i}.nome`] && <p className="text-[11px] text-red-600">{errors[`externos.${i}.nome`]}</p>}
                            </div>
                            <div>
                                <input value={ext.cpf} onChange={(e) => mudarExterno(i, 'cpf', e.target.value)} className="ds-input" placeholder="CPF" required inputMode="numeric" />
                                {errors[`externos.${i}.cpf`] && <p className="text-[11px] text-red-600">{errors[`externos.${i}.cpf`]}</p>}
                            </div>
                            <input type="email" value={ext.email} onChange={(e) => mudarExterno(i, 'email', e.target.value)} className="ds-input" placeholder="E-mail (recebe o link)" />
                            <input value={ext.telefone} onChange={(e) => mudarExterno(i, 'telefone', e.target.value)} className="ds-input" placeholder="Celular" inputMode="tel" />
                            <button type="button" onClick={() => removerExterno(i)} className="ds-btn ds-btn-ghost !px-2 text-red-600" title="Remover">
                                <i className="fas fa-times" />
                            </button>
                        </div>
                    ))}
                    {data.externos.length > 0 && (
                        <>
                            <input value={data.titulo_pacote} onChange={(e) => setData('titulo_pacote', e.target.value)} className="ds-input"
                                placeholder={`Nome do pacote (ex.: Empenhos de outubro) — padrão: ${documentoIds.length} documentos enviados hoje`} maxLength={200} />
                            <p className="text-[11px] text-gray-600">
                                Cada externo recebe <strong>um único link</strong> com os {documentoIds.length} documentos e pode assinar um por um ou todos de uma vez.
                            </p>
                        </>
                    )}
                </div>
                {errors.signatarios && <p className="text-xs text-red-600">{errors.signatarios}</p>}

                <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" checked={data.sequencial} onChange={(e) => setData('sequencial', e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                    Assinar em ordem (servidores na ordem marcada, externos depois)
                </label>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Mensagem (opcional)</label>
                    <textarea value={data.mensagem} onChange={(e) => setData('mensagem', e.target.value)}
                        className="ds-input !h-auto" rows={2} placeholder="Mensagem para os signatários..." />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Prazo (opcional)</label>
                    <input type="date" value={data.prazo} onChange={(e) => setData('prazo', e.target.value)}
                        className="ds-input w-auto" />
                </div>

                <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={processing} icon="fas fa-paper-plane"
                        disabled={total === 0}>
                        Enviar para {total} signatário(s)
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

