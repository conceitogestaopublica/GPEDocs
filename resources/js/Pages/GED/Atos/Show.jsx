/**
 * Ato oficial (TR 4.42): texto, PDF/A com QR, assinatura (servidores do tipo + externos),
 * publicação e revogação.
 */
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';
import { COR_STATUS } from './Index';

const EXT = { nome: '', cpf: '', email: '', telefone: '' };

export default function Show({ ato: a, pode = {}, signatarios_padrao = [], usuarios = [] }) {
    const [modal, setModal] = useState(false);
    const [servidores, setServidores] = useState(signatarios_padrao.map(s => s.id));
    const [externos, setExternos] = useState([]);
    const [gerando, setGerando] = useState(false);
    const ext = (i, k, v) => setExternos(externos.map((e, j) => (j === i ? { ...e, [k]: v } : e)));

    return (
        <AdminLayout>
            <Head title={a.identificacao} />
            <PageHeader title={a.identificacao} subtitle={a.ementa}>
                <span className={`text-xs rounded-full px-2.5 py-1 ${COR_STATUS[a.status]}`}>{a.status_rotulo}</span>
                {pode.editar && <Link href={`/atos/${a.id}/edit`}><Button variant="secondary" icon="fas fa-pen">Editar</Button></Link>}
                {pode.gerar && <Button variant="secondary" icon="fas fa-file-pdf" loading={gerando}
                    onClick={() => router.post(`/atos/${a.id}/gerar`, {}, { preserveScroll: true, onStart: () => setGerando(true), onFinish: () => setGerando(false) })}>{a.tem_pdf ? 'Gerar de novo' : 'Gerar PDF/A'}</Button>}
                {a.tem_pdf && <a href={`/atos/${a.id}/pdf`} target="_blank" rel="noreferrer"><Button variant="secondary" icon="fas fa-eye">Ver PDF</Button></a>}
                {pode.assinar && <Button icon="fas fa-signature" onClick={() => setModal(true)}>Enviar para assinatura</Button>}
                {pode.publicar && <Button icon="fas fa-bullhorn" onClick={() => router.post(`/atos/${a.id}/publicar`, {}, { preserveScroll: true })}>Publicar</Button>}
                {pode.revogar && <Button variant="danger" icon="fas fa-ban" onClick={() => router.post(`/atos/${a.id}/revogar`, {}, { preserveScroll: true })}>Revogar</Button>}
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="lg:col-span-2 space-y-4">
                    {a.conteudo ? <Card title="Texto"><div className="texto-rico" dangerouslySetInnerHTML={{ __html: a.conteudo }} /></Card>
                        : <Card title="Texto"><p className="text-sm text-gray-500">O texto vem do modelo .docx do tipo ({a.modelo}).</p></Card>}
                    {Object.keys(a.campos).length > 0 && (
                        <Card title="Campos do modelo"><dl className="grid grid-cols-2 gap-2 text-sm">{Object.entries(a.campos).map(([k, v]) => <div key={k}><dt className="text-xs text-gray-500">{k.replace(/_/g, ' ')}</dt><dd>{v}</dd></div>)}</dl></Card>
                    )}
                </div>
                <div className="space-y-4">
                    <Card title="Dados">
                        <dl className="text-sm space-y-2">
                            <div><dt className="text-xs text-gray-500">Data do ato</dt><dd>{new Date(`${a.data_ato}T12:00:00`).toLocaleDateString('pt-BR')}</dd></div>
                            <div><dt className="text-xs text-gray-500">Elaborado por</dt><dd>{a.autor}</dd></div>
                            {a.processo && <div><dt className="text-xs text-gray-500">Processo</dt><dd><Link href={`/processos/${a.processo.id}`} className="text-cyan-700 hover:underline">{a.processo.numero}</Link></dd></div>}
                            <div><dt className="text-xs text-gray-500">PDF</dt><dd>{a.tem_pdf ? (a.pdfa ? <span className="text-emerald-700"><i className="fas fa-check mr-1" />PDF/A-2b</span> : 'PDF') : 'Ainda não gerado'}</dd></div>
                            {a.publicado_em && <div><dt className="text-xs text-gray-500">Publicado em</dt><dd>{new Date(a.publicado_em).toLocaleString('pt-BR')}</dd></div>}
                            <div><dt className="text-xs text-gray-500">Verificação (QR)</dt><dd className="break-all text-xs"><a href={a.verificar_url} target="_blank" rel="noreferrer" className="text-cyan-700 hover:underline">{a.verificar_url}</a></dd></div>
                        </dl>
                    </Card>
                    {a.assinatura && (
                        <Card title="Assinaturas">
                            <ul className="text-sm space-y-1">
                                {a.assinatura.assinaturas.map((s, i) => (
                                    <li key={i} className="flex items-center gap-2"><i className={`fas ${s.status === 'assinado' ? 'fa-circle-check text-emerald-600' : 'fa-clock text-amber-500'}`} />{s.nome}
                                        <span className="ml-auto text-xs text-gray-500">{s.status === 'assinado' ? new Date(s.em).toLocaleDateString('pt-BR') : 'pendente'}</span></li>
                                ))}
                            </ul>
                            {a.documento_id && <Link href={`/documentos/${a.documento_id}`} className="mt-2 inline-block text-xs text-cyan-700 hover:underline">Abrir no GED</Link>}
                        </Card>
                    )}
                </div>
            </div>

            <Modal show={modal} onClose={() => setModal(false)} title="Enviar para assinatura" maxWidth="2xl">
                <div className="space-y-4">
                    <div>
                        <p className="text-sm font-medium text-gray-700 mb-1">Servidores</p>
                        <div className="max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                            {usuarios.map(u => (
                                <label key={u.id} className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={servidores.includes(u.id)} onChange={(e) => setServidores(e.target.checked ? [...servidores, u.id] : servidores.filter(s => s !== u.id))} /> {u.name}
                                    {signatarios_padrao.some(s => s.id === u.id) && <span className="text-[10px] rounded-full bg-cyan-100 text-cyan-800 px-1.5">padrão do tipo</span>}
                                </label>
                            ))}
                        </div>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-700 mb-1">Coassinantes externos <span className="text-xs text-gray-400">(recebem link por e-mail)</span></p>
                        {externos.map((e, i) => (
                            <div key={i} className="grid grid-cols-4 gap-2 mb-1">
                                <input value={e.nome} onChange={(v) => ext(i, 'nome', v.target.value)} className="ds-input" placeholder="Nome" />
                                <input value={e.cpf} onChange={(v) => ext(i, 'cpf', v.target.value)} className="ds-input" placeholder="CPF" />
                                <input value={e.email} onChange={(v) => ext(i, 'email', v.target.value)} className="ds-input" placeholder="E-mail" />
                                <div className="flex gap-1"><input value={e.telefone} onChange={(v) => ext(i, 'telefone', v.target.value)} className="ds-input" placeholder="Telefone" />
                                    <Button type="button" variant="secondary" icon="fas fa-trash" onClick={() => setExternos(externos.filter((_, j) => j !== i))} /></div>
                            </div>
                        ))}
                        <Button size="sm" variant="secondary" icon="fas fa-plus" onClick={() => setExternos([...externos, { ...EXT }])}>Externo</Button>
                    </div>
                    <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setModal(false)}>Cancelar</Button>
                        <Button icon="fas fa-paper-plane" onClick={() => router.post(`/atos/${a.id}/assinar`, { servidores, externos }, { preserveScroll: true, onSuccess: () => setModal(false) })}>Enviar</Button></div>
                </div>
            </Modal>
        </AdminLayout>
    );
}