/**
 * Parecer emitido (TR 4.39): texto, conclusão, anexos, vínculo com processo/memorando,
 * PDF com QR e envio para assinatura.
 */
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import { COR_CONCLUSAO } from './Index';

export default function Show({ parecer: p, pode_assinar }) {
    return (
        <AdminLayout>
            <Head title={`Parecer ${p.numero}`} />
            <PageHeader title={`Parecer ${p.numero}`} subtitle={p.assunto}>
                <a href={`/pareceres/${p.id}/pdf`} target="_blank" rel="noreferrer"><Button variant="secondary" icon="fas fa-file-pdf">PDF</Button></a>
                {pode_assinar && <Button icon="fas fa-signature" onClick={() => router.post(`/pareceres/${p.id}/assinar`)}>Assinar</Button>}
                {p.documento_id && <Link href={`/documentos/${p.documento_id}`}><Button variant="secondary" icon="fas fa-file-signature">Documento assinado</Button></Link>}
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="lg:col-span-2 space-y-4">
                    {p.ementa && <Card title="Ementa"><p className="text-sm text-gray-700 italic">{p.ementa}</p></Card>}
                    <Card title="Parecer"><div className="texto-rico" dangerouslySetInnerHTML={{ __html: p.conteudo_html }} /></Card>
                </div>
                <div className="space-y-4">
                    <Card title="Dados">
                        <dl className="text-sm space-y-2">
                            <div><dt className="text-xs text-gray-500">Conclusão</dt><dd><span className={`text-xs rounded-full px-2.5 py-0.5 ${COR_CONCLUSAO[p.conclusao]}`}>{p.conclusao_rotulo}</span></dd></div>
                            <div><dt className="text-xs text-gray-500">Parecerista</dt><dd>{p.parecerista}{p.setor ? ` — ${p.setor}` : ''}</dd></div>
                            <div><dt className="text-xs text-gray-500">Emitido em</dt><dd>{p.emitido_em ? new Date(p.emitido_em).toLocaleString('pt-BR') : ''}</dd></div>
                            {p.processo && <div><dt className="text-xs text-gray-500">Processo</dt><dd><Link href={`/processos/${p.processo.id}`} className="text-cyan-700 hover:underline">{p.processo.numero}</Link></dd></div>}
                            {p.memorando && <div><dt className="text-xs text-gray-500">Memorando</dt><dd><Link href={`/memorandos/${p.memorando.id}`} className="text-cyan-700 hover:underline">{p.memorando.numero}</Link></dd></div>}
                            <div><dt className="text-xs text-gray-500">Verificação</dt><dd className="break-all text-xs"><a href={p.verificar_url} target="_blank" rel="noreferrer" className="text-cyan-700 hover:underline">{p.verificar_url}</a></dd></div>
                        </dl>
                    </Card>
                    <Card title={`Anexos (${p.anexos.length})`}>
                        {p.anexos.length === 0 ? <p className="text-sm text-gray-400">Nenhum anexo.</p> : (
                            <ul className="space-y-1 text-sm">
                                {p.anexos.map(a => <li key={a.id}><a href={`/pareceres/${p.id}/anexos/${a.id}`} className="text-cyan-700 hover:underline"><i className="fas fa-paperclip mr-1" />{a.nome}</a></li>)}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
