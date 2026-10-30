/**
 * Elaborar ato oficial (TR 4.42.1): tipo, número (se manual), data, ementa, texto no editor
 * (ou os campos do modelo .docx do tipo) e o processo de origem.
 */
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import RichEditor from '../../../Components/RichEditor';

export default function Form({ ato = null, tipos = [], processo = null }) {
    const form = useForm({
        tipo_id: ato?.tipo_id ? String(ato.tipo_id) : (tipos[0] ? String(tipos[0].id) : ''), numero: ato?.numero ?? '',
        data_ato: ato?.data_ato || new Date().toISOString().slice(0, 10), ementa: ato?.ementa || '', conteudo: ato?.conteudo || '',
        campos: ato?.campos || {}, processo_id: processo?.id || '',
    });
    const tipo = tipos.find(t => String(t.id) === String(form.data.tipo_id));
    const enviar = (e) => { e.preventDefault(); ato ? form.put(`/atos/${ato.id}`) : form.post('/atos'); };

    if (tipos.length === 0) {
        return <AdminLayout><Head title="Novo ato" /><PageHeader title="Novo ato" /><Card><p className="text-sm text-gray-600">Não há tipo de ato que o seu setor possa elaborar. Peça a quem administra os tipos de ato.</p></Card></AdminLayout>;
    }

    return (
        <AdminLayout>
            <Head title={ato ? `Editar ${ato.identificacao}` : 'Novo ato'} />
            <PageHeader title={ato ? `Editar ${ato.identificacao}` : 'Novo ato oficial'} subtitle="Salva como rascunho; o número automático é dado ao gerar o PDF/A" />
            <form onSubmit={enviar} className="space-y-4 max-w-5xl">
                <Card>
                    {processo && <p className="mb-3 text-sm text-gray-600"><i className="fas fa-link mr-1 text-gray-400" />Processo {processo.numero_protocolo} — {processo.assunto}</p>}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <label className="block text-sm text-gray-700">Tipo<select value={form.data.tipo_id} onChange={(e) => form.setData('tipo_id', e.target.value)} className="ds-input mt-1" disabled={!! ato}>
                            {tipos.map(t => <option key={t.id} value={t.id}>{t.nome}</option>)}</select></label>
                        {tipo?.numeracao === 'manual' ? (
                            <label className="block text-sm text-gray-700">Número<input type="number" min="1" value={form.data.numero} onChange={(e) => form.setData('numero', e.target.value)} className="ds-input mt-1" />
                                {form.errors.numero && <span className="text-xs text-red-600">{form.errors.numero}</span>}</label>
                        ) : (
                            <div className="text-sm text-gray-500 self-end pb-2"><i className="fas fa-hashtag mr-1" />Número automático ({tipo?.numeracao === 'geral' ? 'sequência geral' : 'sequência do tipo'})</div>
                        )}
                        <label className="block text-sm text-gray-700">Data do ato<input type="date" value={form.data.data_ato} onChange={(e) => form.setData('data_ato', e.target.value)} className="ds-input mt-1" /></label>
                    </div>
                    <label className="block text-sm text-gray-700 mt-3">Ementa<textarea value={form.data.ementa} onChange={(e) => form.setData('ementa', e.target.value)} rows={2} className="ds-input !h-auto mt-1" placeholder="Dispõe sobre…" /></label>
                    {form.errors.ementa && <p className="text-xs text-red-600">{form.errors.ementa}</p>}
                </Card>
                {tipo?.modelo && tipo.extras.length > 0 && (
                    <Card title="Campos do modelo" subtitle={`Modelo: ${tipo.modelo}`}>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            {tipo.extras.map(k => (
                                <label key={k} className="block text-sm text-gray-700">{k.replace(/_/g, ' ')}
                                    <input value={form.data.campos[k] || ''} onChange={(e) => form.setData('campos', { ...form.data.campos, [k]: e.target.value })} className="ds-input mt-1" /></label>
                            ))}
                        </div>
                    </Card>
                )}
                <Card title="Texto do ato" subtitle={tipo?.modelo ? 'Opcional: entra no marcador ${texto} do modelo' : 'Artigos, considerandos, parágrafos…'}>
                    <RichEditor html={form.data.conteudo} onChange={(v) => form.setData('conteudo', v)} minHeight={320} placeholder="Art. 1º …" />
                    {form.errors.conteudo && <p className="mt-1 text-xs text-red-600">{form.errors.conteudo}</p>}
                </Card>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={() => window.history.back()}>Cancelar</Button>
                    <Button type="submit" icon="fas fa-save" loading={form.processing}>Salvar rascunho</Button>
                </div>
            </form>
        </AdminLayout>
    );
}