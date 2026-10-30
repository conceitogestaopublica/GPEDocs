/**
 * Etiquetas do setor (TR 4.19.1, 4.19.3, 4.19.5, 4.19.10): cada setor cria as suas, com
 * cor da letra e do fundo e etiqueta dentro de etiqueta. Só os servidores do setor veem.
 */
import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';
import { useConfirm } from '../../../Components/ConfirmProvider';

const PALETA = [['#1f2937', '#e5e7eb'], ['#ffffff', '#0e7490'], ['#ffffff', '#b91c1c'], ['#78350f', '#fde68a'], ['#14532d', '#bbf7d0'], ['#ffffff', '#6d28d9'], ['#1e3a8a', '#bfdbfe']];

export default function Etiquetas({ etiquetas = [], setores = [] }) {
    const confirmar = useConfirm();
    const [editando, setEditando] = useState(null);
    const vazio = { unidade_id: setores[0]?.id ? String(setores[0].id) : '', parent_id: '', nome: '', cor_texto: '#1f2937', cor_fundo: '#e5e7eb' };
    const form = useForm(vazio);

    const editar = (e) => { setEditando(e.id); form.setData({ unidade_id: String(e.unidade_id), parent_id: e.parent_id ? String(e.parent_id) : '', nome: e.nome, cor_texto: e.cor_texto, cor_fundo: e.cor_fundo }); };
    const limpar = () => { setEditando(null); form.setData(vazio); form.clearErrors(); };
    const salvar = (ev) => {
        ev.preventDefault();
        const opcoes = { preserveScroll: true, onSuccess: limpar };
        editando ? form.put(`/flow/etiquetas/${editando}`, opcoes) : form.post('/flow/etiquetas', opcoes);
    };

    // Árvore por setor: mães e filhas indentadas
    const filhas = (pai, setor) => etiquetas.filter(e => e.unidade_id === setor && (e.parent_id ?? null) === pai);
    const linhas = (pai, setor, nivel = 0) => filhas(pai, setor).flatMap(e => [{ ...e, nivel }, ...linhas(e.id, setor, nivel + 1)]);
    const maesPossiveis = etiquetas.filter(e => String(e.unidade_id) === form.data.unidade_id && e.id !== editando);

    return (
        <AdminLayout>
            <Head title="Etiquetas do setor" />
            <PageHeader title="Etiquetas do setor" subtitle="Organize processos, memorandos e ofícios do seu setor. Só os servidores do setor veem as etiquetas dele." />

            {setores.length === 0 ? (
                <Card><p className="text-sm text-gray-500">Você não está em nenhum setor do organograma.</p></Card>
            ) : (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <Card title={editando ? 'Editar etiqueta' : 'Nova etiqueta'}>
                        <form onSubmit={salvar} className="space-y-3">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Setor</label>
                                <select value={form.data.unidade_id} onChange={(e) => form.setData(d => ({ ...d, unidade_id: e.target.value, parent_id: '' }))} className="ds-input">
                                    {setores.map(s => <option key={s.id} value={s.id}>{s.nome}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                                <input value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} maxLength={60} className="ds-input" placeholder="Ex.: Urgente" />
                                {form.errors.nome && <p className="mt-1 text-xs text-red-600">{form.errors.nome}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Dentro de <span className="text-gray-400 text-xs">(opcional)</span></label>
                                <select value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value)} className="ds-input">
                                    <option value="">— Nenhuma (etiqueta principal) —</option>
                                    {maesPossiveis.map(e => <option key={e.id} value={e.id}>{e.nome}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Cores</label>
                                <div className="flex flex-wrap gap-1.5 mb-2">
                                    {PALETA.map(([t, f]) => (
                                        <button key={t + f} type="button" onClick={() => form.setData(d => ({ ...d, cor_texto: t, cor_fundo: f }))}
                                            style={{ color: t, backgroundColor: f }} className="text-xs rounded-full px-2.5 py-1 border border-black/5">Aa</button>
                                    ))}
                                </div>
                                <div className="flex items-center gap-4 text-sm text-gray-600">
                                    <label className="flex items-center gap-2">Letra <input type="color" value={form.data.cor_texto} onChange={(e) => form.setData('cor_texto', e.target.value)} /></label>
                                    <label className="flex items-center gap-2">Fundo <input type="color" value={form.data.cor_fundo} onChange={(e) => form.setData('cor_fundo', e.target.value)} /></label>
                                    <span style={{ color: form.data.cor_texto, backgroundColor: form.data.cor_fundo }} className="text-xs rounded-full px-2.5 py-1 font-semibold">{form.data.nome || 'Prévia'}</span>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" variant="primary" loading={form.processing} disabled={! form.data.nome.trim()}>{editando ? 'Salvar' : 'Criar'}</Button>
                                {editando && <Button type="button" variant="secondary" onClick={limpar}>Cancelar</Button>}
                            </div>
                        </form>
                    </Card>

                    <div className="lg:col-span-2 space-y-4">
                        {setores.map(s => {
                            const lista = linhas(null, s.id);
                            return (
                                <Card key={s.id} title={s.nome}>
                                    {lista.length === 0 ? <p className="text-sm text-gray-400">Nenhuma etiqueta neste setor.</p> : (
                                        <ul className="divide-y divide-gray-100">
                                            {lista.map(e => (
                                                <li key={e.id} className="py-2 flex items-center justify-between gap-3" style={{ paddingLeft: e.nivel * 20 }}>
                                                    <span className="flex items-center gap-2">
                                                        {e.nivel > 0 && <span className="text-gray-300">↳</span>}
                                                        <span style={{ color: e.cor_texto, backgroundColor: e.cor_fundo }} className="text-xs rounded-full px-2.5 py-1 font-semibold">{e.nome}</span>
                                                        <span className="text-xs text-gray-400">{e.uso} item(ns)</span>
                                                    </span>
                                                    <span className="flex items-center gap-3 text-xs">
                                                        <a href={`/flow/inbox-setor?etiqueta=${e.id}`} className="text-cyan-700 hover:underline">Abrir gaveta</a>
                                                        <button type="button" className="text-blue-700 hover:underline" onClick={() => editar(e)}>Editar</button>
                                                        <button type="button" className="text-red-600 hover:underline"
                                                            onClick={async () => (await confirmar({ titulo: `Excluir "${e.nome}"?`, descricao: 'Sai de todos os itens. As etiquetas de dentro sobem um nível.', tom: 'atencao', rotuloConfirmar: 'Excluir' }))
                                                                && router.delete(`/flow/etiquetas/${e.id}`, { preserveScroll: true })}>Excluir</button>
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </Card>
                            );
                        })}
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
