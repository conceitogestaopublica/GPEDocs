/**
 * Processos seletivos simplificados (TR 4.41.1): editais com período de inscrição, cargos,
 * requisitos e o assunto cujo formulário e documentos exigidos formam a ficha de inscrição.
 */
import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import Button from '../../../Components/Button';
import Modal from '../../../Components/Modal';

export const SITUACAO = { rascunho: ['Rascunho', 'bg-gray-100 text-gray-700'], agendado: ['Inscrições em breve', 'bg-sky-100 text-sky-800'],
    aberto: ['Inscrições abertas', 'bg-emerald-100 text-emerald-800'], encerrado: ['Encerrado', 'bg-amber-100 text-amber-800'] };
const data = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '');
const campo = (iso) => { if (! iso) return ''; const d = new Date(iso); const p = (n) => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`; };
const VAZIO = { titulo: '', edital_numero: '', descricao: '', requisitos: '', cargos: [], tipo_processo_id: '', inscricoes_inicio: '', inscricoes_fim: '', publicado: false };

export default function Pss({ editais = [], assuntos = [] }) {
    const [editando, setEditando] = useState(null);
    const form = useForm(VAZIO);
    const abrir = (e) => {
        form.clearErrors();
        form.setData(e ? { ...VAZIO, ...e, tipo_processo_id: e.tipo_processo_id || '', descricao: e.descricao || '', requisitos: e.requisitos || '', edital_numero: e.edital_numero || '',
            inscricoes_inicio: campo(e.inscricoes_inicio), inscricoes_fim: campo(e.inscricoes_fim) } : VAZIO);
        setEditando(e ? e.id : 0);
    };
    const salvar = (ev) => {
        ev.preventDefault();
        const ok = { preserveScroll: true, onSuccess: () => setEditando(null) };
        editando ? form.put(`/flow/pss/${editando}`, ok) : form.post('/flow/pss', ok);
    };
    const cargo = (i, k, v) => form.setData('cargos', form.data.cargos.map((c, j) => (j === i ? { ...c, [k]: v } : c)));

    return (
        <AdminLayout>
            <Head title="Processo seletivo" />
            <PageHeader title="Processo seletivo simplificado" subtitle="Editais com inscrição pelo portal; a ficha usa o formulário e os documentos do assunto escolhido">
                <Button icon="fas fa-plus" onClick={() => abrir(null)}>Novo edital</Button>
            </PageHeader>
            <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-500 text-left">
                        <tr><th className="px-3 py-2">Edital</th><th className="px-3 py-2">Cargos</th><th className="px-3 py-2">Inscrições</th><th className="px-3 py-2">Período</th><th className="px-3 py-2">Situação</th><th className="px-3 py-2" /></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {editais.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-gray-400">Nenhum processo seletivo cadastrado.</td></tr>}
                        {editais.map(e => {
                            const [rot, cor] = SITUACAO[e.situacao];
                            return (
                                <tr key={e.id}>
                                    <td className="px-3 py-2"><Link href={`/flow/pss/${e.id}`} className="text-cyan-700 font-medium hover:underline">{e.titulo}</Link>
                                        {e.edital_numero && <span className="block text-xs text-gray-500">Edital {e.edital_numero}</span>}</td>
                                    <td className="px-3 py-2 text-xs">{e.cargos.map(c => `${c.nome}${c.vagas ? ` (${c.vagas})` : ''}`).join(', ') || '—'}</td>
                                    <td className="px-3 py-2">{e.inscricoes}</td>
                                    <td className="px-3 py-2 text-xs whitespace-nowrap">{data(e.inscricoes_inicio)} a {data(e.inscricoes_fim)}</td>
                                    <td className="px-3 py-2"><span className={`text-[11px] rounded-full px-2 py-0.5 ${cor}`}>{rot}</span></td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                        <Button size="sm" variant="secondary" icon="fas fa-pen" onClick={() => abrir(e)}>Editar</Button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <Modal show={editando !== null} onClose={() => setEditando(null)} title={editando ? 'Editar edital' : 'Novo edital'} maxWidth="2xl">
                <form onSubmit={salvar} className="space-y-3">
                    <div className="grid grid-cols-3 gap-3">
                        <label className="block text-sm text-gray-700 col-span-2">Título<input value={form.data.titulo} onChange={(e) => form.setData('titulo', e.target.value)} className="ds-input mt-1" /></label>
                        <label className="block text-sm text-gray-700">Nº do edital<input value={form.data.edital_numero} onChange={(e) => form.setData('edital_numero', e.target.value)} className="ds-input mt-1" placeholder="01/2026" /></label>
                    </div>
                    {form.errors.titulo && <p className="text-xs text-red-600">{form.errors.titulo}</p>}
                    <label className="block text-sm text-gray-700">Descrição<textarea value={form.data.descricao} onChange={(e) => form.setData('descricao', e.target.value)} rows={2} className="ds-input !h-auto mt-1" /></label>
                    <label className="block text-sm text-gray-700">Requisitos<textarea value={form.data.requisitos} onChange={(e) => form.setData('requisitos', e.target.value)} rows={3} className="ds-input !h-auto mt-1" placeholder="Escolaridade, experiência, documentos…" /></label>
                    <div>
                        <p className="text-sm text-gray-700 mb-1">Cargos e vagas</p>
                        {form.data.cargos.map((c, i) => (
                            <div key={i} className="flex gap-2 mb-1">
                                <input value={c.nome} onChange={(e) => cargo(i, 'nome', e.target.value)} className="ds-input" placeholder="Cargo" />
                                <input type="number" min="0" value={c.vagas ?? ''} onChange={(e) => cargo(i, 'vagas', e.target.value)} className="ds-input !w-28" placeholder="Vagas" />
                                <Button type="button" variant="secondary" icon="fas fa-trash" onClick={() => form.setData('cargos', form.data.cargos.filter((_, j) => j !== i))} />
                            </div>
                        ))}
                        <Button type="button" size="sm" variant="secondary" icon="fas fa-plus" onClick={() => form.setData('cargos', [...form.data.cargos, { nome: '', vagas: '' }])}>Cargo</Button>
                    </div>
                    <label className="block text-sm text-gray-700">Ficha de inscrição (assunto)<select value={form.data.tipo_processo_id} onChange={(e) => form.setData('tipo_processo_id', e.target.value)} className="ds-input mt-1">
                        <option value="">Só os dados do candidato</option>{assuntos.map(a => <option key={a.id} value={a.id}>{a.nome}</option>)}</select>
                        <span className="text-[11px] text-gray-500">Os campos e os documentos exigidos desse assunto (Cadastros › Tipos de Processo) aparecem na inscrição.</span></label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm text-gray-700">Inscrições de<input type="datetime-local" value={form.data.inscricoes_inicio} onChange={(e) => form.setData('inscricoes_inicio', e.target.value)} className="ds-input mt-1" /></label>
                        <label className="block text-sm text-gray-700">até<input type="datetime-local" value={form.data.inscricoes_fim} onChange={(e) => form.setData('inscricoes_fim', e.target.value)} className="ds-input mt-1" /></label>
                    </div>
                    {(form.errors.inscricoes_inicio || form.errors.inscricoes_fim) && <p className="text-xs text-red-600">{form.errors.inscricoes_inicio || form.errors.inscricoes_fim}</p>}
                    <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={!! form.data.publicado} onChange={(e) => form.setData('publicado', e.target.checked)} /> Publicado no portal</label>
                    <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setEditando(null)}>Cancelar</Button>
                        <Button type="submit" loading={form.processing}>Salvar</Button></div>
                </form>
            </Modal>
        </AdminLayout>
    );
}