/**
 * Configuração dos contatos: regras de preenchimento por perfil (TR 4.21.3) e até dez
 * campos complementares com nome, explicação, formato, onde é pedido e obrigatoriedade (4.21.5–9).
 */
import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Card from '../../../Components/Card';
import Button from '../../../Components/Button';

const ONDE = { interno: 'Só no cadastro interno', autocadastro: 'Só no autocadastro do portal', ambos: 'Nos dois' };

export default function Configuracao({ campos = [], regras = {}, tipos = {}, basicos = {}, formatos = {}, maximo = 10 }) {
    const vazio = { nome: '', explicacao: '', formato: 'texto', opcoes: [], onde: 'ambos', obrigatorio: false, perfis: [], ordem: 0 };
    const form = useForm(vazio);
    const [editando, setEditando] = useState(null);
    const [opcoesTexto, setOpcoesTexto] = useState('');
    const regrasForm = useForm({ regras: Object.fromEntries(Object.keys(tipos).map(t => [t, regras[t] || []])) });
    const ativos = campos.filter(c => c.ativo).length;

    const editar = (c) => {
        setEditando(c.id);
        form.setData({ nome: c.nome, explicacao: c.explicacao || '', formato: c.formato, opcoes: c.opcoes || [], onde: c.onde, obrigatorio: !! c.obrigatorio, perfis: c.perfis || [], ordem: c.ordem || 0 });
        setOpcoesTexto((c.opcoes || []).join('\n'));
    };
    const limpar = () => { setEditando(null); form.setData(vazio); setOpcoesTexto(''); form.clearErrors(); };
    const salvar = (e) => {
        e.preventDefault();
        form.transform(dados => ({ ...dados, opcoes: opcoesTexto.split('\n').map(s => s.trim()).filter(Boolean) }));
        const op = { preserveScroll: true, onSuccess: limpar };
        editando ? form.put(`/flow/contatos/campos/${editando}`, op) : form.post('/flow/contatos/campos', op);
    };
    const alternaRegra = (tipo, campo) => regrasForm.setData('regras', { ...regrasForm.data.regras,
        [tipo]: regrasForm.data.regras[tipo].includes(campo) ? regrasForm.data.regras[tipo].filter(c => c !== campo) : [...regrasForm.data.regras[tipo], campo] });
    const alternaPerfil = (t) => form.setData('perfis', form.data.perfis.includes(t) ? form.data.perfis.filter(x => x !== t) : [...form.data.perfis, t]);
    const aplicaA = (perfil) => perfil === 'empresa_id' ? ['setor_pj'] : perfil === 'cpf' ? ['pf'] : perfil === 'cnpj' ? ['pj', 'setor_pj'] : Object.keys(tipos);

    return (
        <AdminLayout>
            <Head title="Configurar contatos" />
            <PageHeader title="Configurar contatos" subtitle="O que é obrigatório em cada perfil de contato e os campos complementares do cadastro." />

            <Card title="Regras de preenchimento por perfil" className="mb-6">
                <p className="text-sm text-gray-600 mb-3">Além do nome, todo contato precisa de ao menos um entre e-mail, CPF, CNPJ ou celular. Marque o que mais é obrigatório em cada perfil.</p>
                <div className="overflow-x-auto">
                    <table className="text-sm">
                        <thead><tr><th className="py-2 pr-6 text-left text-xs text-gray-500">Campo</th>{Object.entries(tipos).map(([t, r]) => <th key={t} className="px-4 text-xs text-gray-500">{r}</th>)}</tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {Object.entries(basicos).map(([campo, rotulo]) => (
                                <tr key={campo}>
                                    <td className="py-2 pr-6 text-gray-700">{rotulo}</td>
                                    {Object.keys(tipos).map(t => (
                                        <td key={t} className="px-4 text-center">
                                            {aplicaA(campo).includes(t)
                                                ? <input type="checkbox" checked={regrasForm.data.regras[t].includes(campo)} onChange={() => alternaRegra(t, campo)} className="rounded border-gray-300 text-cyan-600" />
                                                : <span className="text-gray-300">—</span>}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Button className="mt-3" variant="primary" loading={regrasForm.processing} onClick={() => regrasForm.post('/flow/contatos/regras', { preserveScroll: true })}>Salvar regras</Button>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card title={editando ? 'Editar campo' : 'Novo campo complementar'}>
                    <form onSubmit={salvar} className="space-y-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                            <input value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} maxLength={80} className="ds-input" placeholder="Ex.: Inscrição municipal" />
                            {form.errors.nome && <p className="mt-1 text-xs text-red-600">{form.errors.nome}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Explicação <span className="text-xs text-gray-400">(aparece abaixo do campo)</span></label>
                            <input value={form.data.explicacao} onChange={(e) => form.setData('explicacao', e.target.value)} maxLength={255} className="ds-input" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Formato</label>
                            <select value={form.data.formato} onChange={(e) => form.setData('formato', e.target.value)} className="ds-input">
                                {Object.entries(formatos).map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                            </select>
                        </div>
                        {form.data.formato === 'lista' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Opções <span className="text-xs text-gray-400">(uma por linha)</span></label>
                                <textarea value={opcoesTexto} onChange={(e) => setOpcoesTexto(e.target.value)} rows={4} className="ds-input" />
                                {form.errors.opcoes && <p className="mt-1 text-xs text-red-600">{form.errors.opcoes}</p>}
                            </div>
                        )}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Onde é pedido</label>
                            <select value={form.data.onde} onChange={(e) => form.setData('onde', e.target.value)} className="ds-input">
                                {Object.entries(ONDE).map(([k, r]) => <option key={k} value={k}>{r}</option>)}
                            </select>
                        </div>
                        <div>
                            <p className="block text-sm font-medium text-gray-700 mb-1">Perfis <span className="text-xs text-gray-400">(nenhum marcado = todos)</span></p>
                            <div className="flex flex-wrap gap-3">
                                {Object.entries(tipos).map(([t, r]) => (
                                    <label key={t} className="flex items-center gap-1.5 text-sm"><input type="checkbox" checked={form.data.perfis.includes(t)} onChange={() => alternaPerfil(t)} className="rounded border-gray-300 text-cyan-600" />{r}</label>
                                ))}
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.obrigatorio} onChange={(e) => form.setData('obrigatorio', e.target.checked)} className="rounded border-gray-300 text-cyan-600" />Obrigatório</label>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Ordem</label>
                            <input type="number" min={0} max={99} value={form.data.ordem} onChange={(e) => form.setData('ordem', e.target.value)} className="ds-input w-24" />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" variant="primary" loading={form.processing} disabled={! form.data.nome.trim() || (! editando && ativos >= maximo)}>{editando ? 'Salvar' : 'Criar'}</Button>
                            {editando && <Button type="button" variant="secondary" onClick={limpar}>Cancelar</Button>}
                        </div>
                    </form>
                </Card>

                <Card title={`Campos complementares (${ativos} de ${maximo} ativos)`} className="lg:col-span-2">
                    {campos.length === 0 ? <p className="text-sm text-gray-400">Nenhum campo complementar.</p> : (
                        <ul className="divide-y divide-gray-100">
                            {campos.map(c => (
                                <li key={c.id} className="py-2 flex items-start justify-between gap-3">
                                    <div className={c.ativo ? '' : 'opacity-50'}>
                                        <p className="text-sm font-medium text-gray-800">{c.nome}{c.obrigatorio && <span className="text-red-600"> *</span>}{! c.ativo && <span className="ml-2 text-[10px] rounded-full bg-gray-100 px-2 py-0.5">Desativado</span>}</p>
                                        <p className="text-xs text-gray-500">{formatos[c.formato]} · {ONDE[c.onde]} · {c.perfis?.length ? c.perfis.map(p => tipos[p]).join(', ') : 'todos os perfis'}{c.formato === 'lista' ? ` · ${c.opcoes.join(', ')}` : ''}</p>
                                        {c.explicacao && <p className="text-xs text-gray-400">{c.explicacao}</p>}
                                    </div>
                                    <span className="flex gap-3 text-xs shrink-0">
                                        <button type="button" className="text-blue-700 hover:underline" onClick={() => editar(c)}>Editar</button>
                                        <button type="button" className="text-amber-700 hover:underline" onClick={() => router.post(`/flow/contatos/campos/${c.id}/alternar`, {}, { preserveScroll: true })}>{c.ativo ? 'Desativar' : 'Reativar'}</button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}
