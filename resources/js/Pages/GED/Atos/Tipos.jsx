/**
 * Tipos de ato (TR 4.42.2, 4.42.3, 4.42.7): numeração, modelo .docx com marcadores, setores que
 * elaboram, quem assina por padrão (roteamento) e se aparece na consulta pública.
 */
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';
import Card from '../../../Components/Card';

const VAZIO = { nome: '', sigla: '', numeracao: 'tipo', setores: [], signatarios: [], publico: true, ativo: true };

function Multi({ itens, valor, onChange, rotulo }) {
    return (
        <div className="max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
            {itens.map(i => (
                <label key={i.id} className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={valor.includes(i.id)} onChange={(e) => onChange(e.target.checked ? [...valor, i.id] : valor.filter(v => v !== i.id))} /> {rotulo(i)}
                </label>
            ))}
        </div>
    );
}

export default function Tipos({ tipos = [], numeracoes = {}, marcadores = {}, unidades = [], usuarios = [] }) {
    const [editando, setEditando] = useState(null);
    const [d, setD] = useState(VAZIO);
    const [modelo, setModelo] = useState(null);
    const [erros, setErros] = useState({});
    const abrir = (t) => { setErros({}); setModelo(null); setD(t ? { ...VAZIO, ...t, setores: t.setores || [], signatarios: t.signatarios || [], remover_modelo: false } : VAZIO); setEditando(t ? t.id : 0); };
    const salvar = (e) => {
        e.preventDefault();
        const fd = new FormData();
        ['nome', 'sigla', 'numeracao'].forEach(k => fd.append(k, d[k]));
        ['publico', 'ativo'].forEach(k => fd.append(k, d[k] ? '1' : '0'));
        if (d.remover_modelo) fd.append('remover_modelo', '1');
        d.setores.forEach(s => fd.append('setores[]', s));
        d.signatarios.forEach(s => fd.append('signatarios[]', s));
        if (modelo) fd.append('modelo', modelo);
        router.post(editando ? `/atos/tipos/${editando}` : '/atos/tipos', fd, { forceFormData: true, preserveScroll: true, onSuccess: () => setEditando(null), onError: setErros });
    };

    return (
        <AdminLayout>
            <Head title="Tipos de ato" />
            <PageHeader title="Tipos de ato" subtitle="Numeração, modelo .docx, quem elabora e quem assina">
                <Button icon="fas fa-plus" onClick={() => abrir(null)}>Novo tipo</Button>
            </PageHeader>
            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto mb-6">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Tipo</th><th className="px-3 py-2">Numeração</th><th className="px-3 py-2">Modelo</th><th className="px-3 py-2">Assinam</th><th className="px-3 py-2">Público</th><th className="px-3 py-2" /></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {tipos.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-gray-400">Nenhum tipo de ato.</td></tr>}
                        {tipos.map(t => (
                            <tr key={t.id} className={t.ativo ? '' : 'text-gray-400'}>
                                <td className="px-3 py-2 font-medium">{t.nome} <span className="text-xs text-gray-400">{t.sigla}</span></td>
                                <td className="px-3 py-2 text-xs">{(numeracoes[t.numeracao] || '').split(' (')[0]}</td>
                                <td className="px-3 py-2 text-xs">{t.modelo_nome ? <><i className="fas fa-file-word text-blue-600 mr-1" />{t.modelo_nome}<span className="block text-gray-400">{t.marcadores.length} marcador(es)</span></> : 'Texto do editor'}</td>
                                <td className="px-3 py-2 text-xs">{usuarios.filter(u => (t.signatarios || []).includes(u.id)).map(u => u.name).join(', ') || '—'}</td>
                                <td className="px-3 py-2">{t.publico ? 'Sim' : 'Não'}</td>
                                <td className="px-3 py-2 text-right"><Button size="sm" variant="secondary" icon="fas fa-pen" onClick={() => abrir(t)}>Editar</Button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Card title="Marcadores dos modelos .docx" subtitle="Escreva-os no Word assim: ${ementa}. Outros marcadores viram campos para preencher no ato.">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    {Object.entries(marcadores).map(([k, r]) => <div key={k}><code className="text-cyan-700">{'${' + k + '}'}</code> <span className="text-gray-600">{r}</span></div>)}
                </div>
            </Card>

            <Modal show={editando !== null} onClose={() => setEditando(null)} title={editando ? 'Editar tipo de ato' : 'Novo tipo de ato'} maxWidth="2xl">
                <form onSubmit={salvar} className="space-y-3">
                    <div className="grid grid-cols-3 gap-3">
                        <label className="block text-sm text-gray-700 col-span-2">Nome<input value={d.nome} onChange={(e) => setD({ ...d, nome: e.target.value })} className="ds-input mt-1" placeholder="Decreto" /></label>
                        <label className="block text-sm text-gray-700">Sigla<input value={d.sigla} onChange={(e) => setD({ ...d, sigla: e.target.value })} className="ds-input mt-1" placeholder="DEC" /></label>
                    </div>
                    {(erros.nome || erros.sigla) && <p className="text-xs text-red-600">{erros.nome || erros.sigla}</p>}
                    <label className="block text-sm text-gray-700">Numeração<select value={d.numeracao} onChange={(e) => setD({ ...d, numeracao: e.target.value })} className="ds-input mt-1">
                        {Object.entries(numeracoes).map(([v, r]) => <option key={v} value={v}>{r}</option>)}</select></label>
                    <div>
                        <p className="text-sm text-gray-700 mb-1">Modelo .docx {d.modelo_nome && <span className="text-xs text-gray-500">— atual: {d.modelo_nome}</span>}</p>
                        <input type="file" accept=".docx" onChange={(e) => setModelo(e.target.files[0] || null)} className="text-sm" />
                        {d.modelo_nome && <label className="ml-3 text-xs text-gray-600"><input type="checkbox" checked={!! d.remover_modelo} onChange={(e) => setD({ ...d, remover_modelo: e.target.checked })} /> remover modelo (usar o texto do editor)</label>}
                        {erros.modelo && <p className="text-xs text-red-600">{erros.modelo}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div><p className="text-sm text-gray-700 mb-1">Setores que elaboram <span className="text-xs text-gray-400">(nenhum = todos)</span></p>
                            <Multi itens={unidades} valor={d.setores} onChange={(v) => setD({ ...d, setores: v })} rotulo={(u) => u.nome} /></div>
                        <div><p className="text-sm text-gray-700 mb-1">Quem assina por padrão</p>
                            <Multi itens={usuarios} valor={d.signatarios} onChange={(v) => setD({ ...d, signatarios: v })} rotulo={(u) => `${u.name}${u.cargo ? ` — ${u.cargo}` : ''}`} /></div>
                    </div>
                    <div className="flex gap-4 text-sm text-gray-700">
                        <label className="flex items-center gap-2"><input type="checkbox" checked={!! d.publico} onChange={(e) => setD({ ...d, publico: e.target.checked })} /> Aparece na consulta pública</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={!! d.ativo} onChange={(e) => setD({ ...d, ativo: e.target.checked })} /> Ativo</label>
                    </div>
                    <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setEditando(null)}>Cancelar</Button><Button type="submit">Salvar</Button></div>
                </form>
            </Modal>
        </AdminLayout>
    );
}