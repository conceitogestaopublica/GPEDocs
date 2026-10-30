/**
 * Emitir parecer (TR 4.39): assunto, ementa, texto no editor (com variáveis), conclusão,
 * anexos e o processo ou memorando a que ele se refere.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import RichEditor from '../../../Components/RichEditor';

export default function Create({ processo = null, memorando = null, conclusoes = {}, variaveis = [] }) {
    const errors = usePage().props.errors || {};
    const [d, setD] = useState({ assunto: processo ? `Parecer — ${processo.assunto}` : (memorando ? `Parecer — ${memorando.assunto}` : ''), ementa: '', conteudo: '', conclusao: '' });
    const [anexos, setAnexos] = useState([]);
    const [enviando, setEnviando] = useState(false);
    const campo = (k) => ({ value: d[k], onChange: (e) => setD({ ...d, [k]: e.target.value }) });

    const enviar = (e) => {
        e.preventDefault();
        const fd = new FormData();
        Object.entries(d).forEach(([k, v]) => fd.append(k, v));
        if (processo) fd.append('processo_id', processo.id);
        if (memorando) fd.append('memorando_id', memorando.id);
        anexos.forEach((a, i) => fd.append(`anexos[${i}]`, a));
        router.post('/pareceres', fd, { forceFormData: true, onStart: () => setEnviando(true), onFinish: () => setEnviando(false) });
    };

    return (
        <AdminLayout>
            <Head title="Emitir parecer" />
            <PageHeader title="Emitir parecer" subtitle="O número é dado na emissão (PAR-ano/sequência)" />
            <form onSubmit={enviar} className="space-y-4 max-w-5xl">
                <Card>
                    {(processo || memorando) && (
                        <p className="mb-3 text-sm text-gray-600"><i className="fas fa-link mr-1 text-gray-400" />Referente ao {processo ? `processo ${processo.numero_protocolo}` : `memorando ${memorando.numero}`}</p>
                    )}
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assunto <span className="text-red-500">*</span></label>
                    <input {...campo('assunto')} className="ds-input" maxLength={255} />
                    {errors.assunto && <p className="mt-1 text-xs text-red-600">{errors.assunto}</p>}
                    <label className="block text-sm font-medium text-gray-700 mb-1 mt-4">Ementa</label>
                    <textarea {...campo('ementa')} rows={2} className="ds-input !h-auto" placeholder="Resumo do entendimento em uma ou duas frases" />
                </Card>
                <Card title="Texto do parecer">
                    <RichEditor html={d.conteudo} onChange={(v) => setD((x) => ({ ...x, conteudo: v }))} minHeight={320} variaveis={variaveis}
                        marcadores={['referencia']} placeholder="Relatório, fundamentação e opinião…" />
                    {errors.conteudo && <p className="mt-1 text-xs text-red-600">{errors.conteudo}</p>}
                </Card>
                <Card title="Conclusão e anexos">
                    <div className="flex flex-wrap gap-2">
                        {Object.entries(conclusoes).map(([v, r]) => (
                            <button key={v} type="button" onClick={() => setD({ ...d, conclusao: v })}
                                className={`px-3 py-1.5 rounded-lg border text-sm ${d.conclusao === v ? 'border-cyan-600 bg-cyan-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'}`}>{r}</button>
                        ))}
                    </div>
                    {errors.conclusao && <p className="mt-1 text-xs text-red-600">{errors.conclusao}</p>}
                    <input type="file" multiple className="mt-4 block text-sm" onChange={(e) => setAnexos([...e.target.files])} />
                    {anexos.length > 0 && <p className="mt-1 text-xs text-gray-500">{anexos.length} arquivo(s) selecionado(s)</p>}
                </Card>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={() => window.history.back()}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-stamp" loading={enviando}>Emitir parecer</Button>
                </div>
            </form>
        </AdminLayout>
    );
}
