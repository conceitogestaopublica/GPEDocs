/**
 * Tramita GOV.BR (TR 4.28): o que o ente enviou (NRE, IDT, data/hora, recibo ou recusa) e o que recebeu de
 * outros entes (aceitar → abre o processo e devolve o recibo; recusar com motivo e justificativa).
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';
import Modal from '../../Components/Modal';

const COR = { enviado: 'bg-amber-100 text-amber-800', concluido: 'bg-emerald-100 text-emerald-700', recusado: 'bg-red-100 text-red-700',
    erro: 'bg-red-100 text-red-700', aguardando_aceite: 'bg-amber-100 text-amber-800', recebido: 'bg-emerald-100 text-emerald-700' };
const dt = (s) => (s ? new Date(s).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—');

export default function Index({ sentido, tramites, contagem, aguardando, config, motivos, podeConfigurar }) {
    const [recusa, setRecusa] = useState(null);
    const ir = (s) => router.get('/tramita', { sentido: s }, { preserveState: false });

    return (
        <AdminLayout>
            <Head title="Tramita GOV.BR" />
            <PageHeader title="Tramita GOV.BR" subtitle={config.estrutura_nome ? `Ente: ${config.estrutura_nome}` : 'Ofícios e processos com outros entes públicos'}>
                <span className="text-xs text-gray-500">{config.adaptador === 'simulador' ? 'Simulador (homologação interna)' : 'Barramento real'} · sincronizado {dt(config.sincronizado_em)}</span>
                <Button size="sm" variant="secondary" icon="fas fa-rotate" onClick={() => router.post('/tramita/sincronizar', {}, { preserveScroll: true })} disabled={! config.ativo}>Sincronizar</Button>
                {podeConfigurar && <Link href="/tramita/configuracao"><Button size="sm" variant="secondary" icon="fas fa-sliders">Configuração</Button></Link>}
            </PageHeader>
            {! config.ativo && <Card className="mb-4"><p className="text-sm text-amber-800">O Tramita GOV.BR está desativado. {podeConfigurar ? 'Ative em Configuração (o simulador funciona sem a adesão).' : 'Peça ao administrador para configurar.'}</p></Card>}

            <div className="mb-3 flex gap-1 border-b border-gray-200">
                {[['enviado', 'Enviados', 'fa-paper-plane'], ['recebido', 'Recebidos', 'fa-inbox']].map(([k, r, ic]) => (
                    <button key={k} type="button" onClick={() => ir(k)} className={`-mb-px border-b-2 px-4 py-2 text-sm ${sentido === k ? 'border-cyan-700 font-semibold text-cyan-800' : 'border-transparent text-gray-500'}`}>
                        <i className={`fas ${ic} mr-1.5`} />{r} ({contagem[k] || 0}){k === 'recebido' && aguardando > 0 && <span className="ml-1.5 rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{aguardando}</span>}
                    </button>
                ))}
            </div>

            <Card>
                <ul className="divide-y divide-gray-100">
                    {tramites.map((t) => (
                        <li key={t.id} className="py-3 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${COR[t.status] || 'bg-gray-100'}`}>{t.status_rotulo}</span>
                                <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{t.tipo === 'oficio' ? 'Ofício' : 'Processo'}</span>
                                <b className="text-gray-800">{t.assunto}</b>
                                <a href={`/tramita/${t.id}/comprovante`} target="_blank" rel="noreferrer" className="ml-auto text-xs text-cyan-700 hover:underline"><i className="fas fa-file-pdf mr-1" />Comprovante</a>
                            </div>
                            <p className="mt-0.5 text-xs text-gray-600">{t.sentido === 'enviado' ? 'Para' : 'De'} <b>{t.outro_nome}</b> · NRE <b>{t.nre || '—'}</b> · IDT <b>{t.idt || '—'}</b>
                                · {t.sentido === 'enviado' ? 'enviado' : 'chegou'} em {dt(t.enviado_em)}{t.concluido_em ? ` · ${t.status === 'recusado' ? 'recusado' : 'concluído'} em ${dt(t.concluido_em)}` : ''}{t.usuario ? ` · ${t.usuario}` : ''}</p>
                            {(t.processo_id || t.oficio_id) && <p className="text-xs">{t.processo_id && <Link href={`/processos/${t.processo_id}`} className="text-cyan-700 hover:underline">Processo {t.processo}</Link>}
                                {t.oficio_id && <Link href={`/oficios/${t.oficio_id}`} className="text-cyan-700 hover:underline">Ofício {t.oficio}</Link>}</p>}
                            {t.recusa && <p className="text-xs text-red-700">Recusa: {t.recusa}{t.recusa_justificativa ? ` — ${t.recusa_justificativa}` : ''}</p>}
                            {t.sentido === 'recebido' && t.componentes.length > 0 && (
                                <p className="mt-1 flex flex-wrap gap-2 text-xs">{t.componentes.map((c) => (
                                    <a key={c.indice} href={`/tramita/${t.id}/componentes/${c.indice}`} target="_blank" rel="noreferrer" className={`rounded border px-2 py-0.5 ${c.hash_ok ? 'border-gray-200 text-gray-700' : 'border-red-300 text-red-700'}`}>
                                        <i className="fas fa-paperclip mr-1" />{c.nome}{! c.hash_ok && ' (hash não confere)'}</a>))}</p>
                            )}
                            {t.status === 'aguardando_aceite' && (
                                <div className="mt-2 flex gap-2">
                                    <Button size="sm" icon="fas fa-check" onClick={() => router.post(`/tramita/${t.id}/aceitar`, {}, { preserveScroll: true })}>Aceitar e abrir processo</Button>
                                    <Button size="sm" variant="secondary" icon="fas fa-ban" onClick={() => setRecusa({ id: t.id, motivo: 'destino_incorreto', justificativa: '' })}>Recusar</Button>
                                </div>
                            )}
                        </li>
                    ))}
                    {! tramites.length && <li className="py-6 text-center text-sm text-gray-400">{sentido === 'enviado' ? 'Nada enviado ainda. Envie pela tela do ofício ou do processo (bloco Tramita GOV.BR).' : 'Nada recebido ainda.'}</li>}
                </ul>
            </Card>

            <Modal show={!! recusa} onClose={() => setRecusa(null)} title="Recusar trâmite">
                {recusa && (
                    <div className="space-y-3">
                        <label className="block text-sm text-gray-700">Motivo<select value={recusa.motivo} onChange={(e) => setRecusa({ ...recusa, motivo: e.target.value })} className="ds-input mt-1">
                            {Object.entries(motivos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}</select></label>
                        <label className="block text-sm text-gray-700">Justificativa (vai ao remetente)<textarea value={recusa.justificativa} onChange={(e) => setRecusa({ ...recusa, justificativa: e.target.value })} rows={3} className="ds-input !h-auto mt-1" /></label>
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setRecusa(null)}>Cancelar</Button>
                            <Button variant="danger" disabled={! recusa.justificativa.trim()} onClick={() => router.post(`/tramita/${recusa.id}/recusar`, recusa, { preserveScroll: true, onSuccess: () => setRecusa(null) })}>Recusar</Button></div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
