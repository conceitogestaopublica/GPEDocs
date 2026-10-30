/**
 * Edição do Diário Oficial (TR 4.43.16, 4.43.23–4.43.30): sumário por caderno/seção com
 * reordenação, inclusão dos atos pendentes, data, exclusão e a publicação irreversível.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';
import { TIPO, dataBR } from './Painel';

export default function Edicao({ edicao: e, sumario = [], disponiveis = [], estrutura = [], pode_publicar }) {
    const pendente = e.status === 'pendente';
    const [ordem, setOrdem] = useState(sumario.map(s => s.id));
    const [confirma, setConfirma] = useState(false);
    const [publicando, setPublicando] = useState(false);
    const porId = Object.fromEntries(sumario.map(s => [s.id, s]));
    const mover = (i, d) => { const n = [...ordem]; [n[i], n[i + d]] = [n[i + d], n[i]]; setOrdem(n); };
    const mudou = ordem.join() !== sumario.map(s => s.id).join();
    const put = (dados) => router.put(`/diario/edicoes/${e.id}`, dados, { preserveScroll: true });
    let grupoAnterior = null;

    return (
        <AdminLayout>
            <Head title={e.titulo} />
            <PageHeader title={e.titulo} subtitle={pendente ? 'Edição pendente: monte o sumário e publique' : `Publicada em ${new Date(e.publicada_em).toLocaleString('pt-BR')} por ${e.publicada_por}`}>
                {pendente && <Button variant="danger" icon="fas fa-trash" onClick={() => router.delete(`/diario/edicoes/${e.id}`)}>Excluir</Button>}
                {! pendente && <a href={`/diario/edicoes/${e.id}/pdf`} target="_blank" rel="noreferrer"><Button variant="secondary" icon="fas fa-file-pdf">Edição certificada</Button></a>}
                {pode_publicar && <Button icon="fas fa-bullhorn" disabled={sumario.length === 0 || mudou} onClick={() => setConfirma(true)}>Publicar</Button>}
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="lg:col-span-2">
                    <Card title={`Sumário (${sumario.length} ato(s))`}>
                        {pendente && sumario.length > 0 && (
                            <div className="flex gap-2 mb-3">
                                <Button size="sm" variant="secondary" icon="fas fa-sitemap" onClick={() => put({ reordenar_estrutura: true })}>Ordenar por caderno e seção</Button>
                                {mudou && <Button size="sm" icon="fas fa-save" onClick={() => put({ ordem })}>Salvar ordem</Button>}
                            </div>
                        )}
                        {ordem.length === 0 ? <p className="text-sm text-gray-400">Nenhum ato nesta edição.</p> : (
                            <ol className="text-sm">
                                {ordem.map((id, i) => {
                                    const s = porId[id];
                                    const titulo = s.grupo !== grupoAnterior ? s.grupo : null;
                                    grupoAnterior = s.grupo;
                                    return (
                                        <li key={id}>
                                            {titulo && <p className="mt-3 mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{titulo}</p>}
                                            <div className="flex items-start gap-2 py-1.5 border-b border-gray-50">
                                                <span className="text-xs text-gray-400 w-6 text-right">{i + 1}.</span>
                                                <div className="flex-1"><Link href={`/atos/${s.ato.id}`} className="text-cyan-700 hover:underline">{s.ato.identificacao}</Link>
                                                    <span className="block text-xs text-gray-500">{s.ato.ementa}</span></div>
                                                {s.pagina && <span className="text-xs text-gray-500">pág. {s.pagina}</span>}
                                                {pendente && (
                                                    <span className="flex gap-1 text-gray-500">
                                                        <button type="button" disabled={i === 0} onClick={() => mover(i, -1)} className="disabled:opacity-30"><i className="fas fa-arrow-up" /></button>
                                                        <button type="button" disabled={i === ordem.length - 1} onClick={() => mover(i, 1)} className="disabled:opacity-30"><i className="fas fa-arrow-down" /></button>
                                                        <button type="button" title="Tirar desta edição" onClick={() => router.delete(`/diario/materias/${id}`, { preserveScroll: true })} className="text-red-500 ml-1"><i className="fas fa-xmark" /></button>
                                                    </span>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </Card>
                </div>
                <div className="space-y-4">
                    <Card title="Dados">
                        <dl className="text-sm space-y-2">
                            <div><dt className="text-xs text-gray-500">Tipo</dt><dd>{TIPO[e.tipo]}{e.automatica ? ' (montada automaticamente)' : ''}</dd></div>
                            <div><dt className="text-xs text-gray-500">Data</dt><dd>{pendente ? <input type="date" defaultValue={e.data} onBlur={(ev) => ev.target.value !== e.data && put({ data: ev.target.value })} className="ds-input !h-8" /> : dataBR(e.data)}</dd></div>
                            {! pendente && <>
                                <div><dt className="text-xs text-gray-500">Páginas</dt><dd>{e.paginas}</dd></div>
                                <div><dt className="text-xs text-gray-500">Assinatura</dt><dd>{e.assinada ? <span className="text-emerald-700"><i className="fas fa-certificate mr-1" />{e.certificado}</span> : 'Sem certificado da prefeitura'}</dd></div>
                                <div><dt className="text-xs text-gray-500">Hash SHA-256</dt><dd className="break-all text-[11px] text-gray-600">{e.hash}</dd></div>
                                <div><dt className="text-xs text-gray-500">Verificação</dt><dd className="break-all text-xs"><a href={e.verificar_url} target="_blank" rel="noreferrer" className="text-cyan-700 hover:underline">{e.verificar_url}</a></dd></div>
                            </>}
                        </dl>
                    </Card>
                    {pendente && (
                        <Card title={`Atos sem edição (${disponiveis.length})`}>
                            {disponiveis.length === 0 ? <p className="text-sm text-gray-400">Nenhum.</p> : (
                                <>
                                    <ul className="text-sm space-y-1">{disponiveis.map(d => <li key={d.id}>{d.identificacao}<span className="block text-xs text-gray-500">{d.grupo}</span></li>)}</ul>
                                    <Button size="sm" className="mt-3" icon="fas fa-plus" onClick={() => put({ incluir: disponiveis.map(d => d.id) })}>Incluir todos nesta edição</Button>
                                </>
                            )}
                        </Card>
                    )}
                </div>
            </div>

            <Modal show={confirma} onClose={() => setConfirma(false)} title="Publicar a edição">
                <p className="text-sm text-gray-700">A publicação é <strong>irreversível</strong>: a edição recebe o número, o PDF é gerado e assinado com o certificado da prefeitura e fica disponível na área pública. Depois disso nada nela pode mudar.</p>
                <div className="mt-4 flex justify-end gap-2"><Button variant="secondary" onClick={() => setConfirma(false)}>Cancelar</Button>
                    <Button icon="fas fa-bullhorn" loading={publicando} onClick={() => router.post(`/diario/edicoes/${e.id}/publicar`, {}, { onStart: () => setPublicando(true), onFinish: () => { setPublicando(false); setConfirma(false); } })}>Publicar agora</Button></div>
            </Modal>
        </AdminLayout>
    );
}