/**
 * Central de ajuda (TR 4.26.3): artigos do manual por assunto, com busca. Os mesmos artigos aparecem no
 * autoatendimento da aba Suporte do chat.
 */
import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';

export default function Ajuda({ assuntos, artigos, artigo: inicial }) {
    const [busca, setBusca] = useState('');
    const [aberto, setAberto] = useState(inicial);
    const sem = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const achados = artigos.filter((a) => ! busca || sem(`${a.titulo} ${a.resumo} ${a.conteudo.replace(/<[^>]+>/g, ' ')}`).includes(sem(busca)));
    const atual = artigos.find((a) => a.id === aberto);

    return (
        <AdminLayout>
            <Head title="Central de ajuda" />
            <PageHeader title="Central de ajuda" subtitle="Manual do sistema por assunto. Não achou? Abra o chat → aba Suporte." />
            <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar no manual" className="ds-input mb-4 max-w-xl" />
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div className="space-y-4">
                    {assuntos.map((s) => {
                        const doAssunto = achados.filter((a) => a.assunto_id === s.id);
                        if (! doAssunto.length) return null;
                        return (
                            <Card key={s.id} title={s.nome}>
                                <ul className="space-y-1 text-sm">{doAssunto.map((a) => (
                                    <li key={a.id}><button type="button" onClick={() => setAberto(a.id)} className={`text-left hover:underline ${aberto === a.id ? 'font-semibold text-cyan-800' : 'text-cyan-700'}`}>{a.titulo}</button></li>
                                ))}</ul>
                            </Card>
                        );
                    })}
                    {! achados.length && <p className="text-sm text-gray-500">Nada encontrado.</p>}
                </div>
                <Card className="lg:col-span-2" title={atual?.titulo || 'Escolha um artigo'}>
                    {atual ? (
                        <>
                            {atual.resumo && <p className="mb-3 text-sm text-gray-600">{atual.resumo}</p>}
                            {/* conteúdo saneado no servidor ao salvar (HtmlSeguro::limpar) */}
                            <div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: atual.conteudo }} />
                        </>
                    ) : <p className="text-sm text-gray-500">Selecione um artigo à esquerda.</p>}
                </Card>
            </div>
        </AdminLayout>
    );
}
