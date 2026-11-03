/**
 * Processo parado ou ligado a outro: aviso de sobrestado / apensado / anexado, as ações (sobrestar,
 * retomar, apensar ou anexar outro processo, desapensar) e a relação de apensos e anexados.
 */
import { useEffect, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import Card from './Card';
import Button from './Button';
import Modal from './Modal';

const dia = (s) => (s ? new Date(`${s}T12:00:00`).toLocaleDateString('pt-BR') : '');
const quando = (s) => (s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '');

export default function VinculosDoProcesso({ processo, dados, className = '' }) {
    const [modal, setModal] = useState(null);   // sobrestar | retomar | vincular | {desapensar: vinculo}
    if (! dados) return null;
    const { parado, sobrestamento, apensado_a, anexado_a, vinculos = [], pode = {} } = dados;
    const apensos = vinculos.filter((v) => v.tipo === 'apenso' && ! v.desapensado_em);
    const historicos = vinculos.filter((v) => v.tipo === 'anexo' || v.desapensado_em);
    const temAcao = pode.sobrestar || pode.vincular;
    if (! parado && ! vinculos.length && ! temAcao) return null;

    return (
        <div className={className}>
            {parado === 'sobrestado' && (
                <div className="mb-3 rounded-xl border border-amber-300 bg-amber-50 p-3 flex flex-wrap items-start gap-3">
                    <i className="fas fa-circle-pause text-amber-600 text-lg mt-0.5" />
                    <div className="flex-1 min-w-0 text-sm text-amber-900">
                        <p className="font-semibold">Processo sobrestado desde {quando(sobrestamento.desde)}{sobrestamento.por ? ` por ${sobrestamento.por}` : ''}</p>
                        <p>{sobrestamento.motivo}</p>
                        <p className="text-xs mt-0.5">
                            {sobrestamento.ate ? `Retoma automaticamente em ${dia(sobrestamento.ate)}.` : ''}
                            {sobrestamento.condicao ? ` Retomar quando: ${sobrestamento.condicao}.` : ''}
                            {' '}Enquanto isso ninguém tramita e os prazos não correm.
                        </p>
                    </div>
                    {pode.retomar && <Button size="sm" icon="fas fa-play" onClick={() => setModal('retomar')}>Retomar</Button>}
                </div>
            )}
            {apensado_a && (
                <div className="mb-3 rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900">
                    <i className="fas fa-paperclip mr-2" />
                    {parado === 'apensado' ? 'Apensado ao processo ' : 'Foi apensado ao processo '}
                    <Link href={`/processos/${apensado_a.id}`} className="font-semibold underline">{apensado_a.numero_protocolo}</Link>
                    {parado === 'apensado' ? ': tramita junto com ele até ser desapensado.' : '.'}
                </div>
            )}
            {anexado_a && (
                <div className="mb-3 rounded-xl border border-slate-300 bg-slate-50 p-3 text-sm text-slate-800">
                    <i className="fas fa-file-circle-plus mr-2" />Anexado em definitivo ao processo{' '}
                    <Link href={`/processos/${anexado_a.id}`} className="font-semibold underline">{anexado_a.numero_protocolo}</Link>: as peças passaram a integrá-lo.
                </div>
            )}

            {(vinculos.length > 0 || temAcao) && (
                <Card title="Processos vinculados e sobrestamento">
                    {apensos.length > 0 && (
                        <div className="mb-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Apensos (tramitam junto com este)</p>
                            <ul className="divide-y divide-gray-100">
                                {apensos.map((v) => (
                                    <li key={v.id} className="py-1.5 flex items-center gap-2 text-sm">
                                        <Link href={`/processos/${v.processo_id}`} className="font-semibold text-cyan-700 hover:underline">{v.numero_protocolo}</Link>
                                        <span className="text-gray-600 truncate flex-1">{v.assunto}</span>
                                        <span className="text-[11px] text-gray-400">{quando(v.created_at)}{v.por ? ` · ${v.por}` : ''}</span>
                                        {pode.desapensar && <button type="button" onClick={() => setModal({ desapensar: v })} className="text-xs text-red-600 hover:underline">desapensar</button>}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {historicos.length > 0 && (
                        <div className="mb-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Anexados e desapensados</p>
                            <ul className="divide-y divide-gray-100">
                                {historicos.map((v) => (
                                    <li key={v.id} className="py-1.5 text-sm">
                                        <Link href={`/processos/${v.processo_id}`} className="font-semibold text-cyan-700 hover:underline">{v.numero_protocolo}</Link>
                                        <span className="ml-2 text-[11px] rounded-full px-1.5 py-0.5 bg-gray-100 text-gray-600">{v.tipo === 'anexo' ? 'anexado' : `desapensado em ${quando(v.desapensado_em)}`}</span>
                                        <span className="block text-xs text-gray-500">{v.tipo === 'anexo' ? v.motivo : v.motivo_desapensacao}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {! vinculos.length && <p className="text-sm text-gray-400 mb-3">Nenhum processo apensado ou anexado.</p>}
                    <div className="flex flex-wrap gap-2">
                        {pode.vincular && <Button size="sm" variant="secondary" icon="fas fa-paperclip" onClick={() => setModal('vincular')}>Apensar ou anexar processo</Button>}
                        {pode.sobrestar && <Button size="sm" variant="secondary" icon="fas fa-circle-pause" onClick={() => setModal('sobrestar')}>Sobrestar</Button>}
                    </div>
                </Card>
            )}

            <ModalSobrestar processo={processo} aberto={modal === 'sobrestar'} fechar={() => setModal(null)} />
            <ModalRetomar processo={processo} aberto={modal === 'retomar'} fechar={() => setModal(null)} />
            <ModalVincular processo={processo} aberto={modal === 'vincular'} fechar={() => setModal(null)} />
            <ModalDesapensar processo={processo} vinculo={modal?.desapensar} fechar={() => setModal(null)} />
        </div>
    );
}

const Erro = ({ msg }) => (msg ? <p className="mt-1 text-xs text-red-600">{msg}</p> : null);

function ModalSobrestar({ processo, aberto, fechar }) {
    const form = useForm({ motivo: '', ate: '', condicao: '' });
    const salvar = (e) => { e.preventDefault(); form.post(`/processos/${processo.id}/sobrestar`, { preserveScroll: true, onSuccess: () => { form.reset(); fechar(); } }); };
    return (
        <Modal show={aberto} onClose={fechar} title={`Sobrestar ${processo.numero_protocolo}`}>
            <form onSubmit={salvar} className="space-y-3">
                <p className="text-xs text-gray-600">O processo fica parado: ninguém recebe, despacha ou decide, e os prazos não correm. Na retomada, os prazos das etapas em aberto são estendidos pelo tempo parado.</p>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Motivo</label>
                    <textarea value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} rows={3} className="ds-input" placeholder="Ex.: aguardando decisão judicial no processo nº …" />
                    <Erro msg={form.errors.motivo} />
                </div>
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Retomar automaticamente em</label>
                        <input type="date" value={form.data.ate} onChange={(e) => form.setData('ate', e.target.value)} className="ds-input" />
                        <Erro msg={form.errors.ate} />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Ou: condição para retomar</label>
                        <input value={form.data.condicao} onChange={(e) => form.setData('condicao', e.target.value)} maxLength={300} className="ds-input" placeholder="Ex.: chegada do parecer da PGM" />
                    </div>
                </div>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-circle-pause" loading={form.processing} disabled={! form.data.motivo.trim() || (! form.data.ate && ! form.data.condicao.trim())}>Sobrestar</Button>
                </div>
            </form>
        </Modal>
    );
}

function ModalRetomar({ processo, aberto, fechar }) {
    const form = useForm({ observacao: '' });
    const salvar = (e) => { e.preventDefault(); form.post(`/processos/${processo.id}/retomar`, { preserveScroll: true, onSuccess: () => { form.reset(); fechar(); } }); };
    return (
        <Modal show={aberto} onClose={fechar} title={`Retomar ${processo.numero_protocolo}`}>
            <form onSubmit={salvar} className="space-y-3">
                <p className="text-xs text-gray-600">O processo volta a tramitar e os prazos das etapas em aberto são estendidos pelo tempo em que ficou sobrestado.</p>
                <textarea value={form.data.observacao} onChange={(e) => form.setData('observacao', e.target.value)} rows={2} className="ds-input" placeholder="Observação (opcional)" />
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-play" loading={form.processing}>Retomar</Button>
                </div>
            </form>
        </Modal>
    );
}

function ModalVincular({ processo, aberto, fechar }) {
    const form = useForm({ tipo: 'apenso', processo_id: '', motivo: '' });
    const [busca, setBusca] = useState('');
    const [achados, setAchados] = useState([]);
    const [escolhido, setEscolhido] = useState(null);
    useEffect(() => {
        if (busca.trim().length < 2) { setAchados([]); return undefined; }
        const t = setTimeout(() => fetch(`/processos/${processo.id}/vinculaveis?q=${encodeURIComponent(busca)}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json()).then(setAchados).catch(() => {}), 300);
        return () => clearTimeout(t);
    }, [busca, processo.id]);
    const escolher = (p) => { setEscolhido(p); form.setData('processo_id', p.id); setBusca(''); setAchados([]); };
    const salvar = (e) => {
        e.preventDefault();
        if (form.data.tipo === 'anexo' && ! window.confirm(`Anexar ${escolhido?.numero_protocolo} é definitivo: as peças passam a este processo e ele é encerrado. Continuar?`)) return;
        form.post(`/processos/${processo.id}/vincular`, { preserveScroll: true, onSuccess: () => { form.reset(); setEscolhido(null); fechar(); } });
    };
    return (
        <Modal show={aberto} onClose={fechar} title={`Apensar ou anexar ao ${processo.numero_protocolo}`}>
            <form onSubmit={salvar} className="space-y-3">
                <div className="grid grid-cols-2 gap-2">
                    {[['apenso', 'Apensar', 'Tramita junto com este e pode ser desapensado depois.'], ['anexo', 'Anexar', 'Definitivo: as peças passam a este processo e o outro é encerrado.']].map(([v, r, d]) => (
                        <label key={v} className={`rounded-xl border-2 p-2.5 cursor-pointer ${form.data.tipo === v ? 'border-cyan-600 bg-cyan-50' : 'border-gray-200'}`}>
                            <input type="radio" className="mr-1.5" checked={form.data.tipo === v} onChange={() => form.setData('tipo', v)} />
                            <span className="text-sm font-semibold">{r}</span>
                            <span className="block text-[11px] text-gray-500">{d}</span>
                        </label>
                    ))}
                </div>
                <div className="relative">
                    <label className="block text-xs font-medium text-gray-600 mb-1">Processo</label>
                    {escolhido ? (
                        <div className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <b>{escolhido.numero_protocolo}</b><span className="truncate text-gray-600 flex-1">{escolhido.assunto}</span>
                            <button type="button" onClick={() => { setEscolhido(null); form.setData('processo_id', ''); }} className="text-xs text-gray-500">trocar</button>
                        </div>
                    ) : (
                        <input value={busca} onChange={(e) => setBusca(e.target.value)} className="ds-input" placeholder="Número ou assunto do processo" />
                    )}
                    {achados.length > 0 && (
                        <ul className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow">
                            {achados.map((p) => (
                                <li key={p.id}>
                                    <button type="button" disabled={! p.pode} onClick={() => escolher(p)} className="w-full px-3 py-1.5 text-left text-sm hover:bg-gray-50 disabled:opacity-50">
                                        <b>{p.numero_protocolo}</b> <span className="text-gray-600">{p.assunto}</span>
                                        {! p.pode && <span className="block text-[11px] text-amber-700">Você não está na etapa ativa deste processo</span>}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Erro msg={form.errors.processo_id} />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Motivo</label>
                    <textarea value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} rows={2} className="ds-input" placeholder="Ex.: mesmo requerente e mesmo objeto" />
                    <Erro msg={form.errors.motivo} />
                </div>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-paperclip" loading={form.processing} disabled={! form.data.processo_id || ! form.data.motivo.trim()}>
                        {form.data.tipo === 'apenso' ? 'Apensar' : 'Anexar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ModalDesapensar({ processo, vinculo, fechar }) {
    const form = useForm({ motivo: '' });
    const salvar = (e) => {
        e.preventDefault();
        form.post(`/processos/${processo.id}/apensos/${vinculo.processo_id}/desapensar`, { preserveScroll: true, onSuccess: () => { form.reset(); fechar(); } });
    };
    return (
        <Modal show={!! vinculo} onClose={fechar} title={`Desapensar ${vinculo?.numero_protocolo || ''}`}>
            <form onSubmit={salvar} className="space-y-3">
                <p className="text-xs text-gray-600">O processo volta a tramitar sozinho, no setor em que este processo está agora.</p>
                <textarea value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} rows={2} className="ds-input" placeholder="Motivo" />
                <Erro msg={form.errors.motivo} />
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Cancelar</Button>
                    <Button type="submit" variant="danger" loading={form.processing} disabled={! form.data.motivo.trim()}>Desapensar</Button>
                </div>
            </form>
        </Modal>
    );
}
