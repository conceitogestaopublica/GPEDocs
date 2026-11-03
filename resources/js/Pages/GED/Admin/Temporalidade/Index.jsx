/**
 * Tabela de temporalidade: avaliação do acervo (em guarda, transferência, recolhimento, eliminação,
 * prazo condicional) e eliminação de documentos na forma da Resolução CONARQ nº 40/2014 —
 * listagem → aprovação → edital de ciência → eliminação com termo.
 */
import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Card from '../../../../Components/Card';
import Button from '../../../../Components/Button';
import Modal from '../../../../Components/Modal';

const dia = (s) => (s ? new Date(`${s.slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR') : '—');
const URL_ITEM = { processo: (id) => `/processos/${id}`, memorando: (id) => `/memorandos/${id}`, oficio: (id) => `/oficios/${id}`,
    circular: (id) => `/circulares/${id}`, documento: (id) => `/documentos/${id}` };
const COR_STATUS = { rascunho: 'bg-gray-100 text-gray-700', aprovada: 'bg-blue-100 text-blue-800', edital: 'bg-amber-100 text-amber-800',
    eliminada: 'bg-emerald-100 text-emerald-800', cancelada: 'bg-red-100 text-red-700' };

export default function Temporalidade({ resumo, situacao, itens = [], situacoes, listagens = [], pode_eliminar = false, tem_tabela = false }) {
    const [selecionados, setSelecionados] = useState([]);
    const [criar, setCriar] = useState(false);
    const [modal, setModal] = useState(null);   // {acao, listagem}
    const chave = (i) => `${i.tipo}:${i.id}`;
    const selecionaveis = itens.filter(i => ['eliminacao', 'condicional'].includes(i.situacao) && ! i.listagem);
    const listagemForm = useForm({ itens: [], observacao: '' });

    const criarListagem = (e) => {
        e.preventDefault();
        listagemForm.transform(d => ({ ...d, itens: selecionados.map(k => { const [tipo, id] = k.split(':'); return { tipo, id: Number(id) }; }) }));
        listagemForm.post('/admin/temporalidade/listagens', { preserveScroll: true, onSuccess: () => { setCriar(false); setSelecionados([]); listagemForm.reset(); } });
    };

    return (
        <AdminLayout>
            <Head title="Temporalidade e eliminação" />
            <PageHeader title="Temporalidade e eliminação" subtitle="Avaliação do acervo pela tabela de temporalidade e eliminação de documentos (Resolução CONARQ nº 40/2014)" />

            {! tem_tabela && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <i className="fas fa-circle-info mr-1" />Nenhum código do plano tem temporalidade ainda. Preencha os prazos e a destinação em{' '}
                    <Link href="/admin/classificacao" className="font-semibold underline">Plano de Classificação</Link>.
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 md:grid-cols-5 mb-4">
                {Object.entries(resumo).map(([k, r]) => (
                    <button key={k} type="button" onClick={() => router.get('/admin/temporalidade', { situacao: k }, { preserveState: false })}
                        className={`rounded-xl border p-3 text-left ${situacao === k ? 'border-cyan-600 bg-cyan-50' : 'border-gray-200 bg-white hover:bg-gray-50'}`}>
                        <p className="text-2xl font-semibold text-gray-800">{r.total}</p>
                        <p className="text-xs text-gray-600">{r.rotulo}</p>
                    </button>
                ))}
            </div>

            <Card title={`${situacoes[situacao]} (${itens.length})`} className="mb-6" padding={false}>
                {['eliminacao', 'condicional'].includes(situacao) && selecionaveis.length > 0 && (
                    <div className="px-4 py-2 bg-gray-50 flex flex-wrap items-center gap-2 text-sm border-b border-gray-100">
                        <label className="flex items-center gap-2 text-gray-600 cursor-pointer">
                            <input type="checkbox" className="rounded border-gray-300" checked={selecionados.length === selecionaveis.length}
                                onChange={(e) => setSelecionados(e.target.checked ? selecionaveis.map(chave) : [])} />
                            {selecionados.length ? `${selecionados.length} selecionado(s)` : 'Selecionar todos'}
                        </label>
                        {selecionados.length > 0 && <Button size="sm" icon="fas fa-list-check" onClick={() => setCriar(true)}>Criar listagem de eliminação</Button>}
                        {situacao === 'condicional' && <span className="text-xs text-gray-500">Prazo por condição: inclua só o que o arquivista confirmou que cumpriu a guarda.</span>}
                    </div>
                )}
                {itens.length === 0 ? <p className="p-6 text-center text-sm text-gray-400">Nenhum item nesta situação.</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-xs text-gray-500">
                                <tr><th className="w-8" /><th className="px-3 py-2 text-left">Item</th><th className="px-3 py-2 text-left">Classificação</th>
                                    <th className="px-3 py-2 text-left">Contagem desde</th><th className="px-3 py-2 text-left">Fase corrente até</th><th className="px-3 py-2 text-left">Guarda cumprida em</th><th className="px-3 py-2 text-left">Listagem</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {itens.map(i => (
                                    <tr key={chave(i)}>
                                        <td className="pl-3">
                                            {selecionaveis.includes(i) && <input type="checkbox" className="rounded border-gray-300" checked={selecionados.includes(chave(i))}
                                                onChange={(e) => setSelecionados(s => e.target.checked ? [...s, chave(i)] : s.filter(x => x !== chave(i)))} />}
                                        </td>
                                        <td className="px-3 py-2">
                                            <span className="text-[10px] uppercase text-gray-400 mr-1">{i.tipo_rotulo}</span>
                                            <a href={URL_ITEM[i.tipo](i.id)} className="font-medium text-cyan-700 hover:underline">{i.identificacao}</a>
                                            {i.assunto && <span className="block text-xs text-gray-500 truncate max-w-md">{i.assunto}</span>}
                                        </td>
                                        <td className="px-3 py-2 text-xs"><span className="font-mono">{i.codigo}</span> {i.classificacao}{i.prazo && <span className="block text-amber-700">{i.prazo}</span>}</td>
                                        <td className="px-3 py-2 text-xs">{dia(i.data_base)}</td>
                                        <td className="px-3 py-2 text-xs">{dia(i.corrente_ate)}</td>
                                        <td className="px-3 py-2 text-xs">{dia(i.cumpre_em)}</td>
                                        <td className="px-3 py-2 text-xs">{i.listagem || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Card title={`Listagens de eliminação (${listagens.length})`}>
                {listagens.length === 0 ? <p className="text-sm text-gray-400">Nenhuma listagem ainda. Selecione itens "Para eliminação" e crie a listagem.</p> : (
                    <ul className="divide-y divide-gray-100">
                        {listagens.map(l => {
                            const liberada = l.liberada_em && new Date(l.liberada_em) <= new Date();
                            return (
                                <li key={l.id} className="py-3 flex flex-wrap items-center gap-3">
                                    <div className="flex-1 min-w-[260px]">
                                        <p className="text-sm font-semibold text-gray-800">{l.numero}
                                            <span className={`ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold ${COR_STATUS[l.status]}`}>{l.status_rotulo}</span>
                                            <span className="ml-2 text-xs font-normal text-gray-500">{l.itens} item(ns) · por {l.criador}</span>
                                        </p>
                                        <p className="text-[11px] text-gray-500">
                                            {l.edital_em && <>Edital em {dia(l.edital_em)} ({l.edital_prazo_dias} dias) · </>}
                                            {l.status === 'edital' && (liberada ? 'prazo do edital cumprido: pode eliminar' : `eliminação liberada em ${dia(l.liberada_em)}`)}
                                            {l.status === 'eliminada' && `eliminada em ${dia(l.eliminada_em)} · ${l.arquivos_apagados} arquivo(s) apagado(s)`}
                                            {l.status === 'cancelada' && `cancelada: ${l.motivo_cancelamento}`}
                                        </p>
                                    </div>
                                    <a href={`/admin/temporalidade/listagens/${l.id}/pdf/listagem`} target="_blank" rel="noreferrer" className="text-xs text-cyan-700 hover:underline"><i className="fas fa-file-pdf mr-1" />Listagem</a>
                                    {l.edital_em && <a href={`/admin/temporalidade/listagens/${l.id}/pdf/edital`} target="_blank" rel="noreferrer" className="text-xs text-cyan-700 hover:underline"><i className="fas fa-file-pdf mr-1" />Edital</a>}
                                    {l.status === 'eliminada' && <a href={`/admin/temporalidade/listagens/${l.id}/pdf/termo`} target="_blank" rel="noreferrer" className="text-xs text-cyan-700 hover:underline"><i className="fas fa-file-pdf mr-1" />Termo</a>}
                                    {pode_eliminar && l.status === 'rascunho' && <Button size="sm" icon="fas fa-check" onClick={() => router.post(`/admin/temporalidade/listagens/${l.id}/aprovar`, {}, { preserveScroll: true })}>Aprovar</Button>}
                                    {pode_eliminar && l.status === 'aprovada' && <Button size="sm" icon="fas fa-bullhorn" onClick={() => setModal({ acao: 'edital', listagem: l })}>Publicar edital</Button>}
                                    {pode_eliminar && l.status === 'edital' && <Button size="sm" variant="danger" icon="fas fa-trash" disabled={! liberada} onClick={() => setModal({ acao: 'eliminar', listagem: l })}>Eliminar</Button>}
                                    {! ['eliminada', 'cancelada'].includes(l.status) && <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => setModal({ acao: 'cancelar', listagem: l })}>cancelar</button>}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </Card>

            <Modal show={criar} onClose={() => setCriar(false)} title={`Listagem de eliminação com ${selecionados.length} item(ns)`}>
                <form onSubmit={criarListagem} className="space-y-3">
                    <p className="text-xs text-gray-600">A listagem segue para aprovação. Depois vem o edital de ciência (30 a 45 dias) e só então a eliminação, com termo.</p>
                    <textarea value={listagemForm.data.observacao} onChange={(e) => listagemForm.setData('observacao', e.target.value)} rows={3} className="ds-input" placeholder="Observação (opcional)" />
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setCriar(false)}>Cancelar</Button>
                        <Button type="submit" icon="fas fa-list-check" loading={listagemForm.processing}>Criar listagem</Button>
                    </div>
                </form>
            </Modal>
            <ModalEtapa modal={modal} fechar={() => setModal(null)} />
        </AdminLayout>
    );
}

function ModalEtapa({ modal, fechar }) {
    const form = useForm({ prazo_dias: 30, motivo: '' });
    if (! modal) return null;
    const { acao, listagem: l } = modal;
    const enviar = (e) => {
        e.preventDefault();
        form.post(`/admin/temporalidade/listagens/${l.id}/${acao}`, { preserveScroll: true, onSuccess: () => { form.reset(); fechar(); } });
    };
    const titulo = { edital: 'Publicar edital de ciência', eliminar: 'Eliminar documentos', cancelar: 'Cancelar listagem' }[acao];

    return (
        <Modal show onClose={fechar} title={`${titulo} — ${l.numero}`}>
            <form onSubmit={enviar} className="space-y-3">
                {acao === 'edital' && (
                    <>
                        <p className="text-xs text-gray-600">O edital dá ciência da eliminação e abre o prazo para manifestações. Publique-o no Diário Oficial ou no meio de costume do órgão; depois do prazo, a eliminação fica liberada.</p>
                        <label className="block text-xs text-gray-600">Prazo para manifestações (dias)
                            <input type="number" min={30} max={45} value={form.data.prazo_dias} onChange={(e) => form.setData('prazo_dias', e.target.value)} className="ds-input mt-1 !w-32" /></label>
                        {form.errors.prazo_dias && <p className="text-xs text-red-600">{form.errors.prazo_dias}</p>}
                    </>
                )}
                {acao === 'eliminar' && (
                    <p className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
                        <i className="fas fa-triangle-exclamation mr-1" />Os arquivos digitais dos {l.itens} item(ns) serão <b>apagados definitivamente</b>.
                        Ficam os metadados, a classificação e o histórico, e o termo de eliminação. Esta ação não pode ser desfeita.
                    </p>
                )}
                {acao === 'cancelar' && (
                    <textarea value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} rows={2} className="ds-input" placeholder="Motivo do cancelamento" />
                )}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={fechar}>Voltar</Button>
                    <Button type="submit" variant={acao === 'edital' ? 'primary' : 'danger'} loading={form.processing}
                        disabled={acao === 'cancelar' && ! form.data.motivo.trim()}>{titulo}</Button>
                </div>
            </form>
        </Modal>
    );
}
