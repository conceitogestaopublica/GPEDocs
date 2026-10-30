/**
 * Tramita GOV.BR no ofício ou no processo (TR 4.28.5): envia a outro ente (busca da estrutura de destino no
 * barramento) e lista os trâmites com NRE, IDT, data/hora, situação e o comprovante.
 */
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const COR = { enviado: 'bg-amber-100 text-amber-800', concluido: 'bg-emerald-100 text-emerald-700', recusado: 'bg-red-100 text-red-700',
    erro: 'bg-red-100 text-red-700', aguardando_aceite: 'bg-amber-100 text-amber-800', recebido: 'bg-emerald-100 text-emerald-700' };
const dt = (s) => (s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' }) : '—');

export default function TramitaDoDocumento({ dados, url, rotulo = 'documento', className = '' }) {
    const [aberto, setAberto] = useState(false);
    const [busca, setBusca] = useState('');
    const [lista, setLista] = useState([]);
    const [erro, setErro] = useState('');
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        if (! aberto) return undefined;
        const t = setTimeout(() => fetch(`/tramita/estruturas?q=${encodeURIComponent(busca)}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json()).then((j) => (Array.isArray(j) ? (setLista(j), setErro('')) : setErro(j.erro || 'Erro ao consultar o barramento.'))).catch(() => setErro('Erro ao consultar o barramento.')), 300);
        return () => clearTimeout(t);
    }, [busca, aberto]);

    if (! dados || (! dados.ativo && ! dados.tramites.length)) return null;
    const enviar = (e) => {
        if (! window.confirm(`Enviar este ${rotulo}, com os arquivos, para "${e.nome}" pelo Tramita GOV.BR?`)) return;
        setEnviando(true);
        router.post(url, e, { preserveScroll: true, onFinish: () => setEnviando(false), onSuccess: () => setAberto(false) });
    };

    return (
        <Card title="Tramita GOV.BR" className={className}>
            {dados.tramites.length > 0 ? (
                <ul className="mb-3 divide-y divide-gray-100 text-sm">
                    {dados.tramites.map((t) => (
                        <li key={t.id} className="py-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${COR[t.status] || 'bg-gray-100'}`}>{t.status_rotulo}</span>
                                <span className="text-gray-700">{t.sentido === 'enviado' ? 'para' : 'de'} <b>{t.outro_nome}</b></span>
                                <a href={`/tramita/${t.id}/comprovante`} target="_blank" rel="noreferrer" className="ml-auto text-xs text-cyan-700 hover:underline"><i className="fas fa-file-pdf mr-1" />Comprovante</a>
                            </div>
                            <p className="mt-0.5 text-xs text-gray-500">NRE <b className="text-gray-700">{t.nre || '—'}</b> · IDT <b className="text-gray-700">{t.idt || '—'}</b> · enviado em {dt(t.enviado_em)}{t.concluido_em ? ` · ${t.status === 'recusado' ? 'recusado' : 'recibo'} em ${dt(t.concluido_em)}` : ''}</p>
                            {t.recusa && <p className="text-xs text-red-700">Recusa: {t.recusa}</p>}
                        </li>
                    ))}
                </ul>
            ) : <p className="mb-3 text-sm text-gray-500">Ainda não enviado a outro ente.</p>}
            {dados.ativo && <Button size="sm" variant="secondary" icon="fas fa-share-from-square" onClick={() => setAberto(true)}>Enviar a outro ente</Button>}

            <Modal show={aberto} onClose={() => setAberto(false)} title={`Enviar ${rotulo} pelo Tramita GOV.BR`}>
                <p className="mb-2 text-xs text-gray-500">Busque o órgão ou a unidade de destino. Vão junto o PDF e os anexos; o recibo com NRE e IDT fica registrado aqui.</p>
                <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Nome do órgão ou unidade" className="ds-input" autoFocus />
                {erro && <p className="mt-2 text-xs text-red-600">{erro}</p>}
                <ul className="mt-2 max-h-72 divide-y divide-gray-100 overflow-auto">
                    {lista.map((e) => (
                        <li key={`${e.repositorio}-${e.estrutura}`}>
                            <button type="button" disabled={enviando} onClick={() => enviar(e)} className="w-full px-2 py-2 text-left text-sm hover:bg-gray-50 disabled:opacity-50">
                                {e.nome}<span className="block text-[11px] text-gray-500">repositório {e.repositorio} · estrutura {e.estrutura}</span>
                            </button>
                        </li>
                    ))}
                    {! lista.length && ! erro && <li className="py-2 text-xs text-gray-400">Nenhuma estrutura encontrada.</li>}
                </ul>
            </Modal>
        </Card>
    );
}
