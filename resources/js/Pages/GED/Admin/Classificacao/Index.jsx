/**
 * Plano de classificação (TR 4.27): quatro níveis com código único, suspender sem tirar
 * dos itens já classificados, classificação automática por módulo e por assunto (o
 * assunto vence), importação CSV conferida antes de gravar e classificação retroativa.
 */
import { useMemo, useRef, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import PageHeader from '../../../../Components/PageHeader';
import Card from '../../../../Components/Card';
import Button from '../../../../Components/Button';
import { useConfirm } from '../../../../Components/ConfirmProvider';

const NIVEIS = ['Classe', 'Subclasse', 'Grupo', 'Subgrupo'];

export default function Classificacao({ classificacoes = [], modulos = [], assuntos = {}, pendentes = {}, conferencia = null }) {
    const confirmar = useConfirm();
    const [editando, setEditando] = useState(null);
    const vazio = { codigo: '', descricao: '', observacao: '', parent_id: '' };
    const form = useForm(vazio);
    const arquivo = useRef(null);

    // Árvore em ordem: filhas logo abaixo da mãe
    const linhas = useMemo(() => {
        const porPai = {};
        classificacoes.forEach(c => { (porPai[c.parent_id ?? 0] ||= []).push(c); });
        const out = [];
        const andar = (pai) => (porPai[pai] || []).forEach(c => { out.push(c); andar(c.id); });
        andar(0);
        return out;
    }, [classificacoes]);
    const ativas = linhas.filter(c => c.ativo);
    const rotulo = (c) => `${'  '.repeat(c.nivel - 1)}${c.codigo} — ${c.descricao}`;

    const editar = (c) => { setEditando(c.id); form.setData({ codigo: c.codigo, descricao: c.descricao, observacao: c.observacao || '', parent_id: c.parent_id ? String(c.parent_id) : '' }); window.scrollTo({ top: 0, behavior: 'smooth' }); };
    const limpar = () => { setEditando(null); form.setData(vazio); form.clearErrors(); };
    const salvar = (e) => {
        e.preventDefault();
        const op = { preserveScroll: true, onSuccess: limpar };
        editando ? form.put(`/admin/classificacao/${editando}`, op) : form.post('/admin/classificacao', op);
    };

    const padroes = useForm({
        modulos: Object.fromEntries(modulos.map(m => [m.modulo, m.classificacao_id ? String(m.classificacao_id) : ''])),
        assuntos: {
            processo: Object.fromEntries((assuntos.processo || []).map(a => [a.id, a.classificacao_id ? String(a.classificacao_id) : ''])),
            documento: Object.fromEntries((assuntos.documento || []).map(a => [a.id, a.classificacao_id ? String(a.classificacao_id) : ''])),
        },
    });
    const setPadrao = (grupo, chave, valor) => padroes.setData(d => grupo === 'modulos'
        ? { ...d, modulos: { ...d.modulos, [chave]: valor } }
        : { ...d, assuntos: { ...d.assuntos, [grupo]: { ...d.assuntos[grupo], [chave]: valor } } });

    const conferir = (e) => {
        const f = e.target.files?.[0];
        if (f) router.post('/admin/classificacao/conferir', { arquivo: f }, { forceFormData: true, preserveScroll: true });
        e.target.value = '';
    };

    const Seletor = ({ valor, onChange }) => (
        <select value={valor} onChange={(e) => onChange(e.target.value)} className="ds-input">
            <option value="">— Sem classificação automática —</option>
            {ativas.map(c => <option key={c.id} value={c.id}>{rotulo(c)}</option>)}
        </select>
    );
    const totalPendente = Object.values(pendentes).reduce((a, b) => a + b, 0);

    return (
        <AdminLayout>
            <Head title="Plano de classificação" />
            <PageHeader title="Plano de classificação" subtitle="Até quatro níveis, com código único. Processos, memorandos, ofícios, circulares e documentos recebem a classificação automaticamente.">
                <div className="flex gap-2">
                    <Button variant="secondary" icon="fas fa-file-export" href="/admin/classificacao/exportar">Exportar CSV</Button>
                    <Button variant="secondary" icon="fas fa-file-import" onClick={() => arquivo.current?.click()}>Importar CSV</Button>
                    <input ref={arquivo} type="file" accept=".csv,text/csv" className="hidden" onChange={conferir} />
                </div>
            </PageHeader>

            {conferencia && (
                <Card title="Conferência da importação" className="mb-6">
                    <p className="text-sm text-gray-700 mb-3">
                        <b>{conferencia.novos}</b> código(s) novo(s), <b>{conferencia.atualiza}</b> a atualizar
                        {conferencia.erros > 0 && <>, <b className="text-red-700">{conferencia.erros} com erro</b></>}. Nada foi gravado ainda.
                    </p>
                    <div className="max-h-80 overflow-y-auto border border-gray-100 rounded">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-xs text-gray-500 sticky top-0">
                                <tr><th className="px-3 py-2 text-left">Linha</th><th className="px-3 py-2 text-left">Código</th><th className="px-3 py-2 text-left">Descrição</th><th className="px-3 py-2 text-left">Superior</th><th className="px-3 py-2 text-left">Nível</th><th className="px-3 py-2 text-left">Situação</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {conferencia.itens.map(i => (
                                    <tr key={i.linha} className={i.erro ? 'bg-red-50' : ''}>
                                        <td className="px-3 py-1.5 text-gray-400">{i.linha}</td>
                                        <td className="px-3 py-1.5 font-mono">{i.codigo}</td>
                                        <td className="px-3 py-1.5">{i.descricao}</td>
                                        <td className="px-3 py-1.5 font-mono text-gray-500">{i.superior || '—'}</td>
                                        <td className="px-3 py-1.5">{i.nivel}</td>
                                        <td className="px-3 py-1.5">{i.erro ? <span className="text-red-700">{i.erro}</span> : (i.acao === 'novo' ? 'Novo' : 'Atualiza')}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex gap-2 mt-3">
                        <Button variant="primary" disabled={conferencia.erros > 0} onClick={() => router.post('/admin/classificacao/importar', { token: conferencia.token }, { preserveScroll: true })}>Gravar no plano</Button>
                        <Button variant="secondary" onClick={() => router.get('/admin/classificacao')}>Cancelar</Button>
                    </div>
                    {conferencia.erros > 0 && <p className="mt-2 text-xs text-red-700">Corrija as linhas com erro e envie o arquivo de novo.</p>}
                </Card>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card title={editando ? 'Editar classificação' : 'Nova classificação'}>
                    <form onSubmit={salvar} className="space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Abaixo de <span className="text-gray-400 text-xs">(vazio = 1º nível)</span></label>
                            <select value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value)} className="ds-input">
                                <option value="">— 1º nível (classe) —</option>
                                {ativas.filter(c => c.nivel < 4 && c.id !== editando).map(c => <option key={c.id} value={c.id}>{rotulo(c)}</option>)}
                            </select>
                            {form.errors.parent_id && <p className="mt-1 text-xs text-red-600">{form.errors.parent_id}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Código</label>
                            <input value={form.data.codigo} onChange={(e) => form.setData('codigo', e.target.value)} maxLength={30} className="ds-input font-mono" placeholder="Ex.: 01.02" />
                            {form.errors.codigo && <p className="mt-1 text-xs text-red-600">{form.errors.codigo}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <input value={form.data.descricao} onChange={(e) => form.setData('descricao', e.target.value)} maxLength={255} className="ds-input" />
                            {form.errors.descricao && <p className="mt-1 text-xs text-red-600">{form.errors.descricao}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Observação <span className="text-gray-400 text-xs">(opcional)</span></label>
                            <textarea value={form.data.observacao} onChange={(e) => form.setData('observacao', e.target.value)} rows={2} className="ds-input" />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" variant="primary" loading={form.processing} disabled={! form.data.codigo.trim() || ! form.data.descricao.trim()}>{editando ? 'Salvar' : 'Criar'}</Button>
                            {editando && <Button type="button" variant="secondary" onClick={limpar}>Cancelar</Button>}
                        </div>
                        <p className="text-[11px] text-gray-500">CSV para importar: <span className="font-mono">codigo;descricao;codigo_superior</span>, uma linha por código. Antes de gravar, o sistema mostra a conferência.</p>
                    </form>
                </Card>

                <Card title={`Plano (${classificacoes.length} código(s))`} className="lg:col-span-2">
                    {linhas.length === 0 ? <p className="text-sm text-gray-400">Nenhuma classificação cadastrada. Crie a primeira ou importe um CSV.</p> : (
                        <ul className="divide-y divide-gray-100">
                            {linhas.map(c => (
                                <li key={c.id} className="py-2 flex items-center justify-between gap-3" style={{ paddingLeft: (c.nivel - 1) * 20 }}>
                                    <span className={`text-sm ${c.ativo ? 'text-gray-800' : 'text-gray-400 line-through'}`}>
                                        {c.nivel > 1 && <span className="text-gray-300 mr-1">↳</span>}
                                        <span className="font-mono text-xs mr-2">{c.codigo}</span>{c.descricao}
                                        <span className="ml-2 text-[10px] uppercase text-gray-400">{NIVEIS[c.nivel - 1]}</span>
                                        {! c.ativo && <span className="ml-2 text-[10px] font-semibold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800 no-underline">Suspensa</span>}
                                        <span className="ml-2 text-xs text-gray-400">{c.uso} item(ns)</span>
                                    </span>
                                    <span className="flex items-center gap-3 text-xs shrink-0">
                                        <button type="button" className="text-blue-700 hover:underline" onClick={() => editar(c)}>Editar</button>
                                        <button type="button" className="text-amber-700 hover:underline" onClick={() => router.post(`/admin/classificacao/${c.id}/alternar`, {}, { preserveScroll: true })}>{c.ativo ? 'Suspender' : 'Reativar'}</button>
                                        <button type="button" className="text-red-600 hover:underline"
                                            onClick={async () => (await confirmar({ titulo: `Excluir ${c.codigo}?`, descricao: 'Só dá para excluir código sem uso e sem classificações abaixo. Em uso, suspenda.', tom: 'atencao', rotuloConfirmar: 'Excluir' }))
                                                && router.delete(`/admin/classificacao/${c.id}`, { preserveScroll: true })}>Excluir</button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <Card title="Classificação automática" className="mt-6">
                <p className="text-sm text-gray-600 mb-4">Ao criar um item, vale a classificação do <b>assunto</b>; se o assunto não tiver, a do <b>módulo</b>.</p>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="space-y-3">
                        <h3 className="text-xs font-semibold uppercase text-gray-500">Por módulo</h3>
                        {modulos.map(m => (
                            <div key={m.modulo}>
                                <label className="block text-sm font-medium text-gray-700 mb-1">{m.rotulo}</label>
                                <Seletor valor={padroes.data.modulos[m.modulo]} onChange={(v) => setPadrao('modulos', m.modulo, v)} />
                            </div>
                        ))}
                    </div>
                    {[['processo', 'Por assunto — tipos de processo'], ['documento', 'Por assunto — tipos documentais']].map(([k, titulo]) => (
                        <div key={k} className="space-y-3">
                            <h3 className="text-xs font-semibold uppercase text-gray-500">{titulo}</h3>
                            {(assuntos[k] || []).length === 0 && <p className="text-sm text-gray-400">Nenhum tipo ativo.</p>}
                            <div className="space-y-3 max-h-96 overflow-y-auto pr-1">
                                {(assuntos[k] || []).map(a => (
                                    <div key={a.id}>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">{a.nome}</label>
                                        <Seletor valor={padroes.data.assuntos[k][a.id]} onChange={(v) => setPadrao(k, a.id, v)} />
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
                <div className="mt-4">
                    <Button variant="primary" loading={padroes.processing} onClick={() => padroes.post('/admin/classificacao/padroes', { preserveScroll: true })}>Salvar classificação automática</Button>
                </div>
            </Card>

            <Card title="Itens antigos sem classificação" className="mt-6">
                <div className="flex flex-wrap gap-4 text-sm text-gray-700 mb-3">
                    {modulos.map(m => <span key={m.modulo}>{m.rotulo}: <b>{pendentes[m.modulo] ?? 0}</b></span>)}
                </div>
                <Button variant="secondary" icon="fas fa-wand-magic-sparkles" disabled={totalPendente === 0}
                    onClick={async () => (await confirmar({ titulo: 'Classificar os itens antigos?', descricao: 'Cada item sem classificação recebe a do assunto ou do módulo, como se tivesse sido criado agora. Fica registrado no histórico como automática.', rotuloConfirmar: 'Classificar' }))
                        && router.post('/admin/classificacao/retroativa', {}, { preserveScroll: true })}>Classificar os antigos</Button>
            </Card>
        </AdminLayout>
    );
}
