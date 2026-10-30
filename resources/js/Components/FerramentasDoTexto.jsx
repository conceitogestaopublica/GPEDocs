/**
 * Acima do editor (TR 4.16.11–4.16.12): modelos de texto do setor e da administração para o
 * módulo, salvar o texto atual como modelo do setor e pré-visualizar o PDF sem enviar.
 */
import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { useConfirm } from './ConfirmProvider';
import { textoParaHtml } from './RichEditor';

const temTexto = (html) => (html || '').replace(/<[^>]+>/g, '').trim().length > 0 || /<img|<table/i.test(html || '');

export default function FerramentasDoTexto({ modulo, conteudo, onAplicar, dadosPrevia = null, desabilitado = false }) {
    const confirmar = useConfirm();
    const [aberto, setAberto] = useState(false);
    const [modelos, setModelos] = useState(null);
    const [busca, setBusca] = useState('');
    const [aviso, setAviso] = useState('');
    const caixa = useRef(null);

    const carregar = () => axios.get('/modelos-texto', { params: { modulo } }).then((r) => setModelos(r.data)).catch(() => setModelos([]));

    useEffect(() => {
        if (! aberto) return undefined;
        if (modelos === null) carregar();
        const fora = (e) => { if (caixa.current && ! caixa.current.contains(e.target)) setAberto(false); };
        document.addEventListener('mousedown', fora);
        return () => document.removeEventListener('mousedown', fora);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [aberto]);

    useEffect(() => { if (aviso) { const t = setTimeout(() => setAviso(''), 4000); return () => clearTimeout(t); } return undefined; }, [aviso]);

    const aplicar = async (m) => {
        setAberto(false);
        if (temTexto(conteudo) && ! await confirmar({ titulo: `Usar o modelo "${m.nome}"?`, descricao: 'O texto já escrito será trocado pelo do modelo.', tom: 'atencao', rotuloConfirmar: 'Trocar o texto' })) return;
        onAplicar(textoParaHtml(m.conteudo));
    };

    const salvarComoModelo = async () => {
        if (! temTexto(conteudo)) { setAviso('Escreva o texto antes de salvar como modelo.'); return; }
        const nome = window.prompt('Nome do modelo (fica disponível para todo o seu setor):');
        if (! nome?.trim()) return;
        try {
            await axios.post('/modelos-texto', { nome: nome.trim(), modulo, conteudo });
            setModelos(null);
            setAviso(`Modelo "${nome.trim()}" salvo para o seu setor.`);
        } catch (e) {
            setAviso(e?.response?.data?.errors?.nome?.[0] || 'Não foi possível salvar o modelo.');
        }
    };

    const excluir = async (m) => {
        if (! await confirmar({ titulo: `Excluir o modelo "${m.nome}"?`, descricao: 'Some para todo o setor.', tom: 'perigo', rotuloConfirmar: 'Excluir' })) return;
        await axios.delete(`/modelos-texto/${m.id}`).then(() => setModelos((l) => l.filter((x) => x.id !== m.id))).catch(() => setAviso('Não foi possível excluir.'));
    };

    // Prévia: formulário em aba nova (o PDF abre no visualizador do navegador, sem bloqueio de pop-up)
    const previa = () => {
        const form = document.createElement('form');
        Object.assign(form, { method: 'POST', action: '/editor/previa', target: '_blank' });
        const campos = { _token: document.querySelector('meta[name=csrf-token]')?.content || '', modulo, dados: JSON.stringify(dadosPrevia()) };
        Object.entries(campos).forEach(([nome, valor]) => {
            const i = document.createElement('input');
            Object.assign(i, { type: 'hidden', name: nome, value: valor });
            form.appendChild(i);
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    const filtrados = (modelos || []).filter((m) => ! busca || `${m.nome} ${m.categoria || ''}`.toLowerCase().includes(busca.toLowerCase()));
    const doSetor = filtrados.filter((m) => m.do_setor);
    const daAdm = filtrados.filter((m) => ! m.do_setor);

    return (
        <div className="relative flex flex-wrap items-center gap-2" ref={caixa}>
            <button type="button" disabled={desabilitado} onClick={() => setAberto(! aberto)}
                className="text-xs px-2.5 py-1 rounded-md border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                <i className="fas fa-file-lines mr-1 text-gray-400" />Modelos de texto <i className="fas fa-caret-down ml-1" />
            </button>
            <button type="button" disabled={desabilitado} onClick={salvarComoModelo}
                className="text-xs px-2.5 py-1 rounded-md border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                <i className="fas fa-floppy-disk mr-1 text-gray-400" />Salvar como modelo do setor
            </button>
            {dadosPrevia && (
                <button type="button" onClick={previa}
                    className="text-xs px-2.5 py-1 rounded-md border border-cyan-200 bg-cyan-50 text-cyan-800 hover:bg-cyan-100">
                    <i className="fas fa-file-pdf mr-1" />Prévia em PDF
                </button>
            )}
            {aviso && <span className="text-[11px] text-gray-600">{aviso}</span>}

            {aberto && (
                <div className="absolute right-0 top-full mt-1 z-30 w-96 max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg p-2">
                    <input type="search" value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar modelo…" className="ds-input mb-2" autoFocus />
                    {modelos === null && <p className="text-xs text-gray-400 px-2 py-3">Carregando…</p>}
                    {modelos && filtrados.length === 0 && <p className="text-xs text-gray-400 px-2 py-3">Nenhum modelo. Use "Salvar como modelo do setor" para criar um a partir do texto.</p>}
                    {[['Do meu setor', doSetor], ['Da administração', daAdm]].map(([titulo, lista]) => lista.length > 0 && (
                        <div key={titulo} className="mb-1">
                            <p className="px-2 pt-1 text-[10px] font-semibold uppercase text-gray-400">{titulo}</p>
                            {lista.map((m) => (
                                <div key={m.id} className="flex items-center gap-1 rounded hover:bg-cyan-50">
                                    <button type="button" onClick={() => aplicar(m)} className="flex-1 text-left px-2 py-1.5">
                                        <span className="text-sm text-gray-800">{m.categoria ? `[${m.categoria}] ` : ''}{m.nome}</span>
                                        {(m.descricao || m.setor) && <span className="block text-[11px] text-gray-500 truncate">{m.descricao || m.setor}</span>}
                                    </button>
                                    {m.do_setor && (
                                        <button type="button" onClick={() => excluir(m)} title="Excluir modelo do setor" className="px-2 text-xs text-gray-400 hover:text-red-600">
                                            <i className="fas fa-trash-can" />
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
