/**
 * App da fiscalização (TR 4.52): formulários de checklist (campos configuráveis, objetos fiscalizados,
 * itens com resposta padrão) que o celular do fiscal baixa para trabalhar offline, e as vistorias
 * que já chegaram sincronizadas, com o relatório em PDF.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Card from '../../../Components/Card';
import Modal from '../../../Components/Modal';

const VAZIO = { nome: '', descricao: '', ativo: true, objetos: '', campos: [], itens: '' };
const dataHora = (iso) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

/** Itens no formulário de edição: uma linha por item; "# Grupo" abre um grupo. */
const itensParaTexto = (itens) => {
    let grupo = null; const linhas = [];
    for (const i of itens) {
        if ((i.grupo || null) !== grupo) { grupo = i.grupo || null; if (grupo) linhas.push(`# ${grupo}`); }
        linhas.push(i.texto);
    }
    return linhas.join('\n');
};
const textoParaItens = (texto, antigos) => {
    let grupo = null; const out = [];
    for (const l of texto.split('\n').map((x) => x.trim()).filter(Boolean)) {
        if (l.startsWith('#')) { grupo = l.replace(/^#+/, '').trim() || null; continue; }
        // mantém a chave do item que já existia com o mesmo texto: as vistorias antigas continuam batendo
        out.push({ chave: antigos.find((a) => a.texto === l)?.chave, grupo, texto: l });
    }
    return out;
};

export default function Index({ formularios = [], vistorias = [], tiposCampo = {} }) {
    const errors = usePage().props.errors || {};
    const [form, setForm] = useState(null);
    const [filtro, setFiltro] = useState('');

    const abrir = (f) => setForm(f ? { ...f, objetos: f.objetos.join('\n'), itens: itensParaTexto(f.itens), antigos: f.itens } : { ...VAZIO, antigos: [] });
    const salvar = () => {
        const { id, antigos, vistorias: _, ...d } = form;
        router.post(id ? `/configuracoes/fiscalizacao/formularios/${id}` : '/configuracoes/fiscalizacao/formularios',
            { ...d, objetos: d.objetos.split('\n').map((x) => x.trim()).filter(Boolean), itens: textoParaItens(d.itens, antigos) },
            { preserveScroll: true, onSuccess: () => setForm(null) });
    };
    const campo = (i, k, v) => setForm((f) => ({ ...f, campos: f.campos.map((c, j) => (j === i ? { ...c, [k]: v } : c)) }));
    const lista = vistorias.filter((v) => ! filtro || `${v.formulario} ${v.objeto || ''} ${v.local || ''} ${v.fiscal} ${v.processo || ''}`.toLowerCase().includes(filtro.toLowerCase()));

    return (
        <AdminLayout>
            <Head title="App da fiscalização" />
            <PageHeader title="App da fiscalização" subtitle="Formulários de checklist do celular do fiscal e vistorias sincronizadas">
                <a href="/fiscal" target="_blank" rel="noreferrer"><Button variant="secondary" icon="fas fa-mobile-screen">Abrir o app</Button></a>
            </PageHeader>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card title={`Formulários (${formularios.length})`}>
                    <ul className="divide-y divide-gray-100 text-sm">
                        {formularios.map((f) => (
                            <li key={f.id} className={`flex flex-wrap items-center gap-2 py-2 ${f.ativo ? '' : 'opacity-50'}`}>
                                <div className="min-w-0 flex-1"><b>{f.nome}</b>
                                    <p className="text-xs text-gray-500">{f.itens.length} item(ns) · {f.campos.length} campo(s) · {f.objetos.length ? `${f.objetos.length} objeto(s)` : 'objeto livre'} · {f.vistorias} vistoria(s){f.ativo ? '' : ' · inativo'}</p></div>
                                <button type="button" onClick={() => abrir(f)} className="text-xs text-cyan-700 hover:underline">Editar</button>
                                <button type="button" onClick={() => window.confirm(f.vistorias ? 'O formulário tem vistorias e será desativado. Continuar?' : 'Excluir o formulário?') && router.delete(`/configuracoes/fiscalizacao/formularios/${f.id}`, { preserveScroll: true })}
                                    className="text-xs text-red-600 hover:underline">{f.vistorias ? 'Desativar' : 'Excluir'}</button>
                            </li>
                        ))}
                        {! formularios.length && <li className="py-2 text-gray-500">Nenhum formulário. Crie o primeiro checklist.</li>}
                    </ul>
                    <Button size="sm" className="mt-2" icon="fas fa-plus" onClick={() => abrir(null)}>Formulário</Button>
                    <div className="mt-4 rounded-lg bg-cyan-50 p-3 text-xs text-cyan-900">
                        <p className="font-semibold">Como o fiscal usa</p>
                        <p>No celular, abra <b>{window.location.origin}/fiscal</b> com o login do GED e escolha “Adicionar à tela inicial”. Depois disso o app abre sem internet; as vistorias ficam no aparelho até sincronizar.</p>
                    </div>
                </Card>

                <Card title={`Vistorias sincronizadas (${vistorias.length})`} className="xl:col-span-2">
                    <input value={filtro} onChange={(e) => setFiltro(e.target.value)} placeholder="Filtrar por formulário, objeto, local, fiscal ou processo" className="ds-input mb-2" />
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="border-b text-left text-xs text-gray-500"><th className="py-2">Realizada</th><th>Formulário / objeto</th><th>Fiscal</th><th>Processo</th><th>Resultado</th><th /></tr></thead>
                            <tbody>
                                {lista.map((v) => (
                                    <tr key={v.id} className="border-b border-gray-100 align-top">
                                        <td className="whitespace-nowrap py-2">{dataHora(v.realizada_em)}</td>
                                        <td><b>{v.formulario}</b><p className="text-xs text-gray-500">{[v.objeto, v.local].filter(Boolean).join(' — ')}</p>
                                            <p className="text-xs text-gray-400">{v.fotos} foto(s){v.gps ? ' · GPS' : ''}{v.nao_atende ? ` · ${v.nao_atende} não atende` : ''}</p></td>
                                        <td>{v.fiscal}</td>
                                        <td>{v.processo_id ? <a href={`/processos/${v.processo_id}`} className="text-cyan-700 hover:underline">{v.processo}</a> : '—'}</td>
                                        <td className="text-xs">{v.resultado || '—'}</td>
                                        <td><a href={v.pdf_url} target="_blank" rel="noreferrer" className="whitespace-nowrap text-xs text-cyan-700 hover:underline">PDF</a></td>
                                    </tr>
                                ))}
                                {! lista.length && <tr><td colSpan={6} className="py-3 text-gray-500">Nenhuma vistoria sincronizada.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <Modal show={!! form} onClose={() => setForm(null)} title={form?.id ? `Formulário: ${form.nome}` : 'Novo formulário'} maxWidth="4xl">
                {form && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <label className="text-sm text-gray-700 md:col-span-2">Nome<input value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} className="ds-input mt-1" placeholder="Ex.: Vistoria de obra" />
                                {errors.nome && <span className="text-xs text-red-600">{errors.nome}</span>}</label>
                            <label className="mt-6 flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={form.ativo} onChange={(e) => setForm({ ...form, ativo: e.target.checked })} /> Ativo (aparece no app)</label>
                        </div>
                        <label className="block text-sm text-gray-700">Descrição / orientação ao fiscal<textarea value={form.descricao || ''} onChange={(e) => setForm({ ...form, descricao: e.target.value })} rows={2} className="ds-input !h-auto mt-1" /></label>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label className="block text-sm text-gray-700">Itens do checklist (um por linha; <code># Nome</code> abre um grupo)
                                <textarea value={form.itens} onChange={(e) => setForm({ ...form, itens: e.target.value })} rows={12} className="ds-input !h-auto mt-1 font-mono text-xs"
                                    placeholder={'# Documentação\nAlvará de construção afixado na obra\nProjeto aprovado disponível\n# Segurança\nTapume na frente do lote\nCalçada desobstruída'} />
                                <span className="text-xs text-gray-500">Cada item é respondido no app com Atende / Não atende / Não se aplica.</span>
                                {errors.itens && <span className="block text-xs text-red-600">{errors.itens}</span>}</label>
                            <label className="block text-sm text-gray-700">Objetos fiscalizados (um por linha; vazio = o fiscal digita)
                                <textarea value={form.objetos} onChange={(e) => setForm({ ...form, objetos: e.target.value })} rows={12} className="ds-input !h-auto mt-1 text-xs"
                                    placeholder={'Obra residencial\nObra comercial\nDemolição\nComércio ambulante'} /></label>
                        </div>
                        <div>
                            <p className="mb-1 text-sm font-medium text-gray-700">Campos do formulário</p>
                            {form.campos.map((c, i) => (
                                <div key={i} className="mb-2 grid grid-cols-12 items-start gap-2">
                                    <input value={c.rotulo} onChange={(e) => campo(i, 'rotulo', e.target.value)} placeholder="Rótulo (ex.: Nº do alvará)" className="ds-input col-span-12 md:col-span-4" />
                                    <select value={c.tipo} onChange={(e) => campo(i, 'tipo', e.target.value)} className="ds-input col-span-6 md:col-span-2">
                                        {Object.entries(tiposCampo).map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                                    </select>
                                    <input value={(c.opcoes || []).join('; ')} onChange={(e) => campo(i, 'opcoes', e.target.value.split(';').map((x) => x.trim()))} disabled={c.tipo !== 'lista'}
                                        placeholder={c.tipo === 'lista' ? 'Opções separadas por ;' : '—'} className="ds-input col-span-6 md:col-span-3 disabled:bg-gray-50" />
                                    <label className="col-span-8 mt-2 flex items-center gap-1 text-xs text-gray-600 md:col-span-2"><input type="checkbox" checked={!! c.obrigatorio} onChange={(e) => campo(i, 'obrigatorio', e.target.checked)} /> Obrigatório</label>
                                    <button type="button" onClick={() => setForm((f) => ({ ...f, campos: f.campos.filter((_, j) => j !== i) }))} className="col-span-4 mt-2 text-right text-xs text-red-600 md:col-span-1">Remover</button>
                                </div>
                            ))}
                            <Button size="sm" variant="secondary" icon="fas fa-plus" onClick={() => setForm((f) => ({ ...f, campos: [...f.campos, { rotulo: '', tipo: 'texto', opcoes: [], obrigatorio: false }] }))}>Campo</Button>
                        </div>
                        {Object.keys(errors).some((k) => k.startsWith('campos')) && <p className="text-xs text-red-600">Confira os campos: todo campo precisa de rótulo.</p>}
                        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setForm(null)}>Cancelar</Button><Button icon="fas fa-save" onClick={salvar}>Salvar</Button></div>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
